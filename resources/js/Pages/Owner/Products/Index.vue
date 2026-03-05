<script setup>
import { ref, computed } from 'vue';
import { useForm, Head, Link, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    products: Array,
    categories: Array,
});

// --- Filters ---
const filterCategory = ref('');
const filterStatus = ref('');

const filteredProducts = computed(() => {
    let items = props.products;
    if (filterCategory.value) {
        items = items.filter(p =>
            filterCategory.value === 'none'
                ? !p.category_id
                : p.category_id == filterCategory.value
        );
    }
    if (filterStatus.value !== '') {
        items = items.filter(p => p.is_active === (filterStatus.value === 'active'));
    }
    return items;
});

// --- Delete ---
const deleteTarget = ref(null);
const deleteForm = useForm({});

const confirmDelete = (product) => {
    deleteTarget.value = product;
};

const doDelete = () => {
    if (!deleteTarget.value) return;
    deleteForm.delete(`/owner/products/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null; },
    });
};

const cancelDelete = () => {
    deleteTarget.value = null;
};

// Helpers
const totalStock = (variants) => {
    if (!variants) return 0;
    return variants.reduce((sum, v) => sum + (v.stock || 0), 0);
};

const lowestPrice = (variants) => {
    if (!variants || variants.length === 0) return 0;
    return Math.min(...variants.map(v => Number(v.price)));
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);
};
</script>

<template>
    <Head title="Produk" />

    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Produk</h1>
                <p class="text-sm text-gray-500 mt-1">{{ filteredProducts.length }} produk ditemukan</p>
            </div>
            <Link
                href="/owner/products/create"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Produk
            </Link>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 mb-6">
            <select
                v-model="filterCategory"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">Semua Kategori</option>
                <option value="none">Tanpa Kategori</option>
                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
            <select
                v-model="filterStatus"
                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            >
                <option value="">Semua Status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </select>
        </div>

        <!-- Product Grid -->
        <div v-if="filteredProducts.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div
                v-for="product in filteredProducts"
                :key="product.id"
                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow"
            >
                <!-- Image -->
                <div class="aspect-square bg-gray-100 relative">
                    <img
                        v-if="product.image_url"
                        :src="product.image_url"
                        :alt="product.name"
                        class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center">
                        <svg class="w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <!-- Status badge -->
                    <span
                        :class="[
                            'absolute top-2 right-2 px-2 py-0.5 rounded text-xs font-medium',
                            product.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'
                        ]"
                    >
                        {{ product.is_active ? 'Aktif' : 'Nonaktif' }}
                    </span>
                </div>

                <!-- Info -->
                <div class="p-4">
                    <h3 class="text-sm font-semibold text-gray-900 truncate">{{ product.name }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ product.category?.name || 'Tanpa Kategori' }}</p>

                    <div class="mt-3 flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-400">Mulai dari</p>
                            <p class="text-sm font-bold text-indigo-600">{{ formatCurrency(lowestPrice(product.variants)) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-400">Stok</p>
                            <p class="text-sm font-semibold" :class="totalStock(product.variants) <= 5 ? 'text-red-600' : 'text-gray-800'">
                                {{ totalStock(product.variants) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-2">
                        <span class="text-xs text-gray-400">{{ product.variants?.length || 0 }} varian</span>
                    </div>

                    <!-- Actions -->
                    <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between">
                        <Link
                            :href="`/owner/products/${product.id}/edit`"
                            class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                        >
                            Edit
                        </Link>
                        <button
                            @click="confirmDelete(product)"
                            class="text-sm text-red-600 hover:text-red-800 font-medium"
                        >
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="bg-white rounded-lg shadow-sm border border-gray-200 py-16 text-center">
            <svg class="mx-auto w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <p class="mt-3 text-sm text-gray-500">Belum ada produk</p>
            <Link
                href="/owner/products/create"
                class="mt-2 inline-block text-sm text-indigo-600 hover:text-indigo-800 font-medium"
            >
                Tambah produk pertama
            </Link>
        </div>
    </div>

    <!-- Delete confirm -->
    <ConfirmDialog
        :show="!!deleteTarget"
        title="Hapus Produk"
        :message="`Apakah Anda yakin ingin menghapus produk '${deleteTarget?.name}'? Semua varian produk juga akan dihapus.`"
        confirmText="Hapus"
        @confirm="doDelete"
        @cancel="cancelDelete"
    />
</template>
