<script setup>
import { usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import ReceiptModal from '@/Components/ReceiptModal.vue';

const props = defineProps({
    transactions: Object,
    filters: Object,
});

const { auth } = usePage().props;

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
        pending: 'Open Bill',
        completed: 'Selesai',
        voided: 'Void',
    };
    return map[status] || status;
};

const statusClass = (status) => {
    const map = {
        pending: 'bg-amber-100 text-amber-800',
        completed: 'bg-green-100 text-green-800',
        voided: 'bg-red-100 text-red-800',
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

const goToPOS = () => {
    router.get('/cashier/pos');
};

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <FlashMessage />

        <!-- Header -->
        <nav class="bg-white shadow-sm px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h1 class="text-lg font-bold text-indigo-600">SAPI — Riwayat</h1>
            </div>
            <div class="flex items-center gap-3">
                <button @click="goToPOS" class="text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                    Kembali ke POS
                </button>
                <button @click="logout" class="text-sm text-red-500 hover:text-red-700">Logout</button>
            </div>
        </nav>

        <main class="max-w-3xl mx-auto py-6 px-4">
            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-4">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[140px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Status</label>
                        <select
                            v-model="statusFilter"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500"
                        >
                            <option value="">Semua</option>
                            <option value="pending">Open Bill</option>
                            <option value="completed">Selesai</option>
                            <option value="voided">Void</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[140px]">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal</label>
                        <input
                            v-model="dateFilter"
                            type="date"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="applyFilters"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition"
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
                            <span class="text-sm font-semibold text-indigo-600">{{ tx.code }}</span>
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
                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1"
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
                                ? 'bg-indigo-600 text-white'
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
