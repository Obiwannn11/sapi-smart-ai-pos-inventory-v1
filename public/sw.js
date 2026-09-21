/**
 * SAPI POS — Service Worker
 *
 * Scope: "/" (served from public root so it can control the whole app).
 * Strategy:
 *   - Precache a minimal app shell (offline page, manifest, icons).
 *   - Cache-first for hashed build assets under /build/assets/** (immutable).
 *   - Network-first for navigations, falling back to a cached copy for
 *     offline-capable routes, then to /offline.html.
 *   - Never touch non-GET requests (POST/PUT/etc. always hit the network).
 *
 * Offline transactions are deliberately NOT queued here. The app layer owns the
 * outbox (IndexedDB) so it keeps full control over idempotency and stock
 * conflicts — see docs/phases-2/PHASE-PWA_Offline-Transaction-Sync.md. Since
 * `[BL-016]` B.1 this worker does POST queued rows on a Background Sync, but it
 * still never WRITES to the outbox: it pushes and forgets, and the app does the
 * bookkeeping. See the sync handler at the bottom of this file.
 *
 * PRIVACY: PAGE_CACHE stores authenticated HTML (Inertia props, CSRF token,
 * the cashier's name). It is per-device, not per-user, so it MUST be cleared on
 * logout — otherwise the next user on a shared till could open the POS offline
 * and see the previous user's cached page. The app posts CLEAR_PRIVATE_CACHES
 * before logging out. SYNC_META_CACHE is private too, but the page deletes that
 * one itself — `caches` is reachable from the window, so it needs no round trip
 * through here. The IndexedDB outbox is intentionally NOT cleared by either:
 * it holds un-synced sales that must survive a logout.
 *
 * NOTE: this is a hand-written SW (not Workbox). Bump CACHE_VERSION to force a
 * refresh of the precached shell when these files change.
 */

// v4 — `offline.html` mendapat tautan "Buka Kasir" (`[BL-096]`). Ia terdaftar
// di SHELL_ASSETS, dan shell hanya diprecache ulang saat versi ini berubah;
// tanpa naik versi, setiap pemasangan yang sudah ada akan terus menyajikan
// halaman buntu yang lama dan perbaikannya tidak pernah sampai ke siapa pun.
const CACHE_VERSION = 'v4';
const SHELL_CACHE = `sapi-shell-${CACHE_VERSION}`;
const ASSET_CACHE = `sapi-assets-${CACHE_VERSION}`;
const PAGE_CACHE = `sapi-pages-${CACHE_VERSION}`;

/**
 * Deliberately NOT versioned, and deliberately spared by the activate sweep
 * below. It holds what the Background Sync handler needs to send the outbox
 * (see the bottom of this file); wiping it on a version bump would silently
 * disarm background syncing until the next sale is rung up. Its contents are
 * per-user, so logout clears it — services/offlineSession.js.
 */
const SYNC_META_CACHE = 'sapi-sync-meta';

/**
 * Keep this list to files the offline shell actually RENDERS. `cache.addAll()`
 * is all-or-nothing: one 404 here rejects the whole install and the app loses
 * its offline shell entirely, silently. `/sapi-logo.png` sat here until
 * `[BL-077]` — 92 KB precached on every install for an image no page has ever
 * displayed. `StaticAssetBudgetTest` now fails if an entry stops existing.
 */
const SHELL_ASSETS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

/**
 * Routes whose last successful HTML response is kept so they still boot when
 * the network is gone. Keep this list tight — every entry is authenticated
 * HTML sitting on disk until logout.
 */
const OFFLINE_CAPABLE_ROUTES = ['/cashier/pos'];

function isOfflineCapableRoute(pathname) {
    return OFFLINE_CAPABLE_ROUTES.some(
        (route) => pathname === route || pathname === `${route}/`,
    );
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE).then((cache) => cache.addAll(SHELL_ASSETS)),
    );
    // Activate this SW immediately once installed.
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                ![SHELL_CACHE, ASSET_CACHE, PAGE_CACHE, SYNC_META_CACHE].includes(key),
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Only handle GET; let the browser deal with POST/checkout/etc. directly.
    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Only handle same-origin requests (skip fonts.googleapis, APIs on other hosts).
    if (url.origin !== self.location.origin) return;

    // Immutable, content-hashed build assets → cache-first.
    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(cacheFirst(request));
        return;
    }

    // Page navigations → network-first with offline fallback.
    if (request.mode === 'navigate') {
        event.respondWith(networkFirstNavigation(request));
        return;
    }
});

