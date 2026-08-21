<script setup>
import { router, Head, usePage } from '@inertiajs/vue3';
import { ref, computed, watch, onUnmounted, onMounted } from 'vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import { useFlash } from '@/composables/useFlash';
import ProductCard from '@/Components/ProductCard.vue';
import CartItem from '@/Components/CartItem.vue';
import ModifierModal from '@/Components/ModifierModal.vue';
import PaymentModal from '@/Components/PaymentModal.vue';
import ReceiptModal from '@/Components/ReceiptModal.vue';
import TransactionSuccessModal from '@/Components/TransactionSuccessModal.vue';
import CashierTopbar from '@/Components/CashierTopbar.vue';
import UpsellStrip from '@/Components/UpsellStrip.vue';
import OrderIdentityModal from '@/Components/OrderIdentityModal.vue';
import SkeletonGrid from '@/Components/Skeleton/SkeletonGrid.vue';
import SkeletonCard from '@/Components/Skeleton/SkeletonCard.vue';
import { useOnlineStatus } from '@/composables/useOnlineStatus';
import { useCatalogCache } from '@/composables/useCatalogCache';
import { useOfflineQueue } from '@/composables/useOfflineQueue';
import { useUpsell } from '@/composables/useUpsell';

const props = defineProps({
    categories: Array,
    products: Array,
    paymentMethods: Array,
    // Saklar foto bukti bayar non-tunai milik toko ([BL-075]).
    paymentProofEnabled: { type: Boolean, default: false },
    cashDrawer: Object,
    tenantName: { type: String, default: 'SAPI POS' },
    upsell: { type: Object, default: null },
});

const { show: showFlash } = useFlash();

// --- Offline catalog ---
// The catalog arrives as props. While online we harvest them into IndexedDB;
// while offline we render that snapshot instead. We prefer the snapshot over
// the props when offline because the page itself may have been served from the
// service worker's cache — those props are stale and, unlike the snapshot, we
// cannot tell the cashier how old they are.
const { isOnline, markOffline } = useOnlineStatus();
const { snapshot, loaded: snapshotLoaded, loadSnapshot, saveSnapshot, cachedAtLabel } = useCatalogCache();

const usingCachedCatalog = computed(() => !isOnline.value && snapshot.value !== null);

const catalogProducts = computed(() =>
    usingCachedCatalog.value ? (snapshot.value.products ?? []) : (props.products ?? [])
);
const catalogCategories = computed(() =>
    usingCachedCatalog.value ? (snapshot.value.categories ?? []) : (props.categories ?? [])
);
const catalogPaymentMethods = computed(() =>
    usingCachedCatalog.value ? (snapshot.value.paymentMethods ?? []) : (props.paymentMethods ?? [])
);

// Saran upsell ikut katalog — dan ikut snapshot-nya. Harganya: indeks berumur
// sama dengan katalognya. Untuk SARAN itu pertukaran yang benar (saran basi
// paling buruk hanya jadi tidak relevan, dan stok tetap diverifikasi ulang saat
// masuk keranjang); untuk HARGA tidak, dan karena itu fase ini tidak menyentuh
// harga sama sekali.
const catalogUpsell = computed(() =>
    usingCachedCatalog.value ? (snapshot.value.upsell ?? null) : (props.upsell ?? null)
);

/**
 * Katalog sekarang ditunda (Inertia::defer di POSController), jadi propsnya
 * belum ada saat layar pertama muncul. Ini yang membedakan "belum sampai" —
 * yang tampil sebagai kerangka grid — dari "sudah sampai tapi kosong", yang
 * tampil sebagai "Produk tidak ditemukan".
 *
 * Saat offline tidak ada permintaan lanjutan yang bisa dikirim, jadi yang
 * ditunggu bukan props melainkan snapshot IndexedDB. Begitu pembacaannya
 * selesai, halaman ini dianggap siap meski snapshotnya ternyata tidak ada:
 * kasir tanpa katalog tersimpan harus melihat kalimat yang menjelaskan itu
 * (spanduk offline di atas sudah menyebutkannya), bukan kerangka yang berdenyut
 * selamanya.
 */
const catalogReady = computed(() =>
    isOnline.value ? Array.isArray(props.products) : snapshotLoaded.value
);

// Offline payments are cash-only, enforced here AND on the server. Card/QRIS
// need a gateway round-trip we cannot make, so a "paid" we can't verify would
// be a guess — and the server would reject it on sync anyway, after the
// customer already walked out.
const availablePaymentMethods = computed(() => {
    if (isOnline.value) return catalogPaymentMethods.value;

    return catalogPaymentMethods.value.filter((method) => method.type === 'cash');
});

// --- Offline queue ---
const {
    enqueue,
    flush,
    refresh: refreshQueue,
    pendingCount,
    failedCount,
    flushing,
} = useOfflineQueue();

onMounted(() => {
    if (!isOnline.value) loadSnapshot();

    refreshQueue().then(() => {
        if (isOnline.value) flush();
    });
});

/**
 * Panen katalog menunggu propsnya datang, bukan saat mount. Sejak katalog
 * ditunda ([BL-037]) `props.products` masih undefined pada cat pertama, dan
 * menyimpan saat itu akan menimpa snapshot yang masih bagus dengan katalog
 * kosong — kasir baru akan tahu akibatnya nanti, saat koneksinya jatuh dan
 * layarnya kosong. `upsell` sengaja satu grup dengan `products` di server,
 * jadi keduanya sudah sampai bersamaan saat watcher ini jalan.
 */
