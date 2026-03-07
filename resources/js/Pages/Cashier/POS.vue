<script setup>
import { usePage, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import ProductCard from '@/Components/ProductCard.vue';
import CartItem from '@/Components/CartItem.vue';
import ModifierModal from '@/Components/ModifierModal.vue';
import PaymentModal from '@/Components/PaymentModal.vue';
import ReceiptModal from '@/Components/ReceiptModal.vue';

const props = defineProps({
    categories: Array,
    products: Array,
    paymentMethods: Array,
    cashDrawer: Object,
    openBills: { type: Array, default: () => [] },
    tenantName: { type: String, default: 'SAPI POS' },
});

const page = usePage();
const auth = page.props.auth;

// --- State ---
const selectedCategoryId = ref(null);
const searchQuery = ref('');
const cart = ref([]);
const showModifierModal = ref(false);
const selectedProduct = ref(null);
const showPaymentModal = ref(false);
const showReceiptModal = ref(false);
const lastTransaction = ref(null);
const processing = ref(false);
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
    let list = props.products || [];

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
    for (const product of (props.products || [])) {
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
        alert(`Stok tidak cukup. Tersedia: ${stock}, di keranjang: ${currentCartQty}`);
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
        alert(`Stok tidak cukup. Tersedia: ${stock}`);
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

const clearCart = () => {
    cart.value = [];
};

// --- Checkout ---
const openPaymentModal = () => {
    if (cart.value.length === 0) return;
    showPaymentModal.value = true;
};

const handlePayment = (payments) => {
    if (processing.value) return;
    processing.value = true;

    const data = {
        items: cart.value.map(item => ({
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
        })),
        payments: payments,
        notes: null,
    };

    router.post('/cashier/transactions', data, {
        preserveScroll: true,
        onSuccess: (page) => {
            showPaymentModal.value = false;
            const txData = page.props.flash?.lastTransaction;
            if (txData) {
                lastTransaction.value = txData;
                showReceiptModal.value = true;
            }
            cart.value = [];
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

// --- Open Bill ---
const saveAsOpenBill = () => {
    if (cart.value.length === 0 || processing.value) return;
    openBillCustomerName.value = '';
    showOpenBillNameModal.value = true;
};

const confirmSaveOpenBill = () => {
    if (processing.value) return;
    processing.value = true;
    showOpenBillNameModal.value = false;

    const data = {
        items: cart.value.map(item => ({
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
        })),
        payments: null,
        notes: null,
        is_open_bill: true,
        customer_name: openBillCustomerName.value.trim() || null,
    };

    router.post('/cashier/transactions', data, {
        preserveScroll: true,
        onSuccess: () => {
            cart.value = [];
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

const openBillPayment = (bill) => {
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
                showReceiptModal.value = true;
            }
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};

// --- Navigation ---
const goToCashDrawer = () => {
    router.get('/cashier/cash-drawer');
};

const goToHistory = () => {
    router.get('/cashier/transactions');
};

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="h-screen flex flex-col bg-gray-100 overflow-hidden">
        <FlashMessage />

        <!-- Top Bar -->
        <nav class="bg-white shadow-sm px-4 py-3 flex items-center justify-between shrink-0 z-10">
            <div class="flex items-center gap-3">
                <h1 class="text-lg font-bold text-indigo-600">SAPI POS</h1>
                <span class="text-xs text-gray-400 hidden sm:inline">|</span>
                <span class="text-xs text-gray-500 hidden sm:inline">{{ auth.user.name }}</span>
            </div>
            <div class="flex items-center gap-3">
                <button
                    @click="goToHistory"
                    class="text-sm text-gray-600 hover:text-indigo-600 flex items-center gap-1.5 transition"
                    title="Riwayat Transaksi"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                    <span class="hidden sm:inline">Riwayat</span>
                </button>
                <button
                    @click="goToCashDrawer"
                    class="text-sm text-gray-600 hover:text-indigo-600 flex items-center gap-1.5 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    <span class="hidden sm:inline">Kas</span>
                </button>
                <button @click="logout" class="text-sm text-red-500 hover:text-red-700 transition">Logout</button>
            </div>
        </nav>

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
                            class="w-full pl-10 pr-4 py-2.5 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>

                    <!-- Category Tabs -->
                    <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-hide">
                        <button
                            @click="selectedCategoryId = null"
                            :class="[
                                'px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition',
                                selectedCategoryId === null
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-gray-600 border border-gray-200 hover:border-indigo-300'
                            ]"
                        >
                            Semua
                        </button>
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            @click="selectedCategoryId = cat.id"
                            :class="[
                                'px-3 py-1.5 rounded-full text-xs font-medium whitespace-nowrap transition',
                                selectedCategoryId === cat.id
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-gray-600 border border-gray-200 hover:border-indigo-300'
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

            <!-- RIGHT: Cart Panel -->
            <div class="w-80 lg:w-96 bg-white border-l border-gray-200 flex flex-col shrink-0">
                <!-- Cart Header -->
                <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-semibold text-gray-800">Keranjang</h2>
                        <span v-if="cartItemCount > 0" class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-bold">
                            {{ cartItemCount }}
                        </span>
                    </div>
                    <button
                        v-if="cart.length > 0"
                        @click="clearCart"
                        class="text-xs text-red-500 hover:text-red-700 transition"
                    >
                        Kosongkan
                    </button>
                </div>

                <!-- Cart Items -->
                <div class="flex-1 overflow-y-auto p-3 space-y-2">
                    <!-- Open Bills Panel -->
                    <div v-if="openBills.length > 0" class="mb-2">
                        <button
                            @click="showOpenBills = !showOpenBills"
                            class="w-full flex items-center justify-between px-3 py-2 bg-amber-50 rounded-lg border border-amber-200 text-sm"
                        >
                            <span class="font-medium text-amber-700">Open Bill ({{ openBills.length }})</span>
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
                                        class="px-3 py-1 text-xs font-medium bg-green-600 text-white rounded-md hover:bg-green-700 transition"
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
                <div class="border-t border-gray-200 p-4 space-y-3 flex-shrink-0">
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
                            Open Bill
                        </button>
                        <button
                            @click="openPaymentModal"
                            :disabled="cart.length === 0 || processing"
                            class="flex-[2] py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
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
            :payment-methods="paymentMethods"
            @close="showPaymentModal = false"
            @confirm="handlePayment"
        />

        <!-- Open Bill Payment Modal -->
        <PaymentModal
            :show="showOpenBillPayment"
            :total-amount="Number(selectedOpenBill?.total_amount || 0)"
            :payment-methods="paymentMethods"
            @close="showOpenBillPayment = false; selectedOpenBill = null"
            @confirm="handleOpenBillPayment"
        />

        <ReceiptModal
            :show="showReceiptModal"
            :transaction="lastTransaction"
            :tenant-name="tenantName"
            @close="showReceiptModal = false"
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
                        <p class="text-sm text-gray-500">Masukkan nama pelanggan untuk open bill ini (opsional).</p>
                        <input
                            v-model="openBillCustomerName"
                            type="text"
                            placeholder="Contoh: Meja 3 / Budi"
                            maxlength="100"
                            class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
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
                                Simpan Open Bill
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
