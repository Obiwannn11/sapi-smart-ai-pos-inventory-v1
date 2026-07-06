<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    transactions: Object, // paginated
    filters: Object,
});

const statusOptions = [
    { value: '', label: 'Semua' },
    { value: 'completed', label: 'Selesai' },
    { value: 'voided', label: 'Void' },
    { value: 'pending', label: 'Pending' },
];

const filterStatus = ref(props.filters?.status || '');
const filterFrom = ref(props.filters?.from || '');
const filterTo = ref(props.filters?.to || '');

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatDateTime = (datetime) => {
    return new Date(datetime).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const applyFilters = () => {
    const params = {};
    if (filterStatus.value) params.status = filterStatus.value;
    if (filterFrom.value) params.from = filterFrom.value;
    if (filterTo.value) params.to = filterTo.value;

    router.get('/owner/transactions', params, {
        preserveState: true,
        preserveScroll: true,
    });
};

const resetFilters = () => {
    filterStatus.value = '';
    filterFrom.value = '';
    filterTo.value = '';
    router.get('/owner/transactions', {}, {
        preserveState: true,
    });
};

const statusBadge = (status) => {
    switch (status) {
        case 'completed': return 'bg-success/10 text-success';
        case 'voided': return 'bg-destructive/10 text-destructive';
        case 'pending': return 'bg-warning/10 text-warning-foreground';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const statusLabel = (status) => {
    switch (status) {
        case 'completed': return 'Selesai';
        case 'voided': return 'Void';
        case 'pending': return 'Pending';
        default: return status;
    }
};
</script>

<template>
    <Head title="Riwayat Transaksi" />

    <div class="max-w-6xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Riwayat Transaksi</h1>
            <p class="text-sm text-gray-500 mt-1">Semua transaksi bisnis Anda</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z" />
                </svg>
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Filter</span>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <div class="min-w-[160px]">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                    <SelectDropdown
                        v-model="filterStatus"
                        :options="statusOptions"
                    />
                </div>
                <div class="min-w-[160px]">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Dari</label>
                    <input
                        v-model="filterFrom"
                        type="date"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>
                <div class="min-w-[160px]">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Sampai</label>
                    <input
                        v-model="filterTo"
                        type="date"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                </div>
                <div class="flex gap-2 items-end">
                    <button
                        @click="applyFilters"
                        class="px-4 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors"
                    >
                        Filter
                    </button>
                    <button
                        @click="resetFilters"
                        class="px-4 py-2 bg-gray-100 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-200 transition-colors"
                    >
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Transaction Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left py-3 px-4 text-gray-500 font-medium">Kode</th>
                            <th class="text-left py-3 px-4 text-gray-500 font-medium">Status</th>
                            <th class="text-right py-3 px-4 text-gray-500 font-medium">Total</th>
                            <th class="text-left py-3 px-4 text-gray-500 font-medium">Kasir</th>
                            <th class="text-left py-3 px-4 text-gray-500 font-medium">Waktu</th>
                            <th class="text-center py-3 px-4 text-gray-500 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="tx in transactions.data"
                            :key="tx.id"
                            class="border-b border-gray-50 hover:bg-gray-50 transition-colors"
                        >
                            <td class="py-3 px-4 font-medium text-gray-800">{{ tx.code }}</td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="statusBadge(tx.status)">
                                    {{ statusLabel(tx.status) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-semibold text-gray-900">{{ formatCurrency(tx.total_amount) }}</td>
                            <td class="py-3 px-4 text-gray-600">{{ tx.user?.name || '-' }}</td>
                            <td class="py-3 px-4 text-gray-500">{{ formatDateTime(tx.created_at) }}</td>
                            <td class="py-3 px-4 text-center">
                                <Link
                                    :href="`/owner/transactions/${tx.id}`"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-gray-600 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                    Detail
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="transactions.data?.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                Tidak ada transaksi ditemukan
            </div>

            <!-- Pagination -->
            <div v-if="transactions.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">
                    Menampilkan {{ transactions.from }}–{{ transactions.to }} dari {{ transactions.total }}
                </p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in transactions.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        :class="[
                            'px-3 py-1.5 text-xs rounded-lg transition-colors',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : link.url
                                    ? 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                    : 'bg-gray-50 text-gray-300 cursor-not-allowed'
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