watch(() => props.products, (products) => {
    if (!isOnline.value || !Array.isArray(products)) return;

    saveSnapshot({
        products,
        categories: props.categories,
        paymentMethods: props.paymentMethods,
        upsell: props.upsell,
    });
}, { immediate: true });

watch(isOnline, (online) => {
    if (!online) {
        // Opened online, then the connection dropped: pull the snapshot in so
        // the notice and `cachedAt` are accurate rather than showing props of
        // unknown age.
        if (!snapshot.value) loadSnapshot();

        return;
    }

    flush();
});

// The `online` event is the primary trigger; this interval is the safety net
// for the cases it misses — a captive portal that "connects" without firing an
// event, or a device that came back while the tab was hidden. flush() no-ops
// when there is nothing queued, so an idle till costs nothing.
const SYNC_INTERVAL_MS = 60_000;
let syncTimer = null;

onMounted(() => {
    syncTimer = setInterval(() => {
        if (isOnline.value) flush();
    }, SYNC_INTERVAL_MS);
});

onUnmounted(() => clearInterval(syncTimer));

const syncNow = async () => {
    const result = await flush();

    if (!result) {
        showFlash('Tidak ada transaksi offline yang menunggu.', 'success');

        return;
    }

    if (result.ok) {
        showFlash(`${result.synced} transaksi offline tersinkron.`, 'success');

        return;
    }

    const reason = {
        auth: 'Sesi berakhir — silakan login ulang untuk menyinkronkan.',
        network: 'Sinkronisasi gagal: jaringan tidak stabil.',
        server: 'Sinkronisasi gagal: server menolak permintaan.',
    }[result.reason] ?? 'Sinkronisasi gagal.';

    showFlash(reason, 'error');
};

// --- State ---
const selectedCategoryId = ref(null);
const searchQuery = ref('');
const cart = ref([]);
const showModifierModal = ref(false);
const selectedProduct = ref(null);
const showPaymentModal = ref(false);
const showSuccessModal = ref(false);
const showReceiptModal = ref(false);
const lastTransaction = ref(null);
const processing = ref(false);

// Idempotency key per checkout. Dibuat sekali per percobaan checkout dan
// dipertahankan lintas retry (jaringan flaky) supaya server men-dedup dobel
// request; direset ke null hanya setelah transaksi sukses dibuat.
const checkoutUuid = ref(null);
const getCheckoutUuid = () => {
    if (!checkoutUuid.value) {
        checkoutUuid.value = crypto.randomUUID();
    }
    return checkoutUuid.value;
};

// --- Identitas pesanan ([BL-026]) ---
//
// Mode ditentukan owner dan dibagikan lewat shared data, jadi tidak perlu prop
// tersendiri dan tetap terbaca di halaman kasir mana pun.
const inertiaPage = usePage();
const identityMode = computed(() => inertiaPage.props.auth?.tenant?.order_identity_mode ?? 'none');

// Jalur mana yang menunggu identitas: 'pay' atau 'open_bill'. Satu modal, dua
// pemanggil — inilah yang dulu tidak ada, sehingga transaksi bayar-langsung
// tidak bisa diberi identitas apa pun.
const pendingIdentityFor = ref(null);
const orderIdentity = ref({ customer_name: null, table_number: null });

/**
 * Bentuk input untuk jalur tertentu.
 *
 * Tagihan terbuka SELALU ditanya, jatuh ke nama saat owner belum memilih mode:
 * tagihan yang menunggu dibayar harus bisa dikenali lagi nanti, dan itu benar
 * bahkan untuk outlet yang tidak memanggil pesanan. Bayar langsung hanya
 * ditanya bila owner memang memilih mode — outlet yang berjalan hari ini tidak
 * mendapat satu ketukan tambahan tanpa ada yang memintanya.
 *
 * `code` tidak pernah membuka modal: nomornya lahir di server.
 */
const identityFormFor = (intent) => {
    if (identityMode.value === 'table') return 'table';
    if (identityMode.value === 'name') return 'name';
    if (identityMode.value === 'code') return intent === 'open_bill' ? 'name' : null;

    return intent === 'open_bill' ? 'name' : null;
};

const identityForm = computed(() => identityFormFor(pendingIdentityFor.value) ?? 'name');

const resetOrderIdentity = () => {
    orderIdentity.value = { customer_name: null, table_number: null };
};

// --- Helpers ---
const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

// --- Filtered Products ---
const filteredProducts = computed(() => {
    let list = catalogProducts.value;

    if (selectedCategoryId.value) {
        list = list.filter(p => p.category_id === selectedCategoryId.value);
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(p =>
            p.name.toLowerCase().includes(q) ||
            (p.variants || []).some(v => v.name.toLowerCase().includes(q))
        );
    }

    return list;
});

// --- Cart Logic ---
const cartTotal = computed(() => {
    return cart.value.reduce((total, item) => {
        let itemPrice = Number(item.unit_price);
        if (item.modifiers && item.modifiers.length > 0) {
            itemPrice += item.modifiers.reduce((sum, m) => sum + Number(m.extra_price), 0);
        }
        return total + (itemPrice * item.qty);
    }, 0);
});

const cartItemCount = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.qty, 0);
});

const selectProduct = (product) => {
    const availableVariants = (product.variants || []).filter(v => v.stock > 0);
    const hasModifiers = product.modifier_groups && product.modifier_groups.length > 0;

    if (availableVariants.length === 1 && !hasModifiers) {
        const variant = availableVariants[0];
        addToCart({
            variant_id: variant.id,
            variant_name: `${product.name} - ${variant.name}`,
            // Harga BERDISKON bila ada ([BL-018]). Server menghitung ulang
            // harganya sendiri saat checkout, jadi layar yang memakai harga
            // katalog akan menyebut satu angka lalu menagih angka lain.
            unit_price: Number(variant.effective_price ?? variant.price),
            qty: 1,
            modifiers: [],
            notes: '',
        });
    } else {
        selectedProduct.value = product;
        showModifierModal.value = true;
    }
};

