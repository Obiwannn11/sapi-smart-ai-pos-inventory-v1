<script setup>
import { router, Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import ReceiptModal from '@/Components/ReceiptModal.vue';
import CashierTopbar from '@/Components/CashierTopbar.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const props = defineProps({
    transactions: Object,
    filters: Object,
});

const statusOptions = [
    { value: '', label: 'Semua' },
    { value: 'pending', label: 'Tagihan Terbuka' },
    { value: 'completed', label: 'Selesai' },
    { value: 'voided', label: 'Void' },
];

const statusFilter = ref(props.filters?.status || '');
const dateFilter = ref(props.filters?.date || '');
const showReceiptModal = ref(false);
const selectedTransaction = ref(null);

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatDate = (date) => {
    return new Date(date).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const statusLabel = (status) => {
    const map = {
        pending: 'Tagihan Terbuka',
        completed: 'Selesai',
        voided: 'Void',
    };
    return map[status] || status;
};

const statusClass = (status) => {
    const map = {
        pending: 'bg-warning/10 text-warning-foreground',
        completed: 'bg-success/10 text-success',
        voided: 'bg-destructive/10 text-destructive',
    };
    return map[status] || 'bg-gray-100 text-gray-800';
};

const applyFilters = () => {
    const params = {};
    if (statusFilter.value) params.status = statusFilter.value;
    if (dateFilter.value) params.date = dateFilter.value;
    router.get('/cashier/transactions', params, { preserveState: true });
};

const clearFilters = () => {
    statusFilter.value = '';
    dateFilter.value = '';
    router.get('/cashier/transactions');
};

const viewReceipt = (transaction) => {
    selectedTransaction.value = transaction;
    showReceiptModal.value = true;
};

</script>

<template>
    <Head title="Riwayat Transaksi" />
    <div class="min-h-screen bg-background">
        <FlashMessage />

        <!-- Header -->
        <CashierTopbar title="Riwayat" />

        <main class="max-w-3xl mx-auto py-6 px-4">
            <!-- Filters -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-4">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z" />
                    </svg>
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Filter</span>
                </div>
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[140px]">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <SelectDropdown
                            v-model="statusFilter"
                            :options="statusOptions"
                        />
                    </div>
                    <div class="flex-1 min-w-[140px]">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal</label>
                        <input
                            v-model="dateFilter"
                            type="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>
                    <div class="flex gap-2 items-end">
                        <button
                            @click="applyFilters"
                            class="px-4 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 transition"
                        >
                            Filter
                        </button>
                        <button
                            @click="clearFilters"
                            class="px-4 py-2 bg-gray-100 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-200 transition"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Transaction List -->
            <div class="space-y-3">
                <div
                    v-for="tx in transactions.data"
                    :key="tx.id"
                    class="bg-white rounded-lg shadow-sm border border-gray-200 p-4"
                >
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-primary">{{ tx.code }}</span>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', statusClass(tx.status)]">
                                {{ statusLabel(tx.status) }}
                            </span>
                        </div>
                        <span class="text-xs text-gray-400">{{ formatDate(tx.created_at) }}</span>
                    </div>

                    <!-- Items preview -->
                    <div class="text-xs text-gray-600 space-y-0.5 mb-2">
                        <p v-for="item in tx.items?.slice(0, 3)" :key="item.id" class="truncate">
                            {{ item.qty }}x {{ item.variant_name }}
                            <span v-if="item.notes" class="text-amber-600 italic"> — {{ item.notes }}</span>
                        </p>
                        <p v-if="tx.items?.length > 3" class="text-gray-400">
                            +{{ tx.items.length - 3 }} item lainnya
                        </p>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-sm font-bold text-gray-800">{{ formatCurrency(tx.total_amount) }}</span>
                        <button
                            @click="viewReceipt(tx)"
                            class="text-xs text-primary hover:text-primary/80 font-medium flex items-center gap-1"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Lihat Struk
                        </button>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="transactions.data.length === 0" class="text-center py-12 text-gray-400">
                    <p class="text-sm">Tidak ada transaksi ditemukan.</p>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="transactions.last_page > 1" class="flex justify-center gap-2 mt-6">
                <template v-for="link in transactions.links" :key="link.label">
                    <button
                        v-if="link.url"
                        @click="router.get(link.url)"
                        :class="[
                            'px-3 py-1.5 text-sm rounded-lg transition',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-white border border-gray-300 text-gray-600 hover:bg-gray-50'
                        ]"
                        v-html="link.label"
                    />
                    <span v-else class="px-3 py-1.5 text-sm text-gray-300" v-html="link.label" />
                </template>
            </div>
        </main>

        <!-- Receipt Modal -->
        <ReceiptModal
            :show="showReceiptModal"
            :transaction="selectedTransaction"
            @close="showReceiptModal = false"
        />
    </div>
</template>
