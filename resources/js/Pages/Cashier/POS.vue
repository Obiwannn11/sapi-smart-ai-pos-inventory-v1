<script setup>
import { router, Head } from '@inertiajs/vue3';
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
import { useOnlineStatus } from '@/composables/useOnlineStatus';
import { useCatalogCache } from '@/composables/useCatalogCache';
import { useOfflineQueue } from '@/composables/useOfflineQueue';
import { useUpsell } from '@/composables/useUpsell';

const props = defineProps({
    categories: Array,
    products: Array,
    paymentMethods: Array,
    cashDrawer: Object,
    openBills: { type: Array, default: () => [] },
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
const { snapshot, loadSnapshot, saveSnapshot, cachedAtLabel } = useCatalogCache();

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
    if (isOnline.value) {
        saveSnapshot({
            products: props.products,
            categories: props.categories,
            paymentMethods: props.paymentMethods,
            upsell: props.upsell,
        });
    } else {
        loadSnapshot();
    }

    refreshQueue().then(() => {
        if (isOnline.value) flush();
    });
});

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

const showOpenBills = ref(false);
const selectedOpenBill = ref(null);
const showOpenBillPayment = ref(false);

// --- Open Bill Customer Name Modal ---
const showOpenBillNameModal = ref(false);
const openBillCustomerName = ref('');

// --- Helpers ---
const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatDate = (date) => {
    return new Date(date).toLocaleString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
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
            unit_price: Number(variant.price),
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
const {
    suggestions: upsellSuggestions,
    accept: acceptUpsell,
    dismiss: dismissUpsell,
    collectEvents: collectUpsellEvents,
    reset: resetUpsell,
} = useUpsell(catalogUpsell, cart, { getVariantStock, getCartQtyForVariant });

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

        line.variant_id = suggestion.suggested_variant_id;
        line.variant_name = suggestion.suggested_variant_name ?? suggestion.label;
        line.unit_price = Number(suggestion.suggested_variant_price ?? previousPrice);

        acceptUpsell(suggestion, (line.unit_price - previousPrice) * line.qty);

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
const openPaymentModal = () => {
    if (cart.value.length === 0) return;
    showPaymentModal.value = true;
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
}));

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

    openBillCustomerName.value = '';
    showOpenBillNameModal.value = true;
};