const getVariantStock = (variantId) => {
    for (const product of catalogProducts.value) {
        const variant = (product.variants || []).find(v => v.id === variantId);
        if (variant) return variant.stock;
    }
    return 0;
};

const getCartQtyForVariant = (variantId) => {
    return cart.value
        .filter(c => c.variant_id === variantId)
        .reduce((sum, c) => sum + c.qty, 0);
};

const addToCart = (item) => {
    // item dengan catatan berbeda = baris terpisah
    const existingIdx = cart.value.findIndex(c =>
        c.variant_id === item.variant_id &&
        JSON.stringify(c.modifiers.map(m => m.id).sort()) === JSON.stringify((item.modifiers || []).map(m => m.id).sort()) &&
        (c.notes || '') === (item.notes || '')
    );

    const stock = getVariantStock(item.variant_id);
    const currentCartQty = getCartQtyForVariant(item.variant_id);
    if (currentCartQty + item.qty > stock) {
        showFlash(`Stok tidak cukup. Tersedia: ${stock}, di keranjang: ${currentCartQty}`, 'error');
        return;
    }

    if (existingIdx >= 0) {
        cart.value[existingIdx].qty += item.qty;
    } else {
        cart.value.push({ ...item, notes: item.notes || '' });
    }
};

const updateCartQty = (index, newQty) => {
    if (newQty <= 0) return;
    const item = cart.value[index];
    const stock = getVariantStock(item.variant_id);
    const otherCartQty = getCartQtyForVariant(item.variant_id) - item.qty;
    if (otherCartQty + newQty > stock) {
        showFlash(`Stok tidak cukup. Tersedia: ${stock}`, 'error');
        return;
    }
    cart.value[index].qty = newQty;
};

const updateCartNotes = (index, notes) => {
    cart.value[index].notes = notes;
};

const removeCartItem = (index) => {
    cart.value.splice(index, 1);
};

// --- Upsell ---

/**
 * Apakah jejak sebuah saran yang sudah diterima MASIH ada di keranjang?
 *
 * Dipakai untuk menarik sendiri penerimaan yang barisnya sudah dihapus kasir
 * ([BL-092]). Tanpa pemeriksaan ini, "hapus lalu tambah ulang" — satu-satunya
 * jalan keluar yang dulu tersedia — meninggalkan catatan upsell berhasil untuk
 * penjualan yang dibatalkan.
 */
const upsellStillApplied = (suggestion) => {
    if (suggestion.type === 'attach') {
        return cart.value.some((line) =>
            line.variant_id === suggestion.trigger_variant_id
            && (line.modifiers ?? []).some((modifier) => modifier.id === suggestion.suggested_modifier_id)
        );
    }

    // Naik ukuran menimpa varian barisnya, barang tertekan dan aturan pemilik
    // menambah baris baru — ketiganya berujung pada varian yang sama di
    // keranjang, jadi pemeriksaannya pun sama.
    return cart.value.some((line) => line.variant_id === suggestion.suggested_variant_id);
};

const {
    suggestions: upsellSuggestions,
    accepted: upsellAccepted,
    mandatory: upsellMandatory,
    unresolved: upsellUnresolved,
    accept: acceptUpsell,
    reject: rejectUpsell,
    retract: retractUpsell,
    collectEvents: collectUpsellEvents,
    reset: resetUpsell,
} = useUpsell(catalogUpsell, cart, {
    getVariantStock,
    getCartQtyForVariant,
    isApplied: upsellStillApplied,
});

/**
 * Terapkan saran ke keranjang, lalu catat tambahan omzet yang BENAR-BENAR
 * terjadi — bukan angka indikatif dari indeks, yang tidak tahu qty barisnya.
 *
 * Tiga jenis saran menyentuh keranjang dengan cara berbeda: add-on menempel ke
 * baris yang sudah ada, naik ukuran MENUKAR variannya, dan barang tertekan
 * menambah baris baru.
 */
const applyUpsell = (suggestion) => {
    if (suggestion.type === 'attach') {
        const line = cart.value.find((item) => item.variant_id === suggestion.trigger_variant_id);
        if (!line) return;

        line.modifiers = [
            ...(line.modifiers ?? []),
            {
                id: suggestion.suggested_modifier_id,
                name: suggestion.label,
                extra_price: Number(suggestion.extra_amount),
            },
        ];

        acceptUpsell(suggestion, Number(suggestion.extra_amount) * line.qty);

        return;
    }

    if (suggestion.type === 'upsize') {
        const line = cart.value.find((item) => item.variant_id === suggestion.trigger_variant_id);
        if (!line) return;

        // Varian tujuan harus muat sebanyak qty baris ini — kalau tidak, tawaran
        // yang diterima kasir akan gagal justru saat pelanggan sudah setuju.
        const stock = getVariantStock(suggestion.suggested_variant_id);
        const reserved = getCartQtyForVariant(suggestion.suggested_variant_id);

        if (reserved + line.qty > stock) {
            showFlash(`Stok ${suggestion.label} tidak cukup untuk ditukar.`, 'error');

            return;
        }

        const previousPrice = Number(line.unit_price);

        // Salinan varian lama ikut disimpan: naik ukuran MENIMPA barisnya, jadi
        // hanya inilah bekal yang dipunyai pembatalan nanti ([BL-092]).
        const restore = {
            variant_id: line.variant_id,
            variant_name: line.variant_name,
            unit_price: previousPrice,
        };

        line.variant_id = suggestion.suggested_variant_id;
        line.variant_name = suggestion.suggested_variant_name ?? suggestion.label;
        line.unit_price = Number(suggestion.suggested_variant_price ?? previousPrice);

        acceptUpsell(suggestion, (line.unit_price - previousPrice) * line.qty, restore);

        return;
    }

    // pressed_stock — baris baru, harga katalog. Tanpa potongan: sistem belum
    // punya tempat sah untuk menaruh harga di bawah katalog (lihat [BL-018]).
    const price = Number(suggestion.suggested_variant_price ?? suggestion.extra_amount);
    const before = cart.value.length;

    addToCart({
        variant_id: suggestion.suggested_variant_id,
        variant_name: suggestion.suggested_variant_name ?? suggestion.label,
        unit_price: price,
        qty: 1,
        modifiers: [],
        notes: '',
    });

    // addToCart menolak diam-diam saat stok tak cukup; jangan catat sebagai
    // diterima kalau barangnya tidak benar-benar masuk keranjang.
    if (cart.value.length === before) return;

    acceptUpsell(suggestion, price);
};

