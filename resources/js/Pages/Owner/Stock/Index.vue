<script setup>
import { ref, computed } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    products: Array,
});

// --- Search & Filter ---
const search = ref('');

const filteredProducts = computed(() => {
    if (!search.value) return props.products;
    const q = search.value.toLowerCase();
    return props.products.filter(p =>
        p.name.toLowerCase().includes(q) ||
        p.variants?.some(v => v.name.toLowerCase().includes(q) || (v.sku && v.sku.toLowerCase().includes(q)))
    );
});

// --- Expanded rows ---
const expandedIds = ref(new Set());

const toggleExpand = (productId) => {
    if (expandedIds.value.has(productId)) {
        expandedIds.value.delete(productId);
    } else {
        expandedIds.value.add(productId);
    }
};

const isExpanded = (productId) => expandedIds.value.has(productId);

// Expand all by default
const expandAll = () => {
    props.products.forEach(p => expandedIds.value.add(p.id));
};
expandAll();

// --- Modals ---
const showRestockModal = ref(false);
const showAdjustModal = ref(false);
const selectedVariant = ref(null);

const restockForm = useForm({
    qty: '',
    notes: '',
    expiry_date: '',
});

const adjustForm = useForm({
    qty: '',
    notes: '',
});

const openRestock = (variant, product) => {
    selectedVariant.value = { ...variant, product_name: product.name };
    restockForm.reset();
    restockForm.clearErrors();
    showRestockModal.value = true;
};

const closeRestock = () => {
    showRestockModal.value = false;
    selectedVariant.value = null;
};

const submitRestock = () => {
    restockForm.post(`/owner/stock/${selectedVariant.value.id}/restock`, {
        preserveScroll: true,
        onSuccess: () => closeRestock(),
    });
};

const openAdjust = (variant, product) => {
    selectedVariant.value = { ...variant, product_name: product.name };
    adjustForm.reset();
    adjustForm.clearErrors();
    showAdjustModal.value = true;
};

const closeAdjust = () => {
    showAdjustModal.value = false;
    selectedVariant.value = null;
};

const submitAdjust = () => {
    adjustForm.post(`/owner/stock/${selectedVariant.value.id}/adjust`, {
        preserveScroll: true,
        onSuccess: () => closeAdjust(),
    });
};

