<script setup>
import { ref } from 'vue';
import { Deferred, Head, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import MetricCard from '@/Components/MetricCard.vue';
import DatePicker from '@/Components/DatePicker.vue';
import TopProductsTable from '@/Components/TopProductsTable.vue';
import DiscountSummaryCard from '@/Components/DiscountSummaryCard.vue';
import SkeletonPanel from '@/Components/Skeleton/SkeletonPanel.vue';
import SkeletonTable from '@/Components/Skeleton/SkeletonTable.vue';
import SkeletonList from '@/Components/Skeleton/SkeletonList.vue';
import { BUSINESS_TZ } from '@/support/date';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    date: String,
    summary: Object,
    // Konteks pajak ([BL-065]): `active` false untuk mayoritas tenant yang
    // tidak memungut, dan bagian pajaknya tidak muncul sama sekali.
    tax: { type: Object, default: () => ({ active: false, label: 'Pajak' }) },
    transactions: Array,
    paymentSummary: Array,
    topProducts: Array,
    // Potongan harga hari itu ([BL-018]). Ditunda bersama rekap lain.
    discountSummary: Object,
});

const selectedDate = ref(props.date);

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatTime = (datetime) => {
    return new Date(datetime).toLocaleTimeString('id-ID', {
        timeZone: BUSINESS_TZ,
        hour: '2-digit',
        minute: '2-digit',
    });
};