/**
 * Tarik kembali saran yang terlanjur diterima ([BL-092]).
 *
 * Membereskan KERANJANG dan CATATANNYA sekaligus. Membereskan salah satunya
 * saja persis melahirkan masalah yang tombol ini ada untuk menutupnya: barang
 * hilang dari struk tapi upsell-nya tetap tercatat berhasil, atau sebaliknya.
 *
 * Sarannya kembali menunggu keputusan, bukan langsung jadi "ditolak" — kasir
 * yang salah pencet dan pelanggan yang membatalkan adalah dua hal berbeda, dan
 * hanya kasir yang tahu mana yang baru saja terjadi.
 */
const undoUpsell = (suggestion) => {
    if (suggestion.type === 'attach') {
        const line = cart.value.find((item) =>
            item.variant_id === suggestion.trigger_variant_id
            && (item.modifiers ?? []).some((modifier) => modifier.id === suggestion.suggested_modifier_id)
        );

        if (line) {
            line.modifiers = line.modifiers.filter((modifier) => modifier.id !== suggestion.suggested_modifier_id);
        }
    } else if (suggestion.type === 'upsize' && suggestion.restore) {
        const line = cart.value.find((item) => item.variant_id === suggestion.suggested_variant_id);

        if (line) {
            line.variant_id = suggestion.restore.variant_id;
            line.variant_name = suggestion.restore.variant_name;
            line.unit_price = suggestion.restore.unit_price;
        }
    } else {
        // Barang tertekan dan aturan pemilik menambah SATU baris berisi satu
        // barang. Kalau kasir sempat menaikkan qty-nya, yang ditarik hanya
        // barang yang datang dari saran ini.
        const index = cart.value.findIndex((item) => item.variant_id === suggestion.suggested_variant_id);

        if (index >= 0) {
            if (cart.value[index].qty > 1) {
                cart.value[index].qty -= 1;
            } else {
                cart.value.splice(index, 1);
            }
        }
    }

    retractUpsell(suggestion);
};

// --- Inline "Kosongkan" confirmation ---
const confirmingClear = ref(false);
let _clearTimer = null;

const requestClearCart = () => {
    confirmingClear.value = true;
    clearTimeout(_clearTimer);
    _clearTimer = setTimeout(() => { confirmingClear.value = false; }, 2500);
};

const cancelClearCart = () => {
    confirmingClear.value = false;
    clearTimeout(_clearTimer);
};

const clearCart = () => {
    cart.value = [];
    resetUpsell();
    confirmingClear.value = false;
    clearTimeout(_clearTimer);
};

onUnmounted(() => clearTimeout(_clearTimer));

// --- Checkout ---

/**
 * Kenapa tombol BAYAR mati. Dikembalikan sebagai kalimat, bukan boolean:
 * tombol kelabu tanpa sebab adalah jalan buntu, dan kasir yang menemuinya di
 * depan pelanggan tidak punya cara menebak apa yang kurang ([BL-025]).
 */
const checkoutBlockedReason = computed(() => {
    if (cart.value.length === 0) return 'Keranjang masih kosong.';
    if (processing.value) return '';

    if (upsellMandatory.value && upsellUnresolved.value.length > 0) {
        const labels = upsellUnresolved.value.map((s) => s.label).join(', ');

        return `Selesaikan penawaran dulu: ${labels}. Tandai diterima atau ditolak pelanggan.`;
    }

    return '';
});

const canCheckout = computed(() => !processing.value && checkoutBlockedReason.value === '');

const openPaymentModal = () => {
    if (!canCheckout.value) return;

    // Identitas ditanya SEBELUM pembayaran: ia milik pesanan, bukan milik
    // pembayarannya, dan menanyakannya setelah uang berpindah berarti menahan
    // pelanggan yang sudah selesai.
    if (identityFormFor('pay')) {
        pendingIdentityFor.value = 'pay';

        return;
    }

    resetOrderIdentity();
    showPaymentModal.value = true;
};

/**
 * Kasir menutup modal identitas tanpa memutuskan. Berbeda dari "Lewati":
 * membatalkan mengembalikannya ke keranjang, bukan meneruskan ke pembayaran.
 */
const cancelIdentity = () => {
    pendingIdentityFor.value = null;
};

const confirmIdentity = (identity) => {
    const intent = pendingIdentityFor.value;
    orderIdentity.value = identity;
    pendingIdentityFor.value = null;

    if (intent === 'pay') {
        showPaymentModal.value = true;
    } else if (intent === 'open_bill') {
        submitOpenBill();
    }
};

