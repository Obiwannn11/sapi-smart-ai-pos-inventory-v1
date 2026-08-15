<script setup>
import { ref, computed, watch } from 'vue';
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import SkeletonTable from '@/Components/Skeleton/SkeletonTable.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    // Ditunda ([BL-037]) — null selama daftar mutasinya masih dimuat.
    movements: { type: Object, default: null },
    products: Array,
    filters: Object,
});

// --- Filters ---
const filterType = ref(props.filters?.type || '');
const filterDateFrom = ref(props.filters?.date_from || '');
const filterDateTo = ref(props.filters?.date_to || '');
const filterProduct = ref(props.filters?.product_id || '');

const typeOptions = [
    { value: '', label: 'Semua Tipe' },
    { value: 'sale', label: 'Penjualan' },
    { value: 'restock', label: 'Restock' },
    { value: 'adjustment', label: 'Adjustment' },
];

const productOptions = computed(() => [
    { value: '', label: 'Semua Produk' },
    ...props.products.map(product => ({ value: product.id, label: product.name })),
]);

const applyFilters = () => {
    const params = {};
    if (filterType.value) params.type = filterType.value;
    if (filterDateFrom.value) params.date_from = filterDateFrom.value;
    if (filterDateTo.value) params.date_to = filterDateTo.value;
    if (filterProduct.value) params.product_id = filterProduct.value;

    router.get('/owner/stock/movements', params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const clearFilters = () => {
    filterType.value = '';
    filterDateFrom.value = '';
    filterDateTo.value = '';
    filterProduct.value = '';
    router.get('/owner/stock/movements', {}, {
        preserveState: true,
        preserveScroll: true,
    });
};

const hasFilters = () => {
    return filterType.value || filterDateFrom.value || filterDateTo.value || filterProduct.value;
};

// --- Helpers ---
const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

const typeLabel = (type) => {
    const labels = {
        sale: 'Penjualan',
        restock: 'Restock',
        adjustment: 'Adjustment',
    };
    return labels[type] || type;
};

const typeBadgeClass = (type) => {
    const classes = {
        sale: 'bg-primary/10 text-primary',
        restock: 'bg-success/10 text-success',
        adjustment: 'bg-warning/10 text-warning-foreground',
    };
    return classes[type] || 'bg-gray-100 text-gray-800';
};

const formatQty = (qty) => {
    return qty > 0 ? `+${qty}` : `${qty}`;
};

const qtyClass = (qty) => {
    return qty > 0 ? 'text-success' : 'text-destructive';
};
</script>

<template>
    <Head title="Riwayat Pergerakan Stok" />

    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <Link
                href="/owner/stock"
                class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-700 mb-3"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Kembali ke Stok
            </Link>
            <h1 class="text-2xl font-bold text-gray-900">Semua Pergerakan Stok</h1>
            <p class="text-sm text-gray-500 mt-1">Riwayat seluruh pergerakan stok (penjualan, restock, adjustment)</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
            <div class="flex flex-wrap items-end gap-3">
                <!-- Type filter -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Tipe</label>
                    <SelectDropdown
                        v-model="filterType"
                        :options="typeOptions"
                        class="w-44"
                    />
                </div>

                <!-- Product filter -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Produk</label>
                    <SelectDropdown
                        v-model="filterProduct"
                        :options="productOptions"
                        searchable
                        class="w-52"
                    />
                </div>

                <!-- Date from -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Dari Tanggal</label>
                    <input
                        v-model="filterDateFrom"
                        type="date"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <!-- Date to -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Sampai Tanggal</label>
                    <input
                        v-model="filterDateTo"
                        type="date"
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>

                <!-- Buttons -->
                <div class="flex gap-2">
                    <button
                        @click="applyFilters"
                        class="px-4 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors"
                    >
                        Filter
                    </button>
                    <button
                        v-if="hasFilters()"
                        @click="clearFilters"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Movements Table. Ditunda ([BL-037]): filternya sudah bisa dipakai,
             dan kerangka ini muncul lagi setiap filter diubah. -->
        <Deferred data="movements">
            <template #fallback>
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <SkeletonTable :rows="8" :columns="6" label="Memuat riwayat pergerakan stok…" />
                </div>
            </template>

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table v-if="movements.data.length > 0" class="w-full">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Waktu</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Produk</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Varian</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipe</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Catatan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="movement in movements.data" :key="movement.id" class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-sm text-gray-600 whitespace-nowrap">{{ formatDate(movement.created_at) }}</td>
                        <td class="px-5 py-3 text-sm text-gray-900">{{ movement.variant?.product?.name || '-' }}</td>
                        <td class="px-5 py-3 text-sm text-gray-600">{{ movement.variant?.name || '-' }}</td>
                        <td class="px-5 py-3 text-center">
                            <span :class="['inline-flex items-center px-2 py-0.5 rounded text-xs font-medium', typeBadgeClass(movement.type)]">
                                {{ typeLabel(movement.type) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <span class="text-sm font-semibold" :class="qtyClass(movement.qty)">
                                {{ formatQty(movement.qty) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-sm text-gray-600 max-w-xs truncate">{{ movement.notes || '-' }}</td>
                    </tr>
                </tbody>
            </table>

            <!-- Empty state -->
            <div v-else class="py-16 text-center">
                <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p class="mt-3 text-sm text-gray-500">
                    {{ hasFilters() ? 'Tidak ada data yang cocok dengan filter' : 'Belum ada riwayat pergerakan stok' }}
                </p>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="movements.links && movements.last_page > 1" class="mt-4 flex items-center justify-between">
            <p class="text-sm text-gray-500">
                Menampilkan {{ movements.from }}–{{ movements.to }} dari {{ movements.total }} data
            </p>
            <div class="flex gap-1">
                <Link
                    v-for="link in movements.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    :class="[
                        'px-3 py-1.5 text-sm rounded-lg border transition-colors',
                        link.active
                            ? 'bg-primary text-primary-foreground border-primary'
                            : link.url
                                ? 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed'
                    ]"
                    v-html="link.label"
                    preserve-scroll
                />
            </div>
        </div>
        </Deferred>
    </div>
</template>
