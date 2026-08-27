/**
 * useCatalogCache — keep a last-known-good copy of the POS catalog in
 * IndexedDB so the cashier can still see products, prices and modifiers when
 * the connection drops.
 *
 * The catalog arrives as Inertia props (POSController@index renders it), not
 * from an API endpoint, so there is nothing to fetch: we simply *harvest* the
 * props on every online visit and replay them when offline.
 *
 * Why read IndexedDB offline instead of the props that are already there?
 * When the service worker serves the POS document from its cache, the page
 * boots with the props embedded in that cached document — stale, and of
 * unknown age. The IndexedDB snapshot is refreshed on every online visit and
 * carries an explicit `cachedAt`, so it is never older than the document cache
 * and is the only copy we can honestly timestamp for the cashier.
 *
 * Stock in the snapshot is last-known and drifts the moment another device
 * sells something. It is shown as indicative only — never as truth.
 */

import { ref, computed } from 'vue';
import { BUSINESS_TZ } from '@/support/date';
import {
    CATALOG_STORE,
    SNAPSHOT_KEY,
    readRecord,
    writeRecord,
    isOfflineStorageSupported,
} from '@/services/offlineDb';

export function useCatalogCache() {
    const snapshot = ref(null);
    const loaded = ref(false);

    /**
     * Persist the catalog currently rendered by the server.
     *
     * Strips nothing: the POS reads these objects straight back, so the shape
     * must stay identical to the props. Vue refs/proxies cannot be structured-
     * cloned into IndexedDB, hence the JSON round-trip to get plain objects.
     */
    async function saveSnapshot({ products, categories, paymentMethods, upsell = null, tax = null }) {
        if (!isOfflineStorageSupported()) return false;

        const record = {
            products: toPlain(products ?? []),
            categories: toPlain(categories ?? []),
            paymentMethods: toPlain(paymentMethods ?? []),
            // Konteks pajak ikut, dan ini BUKAN kenyamanan ([BL-065]).
            // Berbeda dari saran upsell yang basi paling buruk jadi tidak
            // relevan, tarif pajak yang tidak ikut membuat kasir offline
            // menghitung total tanpa pajak sementara server menghitung
            // dengan pajak — dan setiap penjualan offline mendarat sebagai
            // needs_review sampai seseorang menyadarinya.
            tax: tax === null ? null : toPlain(tax),
            // Indeks saran upsell ikut menumpang snapshot ini. Ia sengaja
            // dianggap sama umurnya dengan katalog: saran basi paling buruk
            // hanya jadi tidak relevan, sedangkan HARGA basi akan merugikan —
            // dan karena itu indeks ini tidak pernah membawa harga diskon.
            upsell: upsell === null ? null : toPlain(upsell),
            cachedAt: new Date().toISOString(),
        };

        const ok = await writeRecord(CATALOG_STORE, record, SNAPSHOT_KEY);
        if (ok) {
            snapshot.value = record;
        }

        return ok;
    }

    /**
     * Load the last saved catalog. Returns null when nothing was ever cached.
     */
    async function loadSnapshot() {
        snapshot.value = await readRecord(CATALOG_STORE, SNAPSHOT_KEY, null);
        loaded.value = true;

        return snapshot.value;
    }

    const cachedAt = computed(() => snapshot.value?.cachedAt ?? null);

    /**
     * "Katalog per 10:24" — short label so the cashier can judge staleness.
     */
    const cachedAtLabel = computed(() => {
        if (!cachedAt.value) return null;

        return new Date(cachedAt.value).toLocaleTimeString('id-ID', {
            timeZone: BUSINESS_TZ,
            hour: '2-digit',
            minute: '2-digit',
        });
    });

    return {
        snapshot,
        loaded,
        cachedAt,
        cachedAtLabel,
        saveSnapshot,
        loadSnapshot,
    };
}

/**
 * Vue reactive proxies throw on structuredClone (the algorithm IndexedDB uses).
 * JSON is enough here: the catalog is plain data — no Dates, no Maps, no undefined.
 */
function toPlain(value) {
    return JSON.parse(JSON.stringify(value));
}