const cartToItems = () => cart.value.map(item => ({
    variant_id: item.variant_id,
    variant_name: item.variant_name,
    qty: item.qty,
    unit_price: item.unit_price,
    modifiers: (item.modifiers || []).map(m => ({
        id: m.id,
        name: m.name,
        extra_price: m.extra_price,
    })),
    notes: item.notes || null,
    // Harga khusus owner ([BL-018]). Server memeriksa ulang wewenang DAN
    // alasannya — yang dikirim di sini hanya niatnya.
    override_unit_price: item.override_unit_price ?? null,
    discount_reason: item.discount_reason ?? null,
}));

// --- Harga khusus di bawah lantai untung ([BL-018]) ---

/**
 * Hanya owner. Kasir tidak diberi jalan sama sekali — bukan "bisa tapi
 * dicatat", melainkan tidak tersedia. Ditegakkan lagi di server; ini sekadar
 * agar tombolnya tidak menggoda orang yang akan ditolak.
 */
const isOwner = computed(() => inertiaPage.props.auth?.user?.role === 'owner');

const overrideTarget = ref(null);
const overridePrice = ref('');
const overrideReason = ref('');

const openOverride = (idx) => {
    const line = cart.value[idx];

    overrideTarget.value = idx;
    overridePrice.value = String(line.override_unit_price ?? line.unit_price ?? '');
    overrideReason.value = line.discount_reason ?? '';
};

const closeOverride = () => {
    overrideTarget.value = null;
    overridePrice.value = '';
    overrideReason.value = '';
};

const overrideValid = computed(() =>
    Number(overridePrice.value) > 0 && overrideReason.value.trim().length > 0
);

const applyOverride = () => {
    if (!overrideValid.value || overrideTarget.value === null) return;

    const line = cart.value[overrideTarget.value];

    // Dibulatkan KE ATAS ke kelipatan 500, sama seperti server — kalau tidak,
    // total di layar meleset dari total yang ditagih.
    const price = Math.ceil(Number(overridePrice.value) / 500) * 500;

    line.override_unit_price = price;
    line.discount_reason = overrideReason.value.trim();
    line.unit_price = price;

    closeOverride();
};

const clearOverride = (idx) => {
    const line = cart.value[idx];

    line.override_unit_price = null;
    line.discount_reason = null;

    // Kembali ke harga efektif katalog — yang bisa saja tetap berdiskon.
    const variant = catalogProducts.value
        .flatMap((product) => product.variants || [])
        .find((v) => v.id === line.variant_id);

    if (variant) {
        line.unit_price = Number(variant.effective_price ?? variant.price);
    }
};

/**
 * Save a sale the server cannot be told about right now.
 *
 * The cart is only cleared once the row is actually on disk — if IndexedDB is
 * unavailable we must not pretend the sale was recorded, or the cashier would
 * hand over goods against a transaction that exists nowhere.
 */
const queueOfflineSale = async (payments) => {
    const stored = await enqueue({
        clientUuid: getCheckoutUuid(),
        items: cartToItems(),
        payments,
        totalAmount: cartTotal.value,
        upsellEvents: collectUpsellEvents(),
        // Nama/meja ikut tersimpan; nomor panggil tidak bisa — urutannya milik
        // server dan perangkat offline tidak tahu sudah sampai mana.
        customerName: orderIdentity.value.customer_name,
        tableNumber: orderIdentity.value.table_number,
    });

    if (!stored) {
        showFlash(
            'Gagal menyimpan transaksi offline di perangkat ini. Jangan tutup halaman — catat manual.',
            'error',
        );

        return false;
    }

    showPaymentModal.value = false;
    cart.value = [];
    resetUpsell();
    resetOrderIdentity();
    checkoutUuid.value = null;
    showFlash('Tersimpan offline. Akan tersinkron otomatis saat kembali online.', 'success');

    return true;
};

