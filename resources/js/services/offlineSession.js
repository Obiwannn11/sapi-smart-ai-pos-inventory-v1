/**
 * Offline data lifecycle around the session.
 *
 * A till is shared hardware: cashiers hand it over and log in and out on the
 * same device. Anything we cached that identifies a user or a tenant has to go
 * when they leave, or the next person could open the POS offline and be served
 * the previous session's page.
 *
 * What gets cleared:
 *   - PAGE_CACHE in the service worker (authenticated HTML: props, CSRF, name)
 *   - the catalog snapshot (tenant-scoped prices and stock)
 *
 * What deliberately survives:
 *   - the IndexedDB outbox. It holds sales that already happened in the real
 *     world but haven't reached the server. Wiping it on logout would destroy
 *     revenue. Guarding those rows against the *wrong* cashier syncing them is
 *     a separate concern, handled by the queue itself.
 */

import { CATALOG_STORE, SNAPSHOT_KEY, deleteRecord } from '@/services/offlineDb';

/**
 * Ask the service worker to drop its private page cache.
 *
 * Resolves when the SW confirms, or after `timeoutMs` — a logout must never
 * hang because the SW is missing, still installing, or unresponsive.
 */
function clearServiceWorkerPageCache(timeoutMs = 1000) {
    if (!('serviceWorker' in navigator) || !navigator.serviceWorker.controller) {
        return Promise.resolve(false);
    }

    return new Promise((resolve) => {
        const channel = new MessageChannel();
        const timer = setTimeout(() => resolve(false), timeoutMs);

        channel.port1.onmessage = () => {
            clearTimeout(timer);
            resolve(true);
        };

        try {
            navigator.serviceWorker.controller.postMessage(
                { type: 'CLEAR_PRIVATE_CACHES' },
                [channel.port2],
            );
        } catch {
            clearTimeout(timer);
            resolve(false);
        }
    });
}

/**
 * Clear per-user/per-tenant offline data. Call immediately before logging out.
 *
 * Never rejects: a failure to clear must not block the user from logging out.
 */
export async function clearPrivateOfflineData() {
    await Promise.allSettled([
        clearServiceWorkerPageCache(),
        deleteRecord(CATALOG_STORE, SNAPSHOT_KEY),
    ]);
}
