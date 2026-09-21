/**
 * Background Sync for the offline outbox — `[BL-016]` Bagian B.1.
 *
 * The hole this fills: `useOfflineQueue.flush()` only ever runs while the POS
 * page is open. A cashier who rings up sales offline and then closes the tab at
 * closing time leaves that money hanging in IndexedDB until somebody happens to
 * open the POS again. Background Sync lets the browser wake the service worker
 * once connectivity returns, tab or no tab.
 *
 * WHAT THE SERVICE WORKER IS ALLOWED TO DO, AND WHY IT IS SO LITTLE
 *
 * The worker POSTs the queued rows and stops there. It never touches the
 * outbox: no reconcile, no attempt counter, no deletes. That is deliberate and
 * it is what keeps this from becoming a second, drifting copy of the queue
 * logic. `client_uuid` makes it safe — the server dedups, so when the app next
 * opens, its own flush re-sends the same rows, gets `duplicate` back, and does
 * the bookkeeping in the one place that owns it. Worst case is sending twice,
 * which `useOfflineQueue` already documents as harmless.
 *
 * TWO THINGS THE WORKER CANNOT WORK OUT ON ITS OWN, so the page leaves them
 * behind for it while it is alive:
 *
 *   cashier_id  The server credits a synced sale to whoever is authenticated.
 *               The outbox can hold rows from several cashiers who shared the
 *               till, and sending the wrong one's rows would file A's sales
 *               under B's name and B's shift report. The page knows who is
 *               logged in; the worker has no session to ask.
 *   csrf_token  A service worker has no `document`, so it cannot read the meta
 *               tag. A stale token simply earns a 419, which the worker treats
 *               as "leave it queued" — exactly what the app does.
 *
 * WHY THE CACHE API AND NOT INDEXEDDB, where everything else offline lives.
 * Adding a store to `offlineDb` means bumping `DB_VERSION`, and an upgrade is
 * blocked for as long as any other tab still holds the old version open. That
 * stall would land on `enqueue()` — the one call in this application that must
 * never hang, because a cashier is standing at the till waiting to finish a
 * sale. A cache needs no schema and no migration, so the risk is not mitigated
 * but absent. It also happens to be where the worker's own state already lives.
 *
 * Both values are cleared on logout (services/offlineSession.js). After that
 * the worker has nobody to sync for and stops trying, which is correct: there
 * is no session cookie left for the request to authenticate with.
 */

/**
 * Keep all three in step with the same constants in `public/sw.js`. That file
 * is hand-written and outside the bundle, so it cannot import them — the names
 * are copied, not shared, and a mismatch fails silently. `OfflineDurabilityTest`
 * fails when the two sides drift apart.
 */
export const OUTBOX_SYNC_TAG = 'sapi-outbox-sync';
export const SYNC_META_CACHE = 'sapi-sync-meta';
export const SYNC_CREDENTIALS_KEY = '/__sapi/sync-credentials';

/**
 * Is the Cache API usable here? Absent outside secure contexts, and reading it
 * can itself throw when storage is blocked by policy.
 */
function hasCacheStorage() {
    try {
        return typeof caches !== 'undefined' && caches !== null;
    } catch {
        return false;
    }
}

/**
 * Leave the worker what it needs to send this cashier's rows.
 *
 * Called when a sale is queued rather than on page load: that is the moment
 * there is something to sync, and the moment both values are known good.
 *
 * @param {{cashierId: number|null, csrfToken: string}} credentials
 * @returns {Promise<boolean>}
 */
export async function rememberSyncCredentials({ cashierId, csrfToken }) {
    if (!cashierId || !csrfToken || !hasCacheStorage()) {
        return false;
    }

    try {
        const cache = await caches.open(SYNC_META_CACHE);

        await cache.put(
            SYNC_CREDENTIALS_KEY,
            new Response(
                JSON.stringify({
                    cashier_id: cashierId,
                    csrf_token: csrfToken,
                    saved_at: new Date().toISOString(),
                }),
                { headers: { 'Content-Type': 'application/json' } },
            ),
        );

        return true;
    } catch (err) {
        console.warn('[backgroundSync] could not store sync credentials:', err);

        return false;
    }
}

/**
 * Drop them. Call immediately before logging out — see the note above about
 * why the worker going quiet is the right outcome.
 *
 * @returns {Promise<boolean>}
 */
export async function forgetSyncCredentials() {
    if (!hasCacheStorage()) {
        return false;
    }

    try {
        return await caches.delete(SYNC_META_CACHE);
    } catch (err) {
        console.warn('[backgroundSync] could not clear sync credentials:', err);

        return false;
    }
}

/**
 * Ask the browser to fire our sync event once it believes it has connectivity.
 *
 * Registering the same tag twice coalesces into one pending sync, so this is
 * safe to call on every queued sale and on every failed flush.
 *
 * Never throws and never blocks: Background Sync is a bonus on top of the
 * in-page flush, not a replacement for it. It is absent in Safari and Firefox
 * entirely — which costs those users nothing they have today, and matches this
 * entry's Android-only scope.
 *
 * @returns {Promise<boolean>} whether a sync was registered.
 */
export async function requestOutboxSync() {
    try {
        // `navigator.serviceWorker.ready` hangs forever when nothing is
        // registered — and nothing is, in dev, where the registration in app.js
        // is gated on PROD. Checking for a controller first is the same guard
        // offlineSession.js uses, for the same reason.
        if (!('serviceWorker' in navigator) || !navigator.serviceWorker.controller) {
            return false;
        }

        const registration = await navigator.serviceWorker.ready;

        if (!registration.sync) {
            return false;
        }

        await registration.sync.register(OUTBOX_SYNC_TAG);

        return true;
    } catch (err) {
        // Chrome refuses the registration when the user has blocked background
        // sync for the site. Nothing to do about it, and nothing the cashier
        // needs to see: the in-page flush is untouched.
        console.warn('[backgroundSync] could not register outbox sync:', err);

        return false;
    }
}