const handlePayment = async (payments) => {
    if (processing.value) return;
    processing.value = true;

    if (!isOnline.value) {
        await queueOfflineSale(payments);
        processing.value = false;

        return;
    }

    const data = {
        items: cartToItems(),
        payments: payments,
        notes: null,
        client_uuid: getCheckoutUuid(),
        upsell_events: collectUpsellEvents(),
        customer_name: orderIdentity.value.customer_name,
        table_number: orderIdentity.value.table_number,
    };

    router.post('/cashier/transactions', data, {
        preserveScroll: true,
        onSuccess: (page) => {
            showPaymentModal.value = false;
            const txData = page.props.flash?.lastTransaction;
            if (txData) {
                lastTransaction.value = txData;
                showSuccessModal.value = true;
            }
            cart.value = [];
            resetUpsell();
            resetOrderIdentity();
            checkoutUuid.value = null;
        },
        // navigator.onLine said we were online but the request never landed
        // (dead uplink, captive portal, server down). The sale is real, so fall
        // back to the queue rather than dropping it. The same client_uuid is
        // reused, so if the request did reach the server after all, the sync
        // dedups it instead of double-charging.
        onError: async (errors) => {
            if (Object.keys(errors ?? {}).length > 0) return;

            markOffline();
            await queueOfflineSale(payments);
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

// --- Success / Receipt flow ---
const closeSuccessModal = () => {
    showSuccessModal.value = false;
    lastTransaction.value = null;
};

const printFromSuccess = () => {
    showSuccessModal.value = false;
    showReceiptModal.value = true;
};

// --- Open Bill ---
const saveAsOpenBill = () => {
    if (cart.value.length === 0 || processing.value) return;

    // Open bills stay online-only, deliberately. Unlike a completed sale, an
    // open bill is a *pending* record the cashier expects to find and settle
    // later — possibly from another till. Queuing it locally would make it
    // invisible to every other device until sync, so the safer answer is to say
    // no rather than lose track of an unpaid order.
    if (!isOnline.value) {
        showFlash('Tunda Bayar tidak tersedia saat offline. Selesaikan pembayaran tunai.', 'error');

        return;
    }

    pendingIdentityFor.value = 'open_bill';
};

const submitOpenBill = () => {
    if (processing.value) return;
    processing.value = true;

    const data = {
        items: cartToItems(),
        payments: null,
        notes: null,
        is_open_bill: true,
        customer_name: orderIdentity.value.customer_name,
        table_number: orderIdentity.value.table_number,
        client_uuid: getCheckoutUuid(),
        upsell_events: collectUpsellEvents(),
    };

    router.post('/cashier/transactions', data, {
        preserveScroll: true,
        onSuccess: () => {
            cart.value = [];
            resetUpsell();
            resetOrderIdentity();
            checkoutUuid.value = null;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

// --- Resizable Cart Panel ---
const CART_WIDTH_KEY = 'cashier.cartWidth';
const CART_MIN_WIDTH = 300;
const CART_MAX_WIDTH = 640;
const CART_DEFAULT_WIDTH = 384; // matches lg:w-96

const cartWidth = ref(CART_DEFAULT_WIDTH);
const isResizingCart = ref(false);

const clampCartWidth = (value) => {
    return Math.min(CART_MAX_WIDTH, Math.max(CART_MIN_WIDTH, value));
};

onMounted(() => {
    const stored = Number(localStorage.getItem(CART_WIDTH_KEY));
    if (stored) {
        cartWidth.value = clampCartWidth(stored);
    }
});

const pointerX = (event) => {
    return event.touches ? event.touches[0].clientX : event.clientX;
};

const onResizeMove = (event) => {
    if (!isResizingCart.value) return;
    // Cart is anchored to the right edge, so width grows as the pointer moves left.
    cartWidth.value = clampCartWidth(window.innerWidth - pointerX(event));
    event.preventDefault();
};

const stopResizeCart = () => {
    if (!isResizingCart.value) return;
    isResizingCart.value = false;
    document.body.style.userSelect = '';
    document.body.style.cursor = '';
    localStorage.setItem(CART_WIDTH_KEY, String(Math.round(cartWidth.value)));

    window.removeEventListener('mousemove', onResizeMove);
    window.removeEventListener('mouseup', stopResizeCart);
    window.removeEventListener('touchmove', onResizeMove);
    window.removeEventListener('touchend', stopResizeCart);
};

const startResizeCart = (event) => {
    isResizingCart.value = true;
    document.body.style.userSelect = 'none';
    document.body.style.cursor = 'col-resize';

    window.addEventListener('mousemove', onResizeMove);
    window.addEventListener('mouseup', stopResizeCart);
    window.addEventListener('touchmove', onResizeMove, { passive: false });
    window.addEventListener('touchend', stopResizeCart);
    event.preventDefault();
};

const resetCartWidth = () => {
    cartWidth.value = CART_DEFAULT_WIDTH;
    localStorage.setItem(CART_WIDTH_KEY, String(CART_DEFAULT_WIDTH));
};

onUnmounted(stopResizeCart);

</script>

<template>
    <Head title="Kasir" />
    <div class="h-screen flex flex-col bg-background overflow-hidden">
        <FlashMessage />

        <!-- Top Bar -->
        <CashierTopbar :title="tenantName" />

        <!-- Offline notice: the catalog is a snapshot and stock is only a hint. -->
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-150 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="!isOnline"
                class="shrink-0 flex items-center gap-2 px-4 py-2 bg-amber-50 border-b border-amber-200 text-amber-800"
            >
                <span class="relative flex w-2 h-2 shrink-0">
                    <span class="absolute inline-flex w-full h-full rounded-full bg-amber-400 opacity-75 animate-ping"></span>
                    <span class="relative inline-flex w-2 h-2 rounded-full bg-amber-500"></span>
                </span>
                <span class="text-xs font-semibold">Mode Offline</span>
                <span class="text-amber-400 text-[10px] select-none">·</span>
                <span class="text-xs">
                    <template v-if="cachedAtLabel">Katalog per {{ cachedAtLabel }} — stok indikatif, hanya tunai.</template>
                    <template v-else>Katalog tersimpan tidak ditemukan — data mungkin tidak lengkap.</template>
                </span>
                <span v-if="pendingCount > 0" class="ml-auto text-xs font-medium">
                    {{ pendingCount }} transaksi menunggu sinkronisasi
                </span>
            </div>
        </Transition>

        <!-- Unsynced sales while online: the cashier should know money is still
             sitting on this device, and be able to push it without waiting. -->
        <div
            v-if="isOnline && (pendingCount > 0 || failedCount > 0)"
            class="shrink-0 flex items-center gap-2 px-4 py-2 bg-sky-50 border-b border-sky-200 text-sky-800"
        >
            <svg class="w-3.5 h-3.5 shrink-0" :class="flushing ? 'animate-spin' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span class="text-xs">
                <template v-if="flushing">Menyinkronkan transaksi offline…</template>
                <template v-else-if="failedCount > 0">
                    {{ failedCount }} transaksi offline gagal tersinkron dan perlu ditinjau.
                </template>
                <template v-else>{{ pendingCount }} transaksi offline menunggu sinkronisasi.</template>
            </span>
            <button
                @click="syncNow"
                :disabled="flushing"
                class="ml-auto text-xs font-semibold underline underline-offset-2 hover:text-sky-950 transition disabled:opacity-40 disabled:no-underline"
            >
                Sync sekarang
            </button>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex overflow-hidden">
            <!-- LEFT: Product Grid -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Search + Category Filter -->
                <div class="p-4 pb-2 shrink-0 space-y-3">
                    <!-- Search -->
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari produk..."
                            class="w-full pl-10 pr-4 py-2.5 bg-white border border-border rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                        />
                    </div>

                    <!-- Category Tabs -->
                    <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
                        <button
                            @click="selectedCategoryId = null"
                            :class="[
                                'px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition',
                                selectedCategoryId === null
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-white text-gray-600 border border-border hover:border-primary/30'
                            ]"
                        >
                            Semua
                        </button>
                        <button
                            v-for="cat in catalogCategories"
                            :key="cat.id"
                            @click="selectedCategoryId = cat.id"
                            :class="[
                                'px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition',
                                selectedCategoryId === cat.id
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-white text-gray-600 border border-border hover:border-primary/30'
                            ]"
                        >
                            {{ cat.name }}
                        </button>
                    </div>
                </div>

                <!-- Product Grid -->
                <div class="flex-1 overflow-y-auto px-4 pb-4">
                    <!-- Kerangka katalog: grid dan bentuk kartunya sama dengan
                         yang di bawah, jadi tidak ada yang bergeser saat produk
                         sungguhan menggantikannya. -->
                    <SkeletonGrid
                        v-if="!catalogReady"
                        :count="8"
                        columns="grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4"
                        label="Memuat katalog produk…"
                    >
                        <SkeletonCard media :lines="2" footer border-width="border-2" />
                    </SkeletonGrid>
                    <div v-else-if="filteredProducts.length > 0"
                         class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-3 lg:grid-cols-4 gap-3">
                        <ProductCard
                            v-for="product in filteredProducts"
                            :key="product.id"
                            :product="product"
                            @select="selectProduct"
                        />
                    </div>
                    <div v-else class="flex items-center justify-center h-48 text-gray-400 text-sm">
                        Produk tidak ditemukan
                    </div>
                </div>
            </div>

            <!-- Resize Handle: drag / long-press to change cart width -->
            <div
                @mousedown="startResizeCart"
                @touchstart="startResizeCart"
                @dblclick="resetCartWidth"
                :class="[
                    'group relative w-1.5 shrink-0 cursor-col-resize select-none touch-none flex items-center justify-center transition-colors',
                    isResizingCart ? 'bg-primary/60' : 'bg-border hover:bg-primary/40'
                ]"
                title="Geser untuk atur lebar keranjang · klik dua kali untuk reset"
            >
                <!-- Larger invisible hit area for easier grabbing -->
                <span class="absolute inset-y-0 -left-1.5 -right-1.5"></span>
                <!-- Grip dots -->
                <span class="relative flex flex-col gap-1 pointer-events-none">
                    <span :class="['w-0.5 h-0.5 rounded-full', isResizingCart ? 'bg-white' : 'bg-gray-400 group-hover:bg-primary']"></span>
                    <span :class="['w-0.5 h-0.5 rounded-full', isResizingCart ? 'bg-white' : 'bg-gray-400 group-hover:bg-primary']"></span>
                    <span :class="['w-0.5 h-0.5 rounded-full', isResizingCart ? 'bg-white' : 'bg-gray-400 group-hover:bg-primary']"></span>
                </span>
            </div>

            <!-- RIGHT: Cart Panel -->
            <div
                :style="{ width: cartWidth + 'px' }"
                class="bg-card border-l border-border flex flex-col shrink-0">
                <!-- Cart Header -->
                <div class="px-4 py-3 border-b border-border flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-semibold text-gray-800">Keranjang</h2>
                        <span v-if="cartItemCount > 0" class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-primary/10 text-primary text-[10px] font-bold">
                            {{ cartItemCount }}
                        </span>
                    </div>
                    <Transition
                        enter-active-class="transition-all duration-150 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition-all duration-100 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                        mode="out-in"
                    >
                        <div v-if="confirmingClear" key="confirm" class="flex items-center gap-1.5">
                            <span class="text-xs text-muted-foreground">Hapus semua?</span>
                            <button @click="clearCart" class="text-xs font-semibold text-destructive hover:text-destructive/70 transition">Ya</button>
                            <span class="text-muted-foreground/40 text-[10px] select-none">·</span>
                            <button @click="cancelClearCart" class="text-xs text-muted-foreground hover:text-foreground transition">Batal</button>
                        </div>
                        <button
                            v-else-if="cart.length > 0"
                            key="trigger"
                            @click="requestClearCart"
                            class="text-xs text-muted-foreground hover:text-destructive transition"
                        >
                            Kosongkan
                        </button>
                    </Transition>
                </div>

                <!-- Cart Items -->
                <div class="flex-1 overflow-y-auto p-3 space-y-2">
                    <template v-if="cart.length > 0">
                        <div v-for="(item, idx) in cart" :key="`${item.variant_id}-${idx}`">
                            <CartItem
                                :item="item"
                                :index="idx"
                                @update-qty="updateCartQty"
                                @update-notes="updateCartNotes"
                                @remove="removeCartItem"
                            />

                            <!-- Harga khusus ([BL-018]). Hanya owner yang punya
                                 jalan ke bawah lantai untung, dan alasannya
                                 wajib. -->
                            <div v-if="isOwner" class="px-3 pb-2 -mt-1">
                                <button
                                    v-if="!item.override_unit_price"
                                    type="button"
                                    class="text-[11px] font-medium text-primary hover:text-primary/80"
                                    @click="openOverride(idx)"
                                >
                                    Harga khusus
                                </button>
                                <div v-else class="flex items-center gap-2 text-[11px]">
                                    <span class="text-amber-700">
                                        Harga khusus — “{{ item.discount_reason }}”
                                    </span>
                                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="clearOverride(idx)">
                                        batalkan
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                    <div v-else class="flex flex-col items-center justify-center h-full text-gray-300">
                        <svg class="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                        </svg>
                        <p class="text-sm">Keranjang kosong</p>
                        <p class="text-xs mt-1">Pilih produk untuk memulai</p>
                    </div>
                </div>

                <!-- Cart Footer -->
                <div class="border-t border-border p-4 space-y-3 flex-shrink-0">
                    <!-- Saran jual: strip tipis, bukan pop-up (lihat UpsellStrip.vue) -->
                    <UpsellStrip
                        :suggestions="upsellSuggestions"
                        :accepted="upsellAccepted"
                        :disabled="processing"
                        :mandatory="upsellMandatory"
                        @accept="applyUpsell"
                        @reject="rejectUpsell"
                        @retract="undoUpsell"
                    />

                    <!-- Sebab tombol bayar mati, bukan sekadar tombol kelabu -->
                    <div
                        v-if="checkoutBlockedReason && cart.length > 0"
                        class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2"
                        role="status"
                    >
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                        </svg>
                        <span class="text-xs text-amber-800">{{ checkoutBlockedReason }}</span>
                    </div>

                    <!-- Batasnya ditunjukkan apa adanya, bukan dibiarkan jadi
                         jalan buntu ([BL-018]). Kasir yang menemukan barang
                         hampir kedaluwarsa saat owner tidak di tempat perlu
                         tahu apa yang bisa dan tidak bisa ia lakukan. -->
                    <p v-if="!isOwner && cart.length > 0" class="text-[11px] text-gray-400">
                        Diskon yang sudah disetujui pemilik berlaku otomatis. Harga di bawah batas untung hanya bisa ditetapkan pemilik.
                    </p>

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Total</span>
                        <span class="text-xl font-bold text-gray-800">{{ formatCurrency(cartTotal) }}</span>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="saveAsOpenBill"
                            :disabled="cart.length === 0 || processing"
                            class="flex-1 py-3 bg-amber-500 text-white font-semibold rounded-lg hover:bg-amber-600 transition disabled:opacity-40 disabled:cursor-not-allowed text-sm"
                            title="Simpan pesanan tanpa bayar — tagihan berlaku 24 jam"
                        >
                            Tunda Bayar
                        </button>
                        <button
                            @click="openPaymentModal"
                            :disabled="!canCheckout"
                            :title="checkoutBlockedReason || undefined"
                            class="flex-[2] py-3 bg-success text-success-foreground font-semibold rounded-lg hover:bg-success/90 transition disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            {{ processing ? 'Memproses...' : 'BAYAR' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <ModifierModal
            :show="showModifierModal"
            :product="selectedProduct"
            @close="showModifierModal = false"
            @confirm="addToCart"
        />

        <!-- Harga khusus ([BL-018]) -->
        <Teleport to="body">
            <div v-if="overrideTarget !== null" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="closeOverride" />
                <div class="relative bg-white rounded-xl shadow-2xl max-w-sm w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900">Harga Khusus</h3>
                    <p class="mt-1 text-xs text-gray-500 leading-relaxed">
                        Berlaku untuk penjualan ini saja. Harga dan alasannya ikut tercatat pada barisnya, dan muncul terpisah di laporan bila di bawah batas untung.
                    </p>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Harga per item</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">Rp</span>
                                <input
                                    v-model="overridePrice"
                                    type="number"
                                    min="0"
                                    step="500"
                                    class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Alasan *</label>
                            <input
                                v-model="overrideReason"
                                type="text"
                                maxlength="200"
                                placeholder="Contoh: kemasan rusak, daripada dibuang"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                            />
                            <p class="mt-1 text-xs text-gray-500">Wajib — tanpa alasan, penjualannya ditolak server.</p>
                        </div>
                    </div>

                    <div class="mt-5 flex gap-3">
                        <button type="button" class="flex-1 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50" @click="closeOverride">
                            Batal
                        </button>
                        <button
                            type="button"
                            :disabled="!overrideValid"
                            class="flex-1 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 disabled:opacity-40"
                            @click="applyOverride"
                        >
                            Terapkan
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <PaymentModal
            :show="showPaymentModal"
            :total-amount="cartTotal"
            :payment-methods="availablePaymentMethods"
            :proof-required="paymentProofEnabled"
            @close="showPaymentModal = false"
            @confirm="handlePayment"
        />

        <TransactionSuccessModal
            :show="showSuccessModal"
            :transaction="lastTransaction"
            @close="closeSuccessModal"
            @print="printFromSuccess"
        />

        <ReceiptModal
            :show="showReceiptModal"
            :transaction="lastTransaction"
            :tenant-name="tenantName"
            @close="showReceiptModal = false; lastTransaction = null"
        />

        <!--
            Satu modal identitas untuk kedua jalur. Dulu modal nama hanya ada di
            jalur "Tunda Bayar", sehingga pesanan bayar-langsung — yang justru
            perlu dipanggil saat siap — tidak bisa diberi identitas ([BL-026]).
        -->
        <OrderIdentityModal
            :show="pendingIdentityFor !== null"
            :mode="identityForm"
            :confirm-label="pendingIdentityFor === 'open_bill' ? 'Simpan Tagihan' : 'Lanjut Bayar'"
            :disabled="processing"
            @close="cancelIdentity"
            @confirm="confirmIdentity"
        />
    </div>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