// --- Helpers ---
const isLowStock = (stock) => stock > 0 && stock <= 5;
const isOutOfStock = (stock) => stock === 0;
const isNearExpiry = (expiryDate) => {
    if (!expiryDate) return false;
    const exp = new Date(expiryDate);
    const now = new Date();
    const diff = (exp - now) / (1000 * 60 * 60 * 24);
    return diff >= 0 && diff <= 7;
};
const isExpired = (expiryDate) => {
    if (!expiryDate) return false;
    return new Date(expiryDate) < new Date();
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>
    <Head title="Manajemen Stok" />

    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Manajemen Stok</h1>
                <p class="text-sm text-gray-500 mt-1">Restock, adjustment, dan pantau stok produk</p>
            </div>
            <Link
                href="/owner/stock/movements"
                class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                Semua Riwayat
            </Link>
        </div>

        <!-- Search -->
        <div class="mb-6">
            <input
                v-model="search"
                type="text"
                placeholder="Cari produk atau varian..."
                class="w-full max-w-md px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
            />
        </div>

        <!-- Product List -->
        <div v-if="filteredProducts.length > 0" class="space-y-3">
            <div
                v-for="product in filteredProducts"
                :key="product.id"
                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
            >
                <!-- Product Header (clickable to expand) -->
                <button
                    @click="toggleExpand(product.id)"
                    class="w-full flex items-center justify-between px-5 py-4 hover:bg-gray-50 transition-colors text-left"
                >
                    <div class="flex items-center gap-3">
                        <svg
                            class="w-4 h-4 text-gray-400 transition-transform"
                            :class="{ 'rotate-90': isExpanded(product.id) }"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900">{{ product.name }}</h3>
                            <p class="text-xs text-gray-500">{{ product.category?.name || 'Tanpa Kategori' }} &middot; {{ product.variants?.length || 0 }} varian</p>
                        </div>
                    </div>
                </button>

                <!-- Variants table (expanded) -->
                <Transition
                    enter-active-class="transition-all duration-200 ease-out"
                    enter-from-class="opacity-0 max-h-0"
                    enter-to-class="opacity-100 max-h-[1000px]"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-from-class="opacity-100 max-h-[1000px]"
                    leave-to-class="opacity-0 max-h-0"
                >
                    <div v-if="isExpanded(product.id)" class="border-t border-gray-200 overflow-hidden">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Varian</th>
                                    <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">SKU</th>
                                    <th class="px-5 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Stok</th>
                                    <th class="px-5 py-2.5 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Kedaluwarsa</th>
                                    <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr
                                    v-for="variant in product.variants"
                                    :key="variant.id"
                                    class="hover:bg-gray-50"
                                >
                                    <td class="px-5 py-3 text-sm text-gray-900">{{ variant.name }}</td>
                                    <td class="px-5 py-3 text-sm text-gray-500">{{ variant.sku || '-' }}</td>
                                    <td class="px-5 py-3 text-center">
                                        <span
                                            v-if="isOutOfStock(variant.stock)"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800"
                                        >
                                            Habis
                                        </span>
                                        <span
                                            v-else-if="isLowStock(variant.stock)"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800"
                                        >
                                            {{ variant.stock }} — Kritis
                                        </span>
                                        <span v-else class="text-sm font-semibold text-gray-800">
                                            {{ variant.stock }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span
                                            v-if="isExpired(variant.expiry_date)"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800"
                                        >
                                            Expired {{ formatDate(variant.expiry_date) }}
                                        </span>
                                        <span
                                            v-else-if="isNearExpiry(variant.expiry_date)"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800"
                                        >
                                            ⚠️ {{ formatDate(variant.expiry_date) }}
                                        </span>
                                        <span v-else class="text-sm text-gray-500">
                                            {{ formatDate(variant.expiry_date) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <!-- Restock -->
                                            <button
                                                @click="openRestock(variant, product)"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition-colors"
                                                title="Restock"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                                Restock
                                            </button>
                                            <!-- Adjustment -->
                                            <button
                                                @click="openAdjust(variant, product)"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg hover:bg-yellow-100 transition-colors"
                                                title="Adjustment"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                                </svg>
                                                Adjust
                                            </button>
                                            <!-- History -->
                                            <Link
                                                :href="`/owner/stock/${variant.id}/history`"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-gray-600 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors"
                                                title="Riwayat"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                                Riwayat
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Transition>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="bg-white rounded-lg shadow-sm border border-gray-200 py-16 text-center">
            <svg class="mx-auto w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
            </svg>
            <p class="mt-3 text-sm text-gray-500">
                {{ search ? 'Tidak ada produk yang cocok' : 'Belum ada produk' }}
            </p>
        </div>
    </div>

    <!-- Restock Modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showRestockModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="closeRestock" />
                <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Restock</h3>
                    <p class="text-sm text-gray-500 mb-5">
                        {{ selectedVariant?.product_name }} — {{ selectedVariant?.name }}
                        <span class="text-gray-400">(Stok saat ini: {{ selectedVariant?.stock }})</span>
                    </p>

                    <form @submit.prevent="submitRestock" class="space-y-4">
                        <!-- Qty -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Restock <span class="text-red-500">*</span></label>
                            <input
                                v-model="restockForm.qty"
                                type="number"
                                min="1"
                                placeholder="Masukkan jumlah"
                                autofocus
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="{ 'border-red-300': restockForm.errors.qty }"
                            />
                            <p v-if="restockForm.errors.qty" class="mt-1 text-xs text-red-600">{{ restockForm.errors.qty }}</p>
                        </div>

                        <!-- Expiry Date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Kedaluwarsa</label>
                            <input
                                v-model="restockForm.expiry_date"
                                type="date"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="{ 'border-red-300': restockForm.errors.expiry_date }"
                            />
                            <p v-if="restockForm.errors.expiry_date" class="mt-1 text-xs text-red-600">{{ restockForm.errors.expiry_date }}</p>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                            <textarea
                                v-model="restockForm.notes"
                                rows="2"
                                placeholder="Contoh: Restock dari supplier A"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                                :class="{ 'border-red-300': restockForm.errors.notes }"
                            />
                            <p v-if="restockForm.errors.notes" class="mt-1 text-xs text-red-600">{{ restockForm.errors.notes }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                @click="closeRestock"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="restockForm.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50 transition-colors"
                            >
                                {{ restockForm.processing ? 'Menyimpan...' : 'Restock' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Adjustment Modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showAdjustModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="closeAdjust" />
                <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Adjustment Stok</h3>
                    <p class="text-sm text-gray-500 mb-5">
                        {{ selectedVariant?.product_name }} — {{ selectedVariant?.name }}
                        <span class="text-gray-400">(Stok saat ini: {{ selectedVariant?.stock }})</span>
                    </p>

                    <form @submit.prevent="submitAdjust" class="space-y-4">
                        <!-- Qty -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Jumlah Adjustment <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="adjustForm.qty"
                                type="number"
                                placeholder="Positif (+) atau negatif (-)"
                                autofocus
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                                :class="{ 'border-red-300': adjustForm.errors.qty }"
                            />
                            <p v-if="adjustForm.errors.qty" class="mt-1 text-xs text-red-600">{{ adjustForm.errors.qty }}</p>
                            <p class="mt-1 text-xs text-gray-400">Masukkan angka positif untuk menambah, negatif untuk mengurangi</p>
                        </div>

                        <!-- Notes (wajib) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Alasan <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                v-model="adjustForm.notes"
                                rows="2"
                                placeholder="Contoh: Bahan expired dibuang, audit fisik, dll"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent resize-none"
                                :class="{ 'border-red-300': adjustForm.errors.notes }"
                            />
                            <p v-if="adjustForm.errors.notes" class="mt-1 text-xs text-red-600">{{ adjustForm.errors.notes }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                @click="closeAdjust"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="adjustForm.processing"
                                class="px-4 py-2 text-sm font-medium text-white bg-yellow-600 rounded-lg hover:bg-yellow-700 disabled:opacity-50 transition-colors"
                            >
                                {{ adjustForm.processing ? 'Menyimpan...' : 'Simpan Adjustment' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
