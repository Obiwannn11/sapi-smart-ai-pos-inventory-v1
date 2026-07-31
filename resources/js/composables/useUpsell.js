/**
 * useUpsell — mencocokkan isi keranjang dengan indeks saran dari server.
 *
 * Pembagian kerjanya sengaja begini: server menghitung KANDIDAT (ko-okurensi,
 * filter stok, filter kedaluwarsa) dan mengirimnya sekali sebagai props POS;
 * composable ini hanya MENYARING terhadap keranjang yang berubah tiap klik.
 * Karena indeksnya ikut props, ia ikut ter-snapshot `useCatalogCache` dan saran
 * tetap muncul saat perangkat offline — endpoint per-perubahan-keranjang akan
 * mati justru di warung yang sinyalnya paling buruk.
 *
 * Tiga aturan yang menentukan fiturnya dipakai atau ditutup terus:
 *
 *   Saran yang sudah diputuskan TIDAK muncul lagi selama keranjang itu hidup.
 *   Saran yang muncul kembali setelah ditolak adalah cara tercepat membuat
 *   kasir membenci fitur ini.
 *
 *   Yang sempat TAMPIL tetap dicatat meski tidak diambil. Tanpa penyebutnya,
 *   angka "berapa persen saran diterima" tidak berarti apa-apa.
 *
 *   DITOLAK berbeda dari DIABAIKAN. Yang pertama sampai ke pelanggan dan
 *   dijawab tidak; yang kedua cuma lewat di layar kasir. Mencampur keduanya
 *   membuat angka konversi mengukur kedisiplinan kasir dan mutu saran
 *   sekaligus, sehingga tidak mengukur apa pun ([BL-025]).
 */

import { ref, computed, watch } from 'vue';

const EMPTY_INDEX = { by_variant: {}, cart_level: [], max_per_transaction: 2, mandatory: false };

/**
 * @param {import('vue').Ref|import('vue').ComputedRef} index  indeks saran dari server
 * @param {import('vue').Ref} cart                             keranjang POS
 * @param {object} options
 * @param {(variantId: number) => number} options.getVariantStock
 * @param {(variantId: number) => number} options.getCartQtyForVariant
 */
