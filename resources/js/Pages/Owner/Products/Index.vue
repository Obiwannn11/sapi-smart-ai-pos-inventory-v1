<script setup>
import { ref, computed } from 'vue';
import { Deferred, useForm, Head, Link } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import ProductImage from '@/Components/ProductImage.vue';
import SkeletonGrid from '@/Components/Skeleton/SkeletonGrid.vue';
import SkeletonCard from '@/Components/Skeleton/SkeletonCard.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    // Ditunda ([BL-037]) — null selama katalognya masih dalam perjalanan.
    products: { type: Array, default: null },
    categories: Array,
    // Keadaan awal penyaring, dibaca dari query string oleh controller
    // ([BL-100] tahap 1). Bawaannya sengaja objek utuh: halaman ini juga
    // dirender oleh tautan lama yang belum membawa `filters` sama sekali.
    filters: { type: Object, default: () => ({ q: '' }) },
});

// --- Filters ---
// Katalognya disaring di client, jadi URL hanya MENYALAKAN pencarian, tidak
// menjalankannya. Konsekuensinya disengaja: mengetik tidak memuat ulang apa
// pun, dan tautan `?q=Iced` dari luar tetap mendarat pada barang yang dimaksud.
const search = ref(props.filters?.q ?? '');
const filterCategory = ref('');
const filterStatus = ref('');

const categoryOptions = computed(() => [
    { value: '', label: 'Semua Kategori' },
    { value: 'none', label: 'Tanpa Kategori' },
    ...props.categories.map(cat => ({ value: cat.id, label: cat.name })),
]);

const statusOptions = [
    { value: '', label: 'Semua Status' },
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];

/**
 * Pencarian mencakup nama varian, bukan cuma nama produk.
 *
 * Itu bukan kelebihan yang kebetulan: nama yang dibawa owner ke halaman ini
 * sering justru nama varian ("Iced", "Large"), sementara kartunya berjudul
 * nama produk. Pencarian yang hanya membaca judul kartu akan menjawab "tidak
 * ada" untuk barang yang jelas-jelas ada.
 */
const matchesSearch = (product, needle) => {
    if (needle === '') return true;

    return [
        product.name,
        product.category?.name,
        ...(product.variants ?? []).map(v => v.name),
    ].some(text => (text ?? '').toLowerCase().includes(needle));
};

const filteredProducts = computed(() => {
    let items = props.products ?? [];
    const needle = search.value.trim().toLowerCase();

    if (needle !== '') {
        items = items.filter(p => matchesSearch(p, needle));
    }
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

const isFiltering = computed(
    () => search.value.trim() !== '' || filterCategory.value !== '' || filterStatus.value !== ''
);

const resetFilters = () => {
    search.value = '';
    filterCategory.value = '';
    filterStatus.value = '';
};

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
                <p v-if="products" class="text-sm text-gray-500 mt-1">{{ filteredProducts.length }} produk ditemukan</p>
                <p v-else class="text-sm text-gray-500 mt-1">Memuat produk…</p>
            </div>
            <Link
                href="/owner/products/create"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ring transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Produk
            </Link>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 mb-6">
            <div class="relative flex-1 min-w-[220px] max-w-md">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                </svg>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari produk, kategori, atau varian..."
                    class="w-full pl-9 pr-9 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                />
                <button
                    v-if="search"
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600"
                    aria-label="Hapus pencarian"
                    @click="search = ''"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <SelectDropdown
                v-model="filterCategory"
                :options="categoryOptions"
                searchable
                class="w-52"
            />
            <SelectDropdown
                v-model="filterStatus"
                :options="statusOptions"
                class="w-44"
            />
        </div>

        <!-- Product Grid. Ditunda ([BL-037]): kerangkanya memakai grid dan
             kartu bergambar yang sama supaya kartu tidak melompat saat
             katalognya sampai. -->
        <Deferred data="products">
            <template #fallback>
                <SkeletonGrid
                    :count="8"
                    columns="grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                    gap="gap-4"
                    label="Memuat daftar produk…"
                >
                    <SkeletonCard media :lines="2" footer padding="p-4" />
                </SkeletonGrid>
            </template>

        <div v-if="filteredProducts.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <div
                v-for="product in filteredProducts"
                :key="product.id"
                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow"
            >
                <!-- Image -->
                <div class="aspect-square bg-gray-100 relative">
                    <ProductImage :src="product.image_url" :name="product.name" />
                    <!-- Status badge -->
                    <span
                        :class="[
                            'absolute top-2 right-2 px-2 py-0.5 rounded text-xs font-medium',
                            product.is_active ? 'bg-success/10 text-success' : 'bg-muted text-muted-foreground'
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
                            <p class="text-sm font-bold text-primary">{{ formatCurrency(lowestPrice(product.variants)) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-gray-400">Stok</p>
                            <p class="text-sm font-semibold" :class="totalStock(product.variants) <= 5 ? 'text-destructive' : 'text-foreground'">
                                {{ totalStock(product.variants) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-2">
                        <span class="text-xs text-gray-400">{{ product.variants?.length || 0 }} varian</span>
                    </div>

                    <!-- Actions -->
                    <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-end gap-2">
                        <Link
                            :href="`/owner/products/${product.id}/edit`"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-primary bg-primary/10 border border-primary/20 rounded-lg hover:bg-primary/20 transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </Link>
                        <button
                            @click="confirmDelete(product)"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-destructive bg-destructive/10 border border-destructive/20 rounded-lg hover:bg-destructive/20 transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tidak ada yang cocok. Dipisahkan dari katalog kosong: menyuruh
             owner "tambah produk pertama" padahal katalognya penuh dan yang
             salah cuma kata kuncinya adalah jawaban yang menyesatkan. -->
        <div v-else-if="isFiltering" class="bg-white rounded-lg shadow-sm border border-gray-200 py-16 text-center">
            <svg class="mx-auto w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
            </svg>
            <p class="mt-3 text-sm text-gray-500">
                Tidak ada produk yang cocok<span v-if="search.trim()"> dengan “{{ search.trim() }}”</span>.
            </p>
            <button
                type="button"
                class="mt-2 text-sm text-primary hover:text-primary/80 font-medium"
                @click="resetFilters"
            >
                Hapus semua penyaring
            </button>
        </div>

        <!-- Empty state -->
        <div v-else class="bg-white rounded-lg shadow-sm border border-gray-200 py-16 text-center">
            <svg class="mx-auto w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <p class="mt-3 text-sm text-gray-500">Belum ada produk</p>
            <Link
                href="/owner/products/create"
                class="mt-2 inline-block text-sm text-primary hover:text-primary/80 font-medium"
            >
                Tambah produk pertama
            </Link>
        </div>
        </Deferred>
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