const confirmSaveOpenBill = () => {
    if (processing.value) return;
    processing.value = true;
    showOpenBillNameModal.value = false;

    const data = {
        items: cartToItems(),
        payments: null,
        notes: null,
        is_open_bill: true,
        customer_name: openBillCustomerName.value.trim() || null,
        client_uuid: getCheckoutUuid(),
        upsell_events: collectUpsellEvents(),
    };

    router.post('/cashier/transactions', data, {
        preserveScroll: true,
        onSuccess: () => {
            cart.value = [];
            resetUpsell();
            checkoutUuid.value = null;
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

const openBillPayment = (bill) => {
    // Settling an open bill mutates a row that already lives on the server —
    // there is no local copy to safely amend, and another till may be settling
    // the same bill. Same reasoning as saveAsOpenBill: refuse rather than risk
    // double-settling one order.
    if (!isOnline.value) {
        showFlash('Pembayaran tagihan terbuka butuh koneksi. Coba lagi saat online.', 'error');

        return;
    }

    selectedOpenBill.value = bill;
    showOpenBillPayment.value = true;
};

const handleOpenBillPayment = (payments) => {
    if (processing.value || !selectedOpenBill.value) return;
    processing.value = true;

    router.post(`/cashier/transactions/${selectedOpenBill.value.id}/pay`, {
        payments: payments,
    }, {
        preserveScroll: true,
        onSuccess: (page) => {
            showOpenBillPayment.value = false;
            selectedOpenBill.value = null;
            const txData = page.props.flash?.lastTransaction;
            if (txData) {
                lastTransaction.value = txData;
                showSuccessModal.value = true;
            }
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
                    <div v-if="filteredProducts.length > 0"
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
                    <!-- Open Bills Panel -->
                    <div v-if="openBills.length > 0" class="mb-2">
                        <button
                            @click="showOpenBills = !showOpenBills"
                            class="w-full flex items-center justify-between px-3 py-2 bg-amber-50 rounded-lg border border-amber-200 text-sm"
                        >
                            <span class="font-medium text-amber-700">Tagihan Terbuka ({{ openBills.length }})</span>
                            <svg
                                :class="['w-4 h-4 text-amber-600 transition-transform', showOpenBills ? 'rotate-180' : '']"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div v-if="showOpenBills" class="mt-2 space-y-2">
                            <div
                                v-for="bill in openBills"
                                :key="bill.id"
                                class="bg-amber-50 border border-amber-200 rounded-lg p-3"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-semibold text-amber-700">{{ bill.code }}</span>
                                    <span class="text-xs text-gray-400">{{ formatDate(bill.created_at) }}</span>
                                </div>
                                <p v-if="bill.customer_name" class="text-xs font-medium text-amber-800 mb-1">
                                    👤 {{ bill.customer_name }}
                                </p>
                                <div class="text-xs text-gray-600 space-y-0.5">
                                    <p v-for="item in bill.items" :key="item.id" class="truncate">
                                        {{ item.qty }}x {{ item.variant_name }}
                                        <span v-if="item.notes" class="text-amber-600 italic"> — {{ item.notes }}</span>
                                    </p>
                                </div>
                                <div class="flex items-center justify-between mt-2">
                                    <span class="text-sm font-semibold text-gray-800">{{ formatCurrency(bill.total_amount) }}</span>
                                    <button
                                        @click="openBillPayment(bill)"
                                        class="px-3 py-1 text-xs font-medium bg-success text-success-foreground rounded-md hover:bg-success/90 transition"
                                    >
                                        Bayar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <template v-if="cart.length > 0">
                        <CartItem
                            v-for="(item, idx) in cart"
                            :key="`${item.variant_id}-${idx}`"
                            :item="item"
                            :index="idx"
                            @update-qty="updateCartQty"
                            @update-notes="updateCartNotes"
                            @remove="removeCartItem"
                        />
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
                        :disabled="processing"
                        @accept="applyUpsell"
                        @dismiss="dismissUpsell"
                    />

                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">Total</span>
                        <span class="text-xl font-bold text-gray-800">{{ formatCurrency(cartTotal) }}</span>
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="saveAsOpenBill"
                            :disabled="cart.length === 0 || processing"
                            class="flex-1 py-3 bg-amber-500 text-white font-semibold rounded-lg hover:bg-amber-600 transition disabled:opacity-40 disabled:cursor-not-allowed text-sm"
                            title="Simpan pesanan tanpa bayar"
                        >
                            Tunda Bayar
                        </button>
                        <button
                            @click="openPaymentModal"
                            :disabled="cart.length === 0 || processing"
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

        <PaymentModal
            :show="showPaymentModal"
            :total-amount="cartTotal"
            :payment-methods="availablePaymentMethods"
            @close="showPaymentModal = false"
            @confirm="handlePayment"
        />

        <!-- Open Bill Payment Modal -->
        <PaymentModal
            :show="showOpenBillPayment"
            :total-amount="Number(selectedOpenBill?.total_amount || 0)"
            :payment-methods="availablePaymentMethods"
            @close="showOpenBillPayment = false; selectedOpenBill = null"
            @confirm="handleOpenBillPayment"
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

        <!-- Open Bill Customer Name Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-150"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showOpenBillNameModal" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="showOpenBillNameModal = false" />
                    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm p-6 space-y-4">
                        <h3 class="text-base font-semibold text-gray-800">Nama Pelanggan</h3>
                        <p class="text-sm text-gray-500">Nama pelanggan untuk tagihan ini (opsional).</p>
                        <input
                            v-model="openBillCustomerName"
                            type="text"
                            placeholder="Contoh: Meja 3 / Budi"
                            maxlength="100"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                            @keydown.enter="confirmSaveOpenBill"
                            @keydown.esc="showOpenBillNameModal = false"
                            autofocus
                        />
                        <div class="flex gap-3 pt-1">
                            <button
                                @click="showOpenBillNameModal = false"
                                class="flex-1 py-2.5 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition text-sm"
                            >
                                Batal
                            </button>
                            <button
                                @click="confirmSaveOpenBill"
                                class="flex-1 py-2.5 bg-amber-500 text-white font-semibold rounded-lg hover:bg-amber-600 transition text-sm"
                            >
                                Simpan Tagihan
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
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