export function useUpsell(index, cart, { getVariantStock, getCartQtyForVariant }) {
    // DITOLAK pelanggan setelah ditawarkan — per keranjang, bukan per sesi.
    //
    // Dulu bernama "dismissed" dan bermakna "tutup saran ini". Sejak mode wajib
    // ada, tombol × berarti keputusan yang sesungguhnya: ditawarkan, pelanggan
    // menolak. Keduanya sah menyelesaikan saran; yang tidak boleh hanyalah
    // melewatinya tanpa keputusan ([BL-025]).
    const rejectedKeys = ref(new Set());

    // Diambil kasir, beserta tambahan omzet yang benar-benar terjadi.
    const acceptedByKey = ref(new Map());

    // Pernah tampil di layar. Inilah penyebut angka konversinya.
    const shownByKey = ref(new Map());

    const activeIndex = computed(() => index.value ?? EMPTY_INDEX);

    const maxPerTransaction = computed(
        () => activeIndex.value.max_per_transaction ?? EMPTY_INDEX.max_per_transaction
    );

    /** Owner mewajibkan tiap saran diselesaikan sebelum boleh bayar. */
    const mandatory = computed(() => activeIndex.value.mandatory === true);

    /**
     * Modifier yang sudah menempel pada baris keranjang varian tertentu.
     */
    const modifierIdsOnVariant = (variantId) => {
        const ids = new Set();

        for (const line of cart.value) {
            if (line.variant_id !== variantId) continue;

            for (const modifier of line.modifiers ?? []) ids.add(modifier.id);
        }

        return ids;
    };

    const variantIdsInCart = computed(() => new Set(cart.value.map((line) => line.variant_id)));

    /**
     * Masih ada stok tersisa untuk ditawarkan? Stok snapshot bersifat indikatif,
     * tapi menawarkan barang yang jelas-jelas habis adalah cacat yang langsung
     * terlihat pelanggan.
     */
    const hasRoomInStock = (variantId) => {
        if (!variantId) return true;

        return getVariantStock(variantId) - getCartQtyForVariant(variantId) > 0;
    };

    const isRelevant = (suggestion) => {
        if (rejectedKeys.value.has(suggestion.key)) return false;
        if (acceptedByKey.value.has(suggestion.key)) return false;

        // Menyarankan sesuatu yang sudah ada di keranjang membuat saran
        // terlihat asal-asalan.
        if (suggestion.suggested_variant_id && variantIdsInCart.value.has(suggestion.suggested_variant_id)) {
            return false;
        }

        if (!hasRoomInStock(suggestion.suggested_variant_id)) return false;

        if (suggestion.type === 'attach') {
            return !modifierIdsOnVariant(suggestion.trigger_variant_id).has(suggestion.suggested_modifier_id);
        }

        return true;
    };

    const suggestions = computed(() => {
        if (cart.value.length === 0) return [];

        const pool = new Map();

        for (const variantId of variantIdsInCart.value) {
            for (const suggestion of activeIndex.value.by_variant?.[variantId] ?? []) {
                pool.set(suggestion.key, suggestion);
            }
        }

        for (const suggestion of activeIndex.value.cart_level ?? []) {
            pool.set(suggestion.key, suggestion);
        }

        return [...pool.values()]
            .filter(isRelevant)
            .sort((a, b) => b.score - a.score)
            .slice(0, maxPerTransaction.value);
    });

    // Apa pun yang sempat terlihat kasir masuk penyebut, sekali saja.
    watch(
        suggestions,
        (visible) => {
            for (const suggestion of visible) {
                if (!shownByKey.value.has(suggestion.key)) {
                    shownByKey.value.set(suggestion.key, suggestion);
                }
            }
        },
        { immediate: true, deep: false }
    );

    /**
     * Ditawarkan, pelanggan menolak. Dicatat sebagai keputusan — bukan sebagai
     * saran yang berlalu begitu saja.
     */
    const reject = (suggestion) => {
        rejectedKeys.value = new Set(rejectedKeys.value).add(suggestion.key);

        if (!shownByKey.value.has(suggestion.key)) {
            shownByKey.value.set(suggestion.key, suggestion);
        }
    };

    /**
     * @param {object} suggestion
     * @param {number} extraAmount tambahan omzet yang BENAR-BENAR terjadi
     *   (harga × qty), bukan angka indikatif dari indeks.
     */
    const accept = (suggestion, extraAmount) => {
        const next = new Map(acceptedByKey.value);
        next.set(suggestion.key, { ...suggestion, actual_extra_amount: extraAmount });
        acceptedByKey.value = next;

        if (!shownByKey.value.has(suggestion.key)) {
            shownByKey.value.set(suggestion.key, suggestion);
        }
    };

    /**
     * Payload yang menumpang checkout — satu jalur tulis untuk POS online,
     * POS offline, dan open bill.
     */
    const collectEvents = () =>
        [...shownByKey.value.values()].map((suggestion) => {
            const accepted = acceptedByKey.value.get(suggestion.key);
            const status = accepted
                ? 'accepted'
                : rejectedKeys.value.has(suggestion.key)
                    ? 'rejected'
                    : 'ignored';

            return {
                type: suggestion.type,
                status,
                reason: suggestion.reason ?? null,
                label: suggestion.label,
                extra_amount: accepted ? accepted.actual_extra_amount : 0,
                trigger_variant_id: suggestion.trigger_variant_id ?? null,
                suggested_variant_id: suggestion.suggested_variant_id ?? null,
                suggested_modifier_id: suggestion.suggested_modifier_id ?? null,
            };
        });

    /** Dipanggil setelah keranjang benar-benar selesai (checkout atau dikosongkan). */
    const reset = () => {
        rejectedKeys.value = new Set();
        acceptedByKey.value = new Map();
        shownByKey.value = new Map();
    };

    return {
        suggestions,
        mandatory,
        // Saran yang masih menunggu keputusan. Kosong = tidak ada yang menahan
        // tombol bayar; `suggestions` sendiri sudah membuang yang diterima
        // maupun yang ditolak.
        unresolved: suggestions,
        accept,
        reject,
        collectEvents,
        reset,
    };
}