async function cacheFirst(request) {
    const cache = await caches.open(ASSET_CACHE);
    const cached = await cache.match(request);
    if (cached) return cached;
    try {
        const response = await fetch(request);
        if (response && response.status === 200) {
            cache.put(request, response.clone());
        }
        return response;
    } catch (err) {
        return cached || Response.error();
    }
}

async function networkFirstNavigation(request) {
    const url = new URL(request.url);
    const offlineCapable = isOfflineCapableRoute(url.pathname);

    try {
        const response = await fetch(request);

        // Keep the latest good copy of offline-capable pages so they can boot
        // without the network. Only 200s — never cache a redirect to /login or
        // an error page, which would strand the cashier on a dead shell.
        if (offlineCapable && response && response.status === 200 && !response.redirected) {
            const cache = await caches.open(PAGE_CACHE);
            cache.put(request, response.clone());
        }

        return response;
    } catch (err) {
        if (offlineCapable) {
            const pages = await caches.open(PAGE_CACHE);
            // Match on URL only: ignore ?query so /cashier/pos?foo=1 still hits.
            const cached = await pages.match(request, { ignoreSearch: true });
            if (cached) return cached;
        }

        const shell = await caches.open(SHELL_CACHE);
        const offline = await shell.match('/offline.html');

        return offline || Response.error();
    }
}

/**
 * The app asks us to drop anything user-identifying before it logs out.
 * Deliberately scoped to PAGE_CACHE: shell/assets are public, and the
 * IndexedDB outbox (owned by the app) must survive to protect un-synced sales.
 */
self.addEventListener('message', (event) => {
    if (event.data?.type !== 'CLEAR_PRIVATE_CACHES') return;

    event.waitUntil(
        caches.delete(PAGE_CACHE).then(() => {
            // Let the page await completion before it POSTs /logout.
            event.ports?.[0]?.postMessage({ ok: true });
        }),
    );
});

/* -------------------------------------------------------------------------
 * Background Sync — `[BL-016]` Bagian B.1
 *
 * The hole: `useOfflineQueue.flush()` only runs while the POS page is open. A
 * cashier who sells offline and then closes the tab at closing time leaves that
 * money sitting in IndexedDB until somebody opens the POS again. Here the
 * browser wakes us once it believes connectivity is back, tab or no tab.
 *
 * THIS HANDLER PUSHES AND FORGETS. It POSTs the queued rows and stops — no
 * reconcile, no attempt counter, no deletes, not a single write to the outbox.
 * That restraint is the whole design:
 *
 *   - `client_uuid` makes a re-send free. The server dedups, so when the app
 *     next opens, its own flush sends the same rows, gets `duplicate` back, and
 *     clears them. The sale reached the server hours earlier, which is the part
 *     that mattered.
 *   - The queue's rules (MAX_ATTEMPTS, `failed` parking, per-row verdicts) stay
 *     in exactly one place. A copy of them here would rot, and it would rot in
 *     the file this project has no test runner for.
 *
 * Two values it cannot work out alone — the logged-in cashier and a CSRF token
 * — are left behind by the page in SYNC_META_CACHE. The reasoning is written
 * out in resources/js/services/backgroundSync.js; the short version is that a
 * worker has no session to ask and no `document` to read a meta tag from, and
 * that a cache avoids the IndexedDB migration that would otherwise be able to
 * stall a sale.
 *
 * NOT TESTABLE FROM THE SUITE. There is no JavaScript test runner here, and a
 * real exercise means a real browser: queue a sale offline, close every tab,
 * restore the network, and watch the request arrive. Guards in
 * `OfflineDurabilityTest` only keep the two sides from drifting apart.
 * ------------------------------------------------------------------------- */

/** Keep in step with resources/js/services/backgroundSync.js. */
const OUTBOX_SYNC_TAG = 'sapi-outbox-sync';

/** Keep in step with resources/js/services/offlineDb.js. */
const OUTBOX_DB_NAME = 'sapi-pos';
const OUTBOX_STORE = 'outbox';

/** Keep in step with resources/js/services/backgroundSync.js. */
const SYNC_CREDENTIALS_KEY = '/__sapi/sync-credentials';

/** Keep in step with SyncOfflineTransactionsRequest::MAX_BATCH_SIZE. */
const OUTBOX_BATCH_SIZE = 50;

const OUTBOX_SYNC_ENDPOINT = '/cashier/transactions/sync';

