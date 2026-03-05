<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    transactions: Object, // paginated
    filters: Object,
});

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
        case 'completed': return 'bg-green-100 text-green-800';
        case 'voided': return 'bg-red-100 text-red-800';
        case 'pending': return 'bg-yellow-100 text-yellow-800';
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
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                    <select
                        v-model="filterStatus"
                        class="rounded-lg border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option value="">Semua</option>
                        <option value="completed">Selesai</option>
                        <option value="voided">Void</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Dari</label>
                    <input
                        v-model="filterFrom"
                        type="date"
                        class="rounded-lg border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Sampai</label>
                    <input
                        v-model="filterTo"
                        type="date"
                        class="rounded-lg border-gray-300 text-sm shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>
                <button
                    @click="applyFilters"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors"
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
                                    class="text-indigo-600 hover:text-indigo-800 text-xs font-medium"
                                >
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
                                ? 'bg-indigo-600 text-white'
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
