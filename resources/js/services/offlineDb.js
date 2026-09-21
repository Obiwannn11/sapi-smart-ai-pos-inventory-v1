/**
 * IndexedDB layer for offline POS.
 *
 * Pure, framework-agnostic: owns the database handle and schema, and knows
 * nothing about Vue. Composables (`useCatalogCache`, `useOfflineQueue`) build
 * on top of this.
 *
 * Two stores, both declared in schema v1 even though the outbox only starts
 * being written in phase C — declaring them together avoids a version bump
 * (and an upgrade path) later:
 *
 *   catalog  keyed manually; a single SNAPSHOT_KEY record holding the whole
 *            last-known catalog so the POS can render while offline.
 *   outbox   keyed by client_uuid (the same idempotency key the server dedups
 *            on), indexed by `status` so pending items are cheap to find.
 *
 * IndexedDB is unavailable in some contexts (older browsers, certain private
 * modes, blocked storage). Every helper degrades to a no-op / empty result
 * rather than throwing, so an unsupported browser simply loses offline support
 * instead of breaking the online POS.
 */

import { openDB } from 'idb';

const DB_NAME = 'sapi-pos';
const DB_VERSION = 1;

export const CATALOG_STORE = 'catalog';
export const OUTBOX_STORE = 'outbox';
export const SNAPSHOT_KEY = 'snapshot';

let dbPromise = null;

/**
 * Is IndexedDB usable in this context?
 */
export function isOfflineStorageSupported() {
    try {
        return typeof indexedDB !== 'undefined' && indexedDB !== null;
    } catch {
        // Accessing indexedDB can itself throw when storage is blocked.
        return false;
    }
}

/**
 * Open (once) and return the database, or null when IndexedDB is unusable.
 */
export function getDb() {
    if (!isOfflineStorageSupported()) {
        return Promise.resolve(null);
    }

    if (!dbPromise) {
        dbPromise = openDB(DB_NAME, DB_VERSION, {
            upgrade(db) {
                if (!db.objectStoreNames.contains(CATALOG_STORE)) {
                    db.createObjectStore(CATALOG_STORE);
                }

                if (!db.objectStoreNames.contains(OUTBOX_STORE)) {
                    const outbox = db.createObjectStore(OUTBOX_STORE, {
                        keyPath: 'client_uuid',
                    });
                    outbox.createIndex('status', 'status');
                }
            },
            blocked() {
                console.warn('[offlineDb] upgrade blocked by another open tab');
            },
        }).catch((err) => {
            console.error('[offlineDb] failed to open database:', err);
            // Reset so a later call can retry rather than caching the failure.
            dbPromise = null;

            return null;
        });
    }

    return dbPromise;
}

/**
 * Read one record. Returns `fallback` when storage is unusable or empty.
 */
export async function readRecord(store, key, fallback = null) {
    try {
        const db = await getDb();
        if (!db) return fallback;

        const value = await db.get(store, key);

        return value === undefined ? fallback : value;
    } catch (err) {
        console.error(`[offlineDb] read ${store}/${key} failed:`, err);

        return fallback;
    }
}

/**
 * Write one record. Returns true on success, false when storage is unusable.
 */
export async function writeRecord(store, value, key = undefined) {
    try {
        const db = await getDb();
        if (!db) return false;

        await db.put(store, value, key);

        return true;
    } catch (err) {
        console.error(`[offlineDb] write ${store} failed:`, err);

        return false;
    }
}

/**
 * Delete one record. Returns true on success.
 */
export async function deleteRecord(store, key) {
    try {
        const db = await getDb();
        if (!db) return false;

        await db.delete(store, key);

        return true;
    } catch (err) {
        console.error(`[offlineDb] delete ${store}/${key} failed:`, err);

        return false;
    }
}

/**
 * Read every record in a store, optionally filtered by an index value.
 */
export async function readAll(store, { index = null, query = null } = {}) {
    try {
        const db = await getDb();
        if (!db) return [];

        if (index) {
            return await db.getAllFromIndex(store, index, query ?? undefined);
        }

        return await db.getAll(store);
    } catch (err) {
        console.error(`[offlineDb] readAll ${store} failed:`, err);

        return [];
    }
}

/**
 * Ask the browser to make this origin's storage persistent — `[BL-016]` B.2.
 *
 * By default IndexedDB is "best effort": storage pressure or a tap on "Clear
 * browsing data" evicts it without warning or trace. For every other kind of
 * data here that is merely annoying (the catalog snapshot re-downloads), but
 * the outbox is the one thing in this application with NO copy on the server —
 * evicting it destroys sales that already happened in the real world.
 *
 * Chrome grants this silently from engagement heuristics (installed PWA,
 * bookmarked, frequent visits) and never prompts, so this is safe to call on
 * every POS open. Firefox prompts, which is why it is called from the POS and
 * not at app boot: the cashier screen is the only place where the answer is
 * worth a question.
 *
 * A denial is NOT a failure to report to the cashier — nothing they can do
 * about it, and offline selling still works exactly as before. It only means
 * the eviction window stays open, which is the situation today anyway.
 *
 * @returns {Promise<boolean>} whether storage is persistent afterwards.
 */
export async function requestPersistentStorage() {
    try {
        // Absent on older browsers and in insecure contexts. Optional chaining
        // is not enough here: reading `navigator.storage` can itself throw when
        // storage is blocked by policy, same as `indexedDB` above.
        if (typeof navigator === 'undefined' || !navigator.storage?.persist) {
            return false;
        }

        // Already granted (an earlier visit, or an installed PWA). Asking again
        // would be harmless but pointless.
        if (await navigator.storage.persisted?.()) {
            return true;
        }

        const granted = await navigator.storage.persist();

        if (!granted) {
            console.warn(
                '[offlineDb] persistent storage denied — the outbox can still be evicted',
            );
        }

        return granted;
    } catch (err) {
        console.warn('[offlineDb] persistent storage request failed:', err);

        return false;
    }
}