self.addEventListener('sync', (event) => {
    if (event.tag !== OUTBOX_SYNC_TAG) return;

    // Throwing from here is how we ask for a retry: the browser re-fires the
    // event later with backoff. Everything a retry cannot fix (no credentials,
    // empty queue, dead session) resolves quietly instead.
    event.waitUntil(pushOutboxToServer());
});

async function pushOutboxToServer() {
    const credentials = await readSyncCredentials();

    // Nobody is logged in — cleared at logout. The rows stay put and go out
    // when their owner signs back in.
    if (!credentials) return;

    const db = await openOutboxDb();
    if (!db) return;

    try {
        const rows = await readAllFrom(db, OUTBOX_STORE);

        // The same two filters the page applies: only rows still queued, and
        // only rows belonging to the cashier whose session we are about to
        // borrow. Sending someone else's would file their sales under the wrong
        // name and the wrong shift report.
        const batch = rows
            .filter((row) => row.status === 'queued' && row.cashier_id === credentials.cashier_id)
            .slice(0, OUTBOX_BATCH_SIZE);

        if (batch.length === 0) return;

        const response = await fetch(OUTBOX_SYNC_ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': credentials.csrf_token,
            },
            credentials: 'same-origin',
            body: JSON.stringify({ transactions: batch.map(toSyncPayload) }),
        });

        // Session or token gone. A retry would fail identically, so let it rest
        // — the rows are untouched and the page deals with it at next login.
        if (response.status === 401 || response.status === 419) return;

        if (!response.ok) {
            throw new Error(`[sw] outbox sync rejected: HTTP ${response.status}`);
        }

        // Deliberately nothing here. See the push-and-forget note above.
    } finally {
        db.close();
    }
}

/**
 * Strip queue bookkeeping — the server only wants the transaction itself.
 * Mirrors `toPayload()` in resources/js/composables/useOfflineQueue.js, and
 * `OfflineDurabilityTest` fails if the two field lists drift apart.
 */
function toSyncPayload(entry) {
    return {
        client_uuid: entry.client_uuid,
        occurred_at: entry.occurred_at,
        device_id: entry.device_id,
        total_amount: entry.total_amount,
        notes: entry.notes,
        items: entry.items,
        payments: entry.payments,
        customer_name: entry.customer_name ?? null,
        table_number: entry.table_number ?? null,
        upsell_events: entry.upsell_events ?? [],
    };
}

/**
 * Open the app's database WITHOUT naming a version.
 *
 * That detail is load-bearing. Passing the version would make this worker
 * responsible for running migrations, and it holds no copy of the schema — an
 * upgrade from here would produce a database with missing stores. Opening
 * version-less attaches to whatever the app has already built, and if the app
 * has never run, `onupgradeneeded` fires on an empty database and we walk away.
 *
 * Resolves null rather than rejecting: a sync that cannot read storage has
 * nothing to retry.
 */
function openOutboxDb() {
    return new Promise((resolve) => {
        let request;

        try {
            request = indexedDB.open(OUTBOX_DB_NAME);
        } catch (err) {
            resolve(null);

            return;
        }

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => resolve(null);
        request.onblocked = () => resolve(null);
        request.onupgradeneeded = () => {
            // No database yet, so no outbox either. Abort so we do not leave an
            // empty one behind for the app to inherit.
            try {
                request.transaction.abort();
            } catch (err) {
                // Aborting is best-effort; resolving null is what matters.
            }

            resolve(null);
        };
    });
}

/**
 * The cashier id and CSRF token the page left behind, or null when there is
 * nothing usable there. Never throws: a sync that cannot read them has nothing
 * to retry.
 */
async function readSyncCredentials() {
    try {
        const cache = await caches.open(SYNC_META_CACHE);
        const stored = await cache.match(SYNC_CREDENTIALS_KEY);

        if (!stored) return null;

        const credentials = await stored.json();

        if (!credentials || !credentials.cashier_id || !credentials.csrf_token) return null;

        return credentials;
    } catch (err) {
        return null;
    }
}

/** Read a whole store, or an empty list. Read-only, like everything here. */
function readAllFrom(db, storeName) {
    return new Promise((resolve) => {
        if (!db.objectStoreNames.contains(storeName)) {
            resolve([]);

            return;
        }

        try {
            const request = db.transaction(storeName, 'readonly').objectStore(storeName).getAll();

            request.onsuccess = () => resolve(request.result ?? []);
            request.onerror = () => resolve([]);
        } catch (err) {
            resolve([]);
        }
    });
}
