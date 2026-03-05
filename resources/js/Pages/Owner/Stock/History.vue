<script setup>
import { Head, Link } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    variant: Object,
    movements: Object,
});

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
        sale: 'bg-blue-100 text-blue-800',
        restock: 'bg-green-100 text-green-800',
        adjustment: 'bg-yellow-100 text-yellow-800',
    };
    return classes[type] || 'bg-gray-100 text-gray-800';
};

const formatQty = (qty) => {
    return qty > 0 ? `+${qty}` : `${qty}`;
};

const qtyClass = (qty) => {
    return qty > 0 ? 'text-green-600' : 'text-red-600';
};
</script>

<template>
    <Head :title="`Riwayat Stok — ${variant.product?.name} (${variant.name})`" />

    <div class="max-w-5xl mx-auto">
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
            <h1 class="text-2xl font-bold text-gray-900">Riwayat Stok</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ variant.product?.name }} — <span class="font-medium text-gray-700">{{ variant.name }}</span>
                &middot; Stok saat ini: <span class="font-semibold" :class="variant.stock <= 5 ? 'text-red-600' : 'text-gray-900'">{{ variant.stock }}</span>
            </p>
        </div>

        <!-- Movements Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table v-if="movements.data.length > 0" class="w-full">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Waktu</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipe</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Qty</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Catatan</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Referensi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <tr v-for="movement in movements.data" :key="movement.id" class="hover:bg-gray-50">
                        <td class="px-5 py-3 text-sm text-gray-600">{{ formatDate(movement.created_at) }}</td>
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
                        <td class="px-5 py-3 text-center text-sm text-gray-500">
                            {{ movement.reference_id ? `#${movement.reference_id}` : '-' }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Empty state -->
            <div v-else class="py-16 text-center">
                <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p class="mt-3 text-sm text-gray-500">Belum ada riwayat pergerakan stok</p>
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
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : link.url
                                ? 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed'
                    ]"
                    v-html="link.label"
                    preserve-scroll
                />
            </div>
        </div>
    </div>
</template>
