/**
 * Stable per-device identifier.
 *
 * Offline sales carry the id of the till that rang them up, so an owner
 * reconciling negative stock can tell which device drifted. It identifies
 * HARDWARE, not a person — it deliberately survives logout (unlike the cached
 * page and catalog, which do not).
 *
 * localStorage rather than IndexedDB: it is a single short string read on every
 * enqueue, and it must be readable synchronously.
 */

const DEVICE_ID_KEY = 'sapi.deviceId';

let cached = null;

/**
 * Get (or create once) this device's id.
 *
 * Returns null when storage is unavailable — the queue treats device_id as
 * optional rather than failing a sale over a missing label.
 */
export function getDeviceId() {
    if (cached) return cached;

    try {
        let id = localStorage.getItem(DEVICE_ID_KEY);

        if (!id) {
            id = `till-${crypto.randomUUID().slice(0, 8)}`;
            localStorage.setItem(DEVICE_ID_KEY, id);
        }

        cached = id;

        return id;
    } catch {
        return null;
    }
}
