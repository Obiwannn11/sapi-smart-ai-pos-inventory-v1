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

// --- Helpers ---
const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
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
    // Jika produk punya 1 variant tanpa modifier → langsung tambah ke cart
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
        });
    } else {
        // Buka ModifierModal untuk pilih variant + modifiers
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
    // Cek apakah item sama sudah ada di cart (sama variant + sama modifiers)
    const existingIdx = cart.value.findIndex(c =>
        c.variant_id === item.variant_id &&
        JSON.stringify(c.modifiers.map(m => m.id).sort()) === JSON.stringify((item.modifiers || []).map(m => m.id).sort())
    );

    // Cek stok tersedia
    const stock = getVariantStock(item.variant_id);
    const currentCartQty = getCartQtyForVariant(item.variant_id);
    if (currentCartQty + item.qty > stock) {
        alert(`Stok tidak cukup. Tersedia: ${stock}, di keranjang: ${currentCartQty}`);
        return;
    }

    if (existingIdx >= 0) {
        cart.value[existingIdx].qty += item.qty;
    } else {
        cart.value.push({ ...item });
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
        })),
        payments: payments,
        notes: null,
    };

    router.post('/cashier/transactions', data, {
        preserveScroll: true,
        onSuccess: (page) => {
            showPaymentModal.value = false;
            // Ambil lastTransaction dari flash
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

// --- Navigation ---
const goToCashDrawer = () => {
    router.get('/cashier/cash-drawer');
};

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="h-screen flex flex-col bg-gray-100 overflow-hidden">
        <FlashMessage />

        <!-- Top Bar -->
        <nav class="bg-white shadow-sm px-4 py-3 flex items-center justify-between flex-shrink-0 z-10">
            <div class="flex items-center gap-3">
                <h1 class="text-lg font-bold text-indigo-600">SAPI POS</h1>
                <span class="text-xs text-gray-400 hidden sm:inline">|</span>
                <span class="text-xs text-gray-500 hidden sm:inline">{{ auth.user.name }}</span>
            </div>
            <div class="flex items-center gap-3">
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
                <div class="p-4 pb-2 flex-shrink-0 space-y-3">
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
            <div class="w-80 lg:w-96 bg-white border-l border-gray-200 flex flex-col flex-shrink-0">
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
                    <template v-if="cart.length > 0">
                        <CartItem
                            v-for="(item, idx) in cart"
                            :key="`${item.variant_id}-${idx}`"
                            :item="item"
                            :index="idx"
                            @update-qty="updateCartQty"
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
                    <button
                        @click="openPaymentModal"
                        :disabled="cart.length === 0 || processing"
                        class="w-full py-3 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        {{ processing ? 'Memproses...' : 'BAYAR' }}
                    </button>
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

        <ReceiptModal
            :show="showReceiptModal"
            :transaction="lastTransaction"
            @close="showReceiptModal = false"
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