const changeDate = (newDate) => {
    if (newDate) selectedDate.value = newDate;
    router.get('/owner/reports/daily', { date: selectedDate.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Expandable transactions
const expandedTxIds = ref(new Set());

const toggleTx = (id) => {
    if (expandedTxIds.value.has(id)) {
        expandedTxIds.value.delete(id);
    } else {
        expandedTxIds.value.add(id);
    }
};
</script>

<template>
    <Head title="Laporan Harian" />

    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Header + Date Picker -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Laporan Harian</h1>
                <p class="text-sm text-gray-500 mt-1">Detail penjualan per hari</p>
            </div>
            <div class="flex items-center gap-2">
                <DatePicker v-model="selectedDate" @update:modelValue="changeDate" />
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <MetricCard
                title="Total Pendapatan"
                :value="formatCurrency(summary.total_revenue)"
                :subtitle="tax.active ? `termasuk ${tax.label} yang dipungut` : null"
                icon="currency"
                color="success"
            />
            <MetricCard
                title="Total Transaksi"
                :value="summary.total_transactions"
                subtitle="transaksi selesai"
                icon="receipt"
                color="primary"
            />
            <MetricCard
                title="Transaksi Void"
                :value="summary.voided_count"
                subtitle="dibatalkan"
                icon="average"
                color="muted"
            />
        </div>

        <!-- Pajak terpungut ([BL-065] butir (e)).
             Dua angka yang dipisah dari omzet: yang jadi pendapatan toko, dan
             yang hanya dititipkan pelanggan untuk disetorkan. -->
        <div v-if="tax.active" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ tax.label }} Hari Ini</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs text-gray-500">Omzet Sebelum Pajak</p>
                    <p class="mt-1 text-lg font-bold text-gray-900">{{ formatCurrency(summary.net_revenue) }}</p>
                    <p class="mt-0.5 text-xs text-gray-400">pendapatan toko</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">{{ tax.label }} Terpungut</p>
                    <p class="mt-1 text-lg font-bold text-gray-900">{{ formatCurrency(summary.tax_collected) }}</p>
                    <p class="mt-0.5 text-xs text-gray-400">dititipkan untuk disetorkan</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Dibayar Pelanggan</p>
                    <p class="mt-1 text-lg font-bold text-gray-900">{{ formatCurrency(summary.total_revenue) }}</p>
                    <p class="mt-0.5 text-xs text-gray-400">uang yang masuk</p>
                </div>
            </div>
            <p class="mt-4 text-xs text-gray-400">
                Struk kasir bukan faktur pajak. Angka ini membantu menyiapkan setoran, bukan menggantikan e-Faktur.
            </p>
        </div>

        <!-- Rekap per Payment Method -->
        <Deferred data="paymentSummary">
            <template #fallback>
                <SkeletonPanel label="Memuat rekap metode pembayaran…">
                    <SkeletonTable :rows="3" :columns="3" :header="false" />
                </SkeletonPanel>
            </template>

        <div v-if="paymentSummary.length > 0" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Rekap per Metode Pembayaran</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">Metode</th>
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">Tipe</th>
                            <th class="text-right py-2 px-3 text-gray-500 font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="pm in paymentSummary" :key="pm.id" class="border-b border-gray-50">
                            <td class="py-2.5 px-3 font-medium text-gray-800">{{ pm.name }}</td>
                            <td class="py-2.5 px-3">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                    :class="{
                                        'bg-success/10 text-success': pm.type === 'cash',
                                        'bg-primary/10 text-primary': pm.type === 'qris_static',
                                        'bg-secondary text-secondary-foreground': pm.type === 'bank_transfer',
                                    }"
                                >
                                    {{ pm.type === 'cash' ? 'Tunai' : pm.type === 'qris_static' ? 'QRIS' : 'Transfer' }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-semibold text-gray-900">{{ formatCurrency(pm.total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        </Deferred>

        <!-- Top Products -->
        <Deferred data="topProducts">
            <template #fallback>
                <SkeletonPanel label="Memuat produk terlaris…">
                    <SkeletonTable :rows="5" :columns="4" :header="false" />
                </SkeletonPanel>
            </template>

        <TopProductsTable
            title="Top 10 Produk Terlaris"
            :products="topProducts"
            revenue-label="Revenue"
        />
        </Deferred>

        <!-- Potongan harga ([BL-018]).
             TIGA angka sejak [BL-116]: harga normal barang, yang dipotong, dan
             yang benar-benar DIKORBANKAN di bawah lantai untung. Tanpa
             pemisahan terakhir itu, sebuah penjualan rugi terlihat persis
             seperti diskon 5% yang sehat.

             Kartunya milik bersama dengan rekap bulanan ([BL-116] butir 1) —
             satu pembaca di server, satu tampilan di klien. -->
        <Deferred data="discountSummary">
            <template #fallback>
                <SkeletonPanel label="Memuat rekap potongan…">
                    <SkeletonTable :rows="2" :columns="3" :header="false" />
                </SkeletonPanel>
            </template>

            <DiscountSummaryCard
                v-if="discountSummary && discountSummary.items_discounted > 0"
                :summary="discountSummary"
            />
        </Deferred>

        <!-- Transaction List. Bagian terberat halaman ini: tiap baris membawa
             item dan pembayarannya, jadi ia yang paling lama sampai. -->
        <Deferred data="transactions">
            <template #fallback>
                <SkeletonPanel flush label="Memuat daftar transaksi…">
                    <SkeletonList :rows="6" :leading="false" />
                </SkeletonPanel>
            </template>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Daftar Transaksi</h3>
            </div>

            <div v-if="transactions.length > 0">
                <div
                    v-for="tx in transactions"
                    :key="tx.id"
                    class="border-b border-gray-50 last:border-0"
                >
                    <!-- Transaction header row -->
                    <div
                        class="flex items-center justify-between px-5 py-3 cursor-pointer hover:bg-gray-50 transition-colors"
                        @click="toggleTx(tx.id)"
                    >
                        <div class="flex items-center gap-3">
                            <svg
                                class="w-4 h-4 text-gray-400 transition-transform"
                                :class="{ 'rotate-90': expandedTxIds.has(tx.id) }"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            <div>
                                <span class="text-sm font-medium text-gray-800">{{ tx.code }}</span>
                                <span class="text-xs text-gray-400 ml-2">{{ tx.user?.name }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(tx.total_amount) }}</span>
                            <span class="text-xs text-gray-400 ml-2">{{ formatTime(tx.created_at) }}</span>
                        </div>
                    </div>

                    <!-- Expanded detail -->
                    <div v-if="expandedTxIds.has(tx.id)" class="bg-gray-50 px-5 py-3 space-y-2">
                        <div v-for="item in tx.items" :key="item.id" class="flex justify-between text-xs text-gray-600">
                            <span>{{ item.variant_name }} × {{ item.qty }}</span>
                            <span class="font-medium">{{ formatCurrency(item.subtotal) }}</span>
                        </div>
                        <div v-if="tx.payments?.length" class="border-t border-gray-200 pt-2 mt-2">
                            <div v-for="pay in tx.payments" :key="pay.id" class="flex justify-between text-xs text-gray-500">
                                <span>{{ pay.payment_method?.name || 'Payment' }}</span>
                                <span>{{ formatCurrency(pay.amount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="px-5 py-8 text-center text-sm text-gray-400">
                Tidak ada transaksi pada tanggal ini
            </div>
        </div>
        </Deferred>
    </div>
</template>
