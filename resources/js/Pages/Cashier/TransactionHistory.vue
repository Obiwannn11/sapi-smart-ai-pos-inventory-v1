<script setup>
import { Deferred, router, Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import Pagination from '@/Components/Pagination.vue';
import ReceiptModal from '@/Components/ReceiptModal.vue';
import CashierTopbar from '@/Components/CashierTopbar.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import DatePicker from '@/Components/DatePicker.vue';
import TransactionEditModal from '@/Components/TransactionEditModal.vue';
import SkeletonGrid from '@/Components/Skeleton/SkeletonGrid.vue';
import SkeletonCard from '@/Components/Skeleton/SkeletonCard.vue';
import { BUSINESS_TZ } from '@/support/date';

const props = defineProps({
    transactions: { type: Object, default: null },
    filters: Object,
    // Batas daftar yang sedang berlaku. Kasir dibatasi ke sesi kas berjalan
    // dan tidak boleh menyetel tanggal sendiri — lihat [BL-027].
    scope: { type: Object, default: () => ({ label: '', can_filter_date: false }) },
    products: { type: Array, default: null },
    paymentMethods: { type: Array, default: () => [] },
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
        timeZone: BUSINESS_TZ,
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
    if (props.scope.can_filter_date && dateFilter.value) params.date = dateFilter.value;
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

// Edit transaksi
const showEditModal = ref(false);
const editingTransaction = ref(null);

const openEdit = (transaction) => {
    editingTransaction.value = transaction;
    showEditModal.value = true;
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
                    <span
                        v-if="scope.label"
                        class="ml-auto inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-medium text-primary"
                        :title="scope.can_filter_date ? undefined : 'Riwayat dibatasi ke sesi kas Anda yang sedang berjalan'"
                    >
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ scope.label }}
                    </span>
                </div>
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[140px]">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                        <SelectDropdown
                            v-model="statusFilter"
                            :options="statusOptions"
                        />
                    </div>
                    <div v-if="scope.can_filter_date" class="flex-1 min-w-[140px]">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Tanggal</label>
                        <DatePicker v-model="dateFilter" />
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

            <!-- Transaction List. Ditunda ([BL-037]): kerangka kartu ini juga
                 yang muncul kembali setiap filter diubah, jadi ada tanda bahwa
                 isi daftarnya sedang diganti. -->
            <Deferred data="transactions">
                <template #fallback>
                    <SkeletonGrid
                        :count="4"
                        columns="grid-cols-1"
                        gap="gap-3"
                        label="Memuat riwayat transaksi…"
                    >
                        <SkeletonCard :lines="3" footer padding="p-4" />
                    </SkeletonGrid>
                </template>

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
                            <span
                                v-if="tx.channel === 'offline'"
                                class="px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800"
                                :title="`Terjadi ${formatDate(tx.occurred_at)}, tersinkron ${formatDate(tx.synced_at)}`"
                            >
                                Offline
                            </span>
                            <span
                                v-if="tx.sync_status === 'needs_review'"
                                class="px-2 py-0.5 rounded-full text-xs font-medium bg-destructive/10 text-destructive"
                                title="Ada anomali stok/harga — menunggu koreksi owner"
                            >
                                Perlu koreksi
                            </span>
                        </div>
                        <!-- Tanggal penjualan sebenarnya: untuk transaksi offline
                             created_at adalah waktu sync, bukan waktu jual. -->
                        <span class="text-xs text-gray-400">{{ formatDate(tx.occurred_at ?? tx.created_at) }}</span>
                    </div>

                    <!-- Identitas pesanan. Ada di sini karena identitas yang
                         hanya hidup di layar kasir tidak menolong siapa pun
                         saat pesanan dicari kembali ([BL-026]). -->
                    <div v-if="tx.queue_number || tx.table_number || tx.customer_name" class="flex flex-wrap items-center gap-1.5 mb-2">
                        <span v-if="tx.queue_number" class="px-2 py-0.5 rounded text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                            No. {{ tx.queue_number }}
                        </span>
                        <span v-if="tx.table_number" class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                            Meja {{ tx.table_number }}
                        </span>
                        <span v-if="tx.customer_name" class="px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700 truncate max-w-[12rem]">
                            {{ tx.customer_name }}
                        </span>
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
                        <div class="flex items-center gap-3">
                            <button
                                v-if="tx.can_edit"
                                @click="openEdit(tx)"
                                class="text-xs text-primary hover:text-primary/80 font-medium flex items-center gap-1"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Edit
                            </button>
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
                </div>

                <!-- Empty state -->
                <div v-if="transactions.data.length === 0" class="text-center py-12 text-gray-400">
                    <p class="text-sm">Tidak ada transaksi ditemukan.</p>
                    <p v-if="scope.label" class="text-xs mt-1">Cakupan: {{ scope.label }}.</p>
                </div>
            </div>

            <Pagination :paginator="transactions" unit="transaksi" />
            </Deferred>
        </main>

        <!-- Receipt Modal -->
        <ReceiptModal
            :show="showReceiptModal"
            :transaction="selectedTransaction"
            @close="showReceiptModal = false"
        />

        <!-- Edit Transaksi Modal -->
        <TransactionEditModal
            :show="showEditModal"
            :transaction="editingTransaction"
            :products="products"
            :payment-methods="paymentMethods"
            @close="showEditModal = false"
        />
    </div>
</template>
