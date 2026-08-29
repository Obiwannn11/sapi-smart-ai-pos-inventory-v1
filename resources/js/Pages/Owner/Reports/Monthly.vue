<script setup>
import { ref, computed } from 'vue';
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import MetricCard from '@/Components/MetricCard.vue';
import MonthPicker from '@/Components/MonthPicker.vue';
import TrendChart from '@/Components/TrendChart.vue';
import SkeletonPanel from '@/Components/Skeleton/SkeletonPanel.vue';
import SkeletonTable from '@/Components/Skeleton/SkeletonTable.vue';
import { BUSINESS_TZ } from '@/support/date';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    month: String,
    monthLabel: String,
    range: Object,
    summary: Object,
    // Konteks pajak ([BL-065]): `active` false untuk mayoritas tenant yang
    // tidak memungut, dan bagian pajaknya tidak muncul sama sekali.
    tax: { type: Object, default: () => ({ active: false, label: 'Pajak' }) },
    comparison: Object,
    dailySeries: Array,
    // Ditunda ([BL-037]) — null sampai kedua rekapnya sampai.
    paymentSummary: { type: Array, default: null },
    topProducts: { type: Array, default: null },
});

const selectedMonth = ref(props.month);

const formatCurrency = (value) => 'Rp ' + Math.round(Number(value)).toLocaleString('id-ID');

const formatDayLabel = (date) => new Date(date).toLocaleDateString('id-ID', {
    timeZone: BUSINESS_TZ,
    weekday: 'short',
    day: 'numeric',
    month: 'short',
});

const changeMonth = (newMonth) => {
    if (newMonth) selectedMonth.value = newMonth;
    router.get('/owner/reports/monthly', { month: selectedMonth.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const exportUrl = computed(() => `/owner/reports/monthly/export?month=${props.month}`);

const rangeLabel = computed(() => {
    const from = new Date(props.range.from);
    const to = new Date(props.range.to);
    return `tanggal ${from.getDate()}–${to.getDate()}`;
});

const hasData = computed(() => props.summary.total_transactions > 0 || props.summary.voided_count > 0);

// Bulan yang belum ada pembandingnya (delta null) tidak boleh ditulis "+100%".
const formatDelta = (pct) => {
    if (pct === null || pct === undefined) return null;
    return (pct > 0 ? '+' : '') + pct.toLocaleString('id-ID') + '%';
};

const deltaTone = (pct) => {
    if (pct === null || pct === undefined) return 'text-gray-400';
    if (pct > 0) return 'text-success';
    if (pct < 0) return 'text-destructive';
    return 'text-gray-400';
};

const revenueDelta = computed(() => formatDelta(props.comparison.revenue_delta_pct));
const transactionsDelta = computed(() => formatDelta(props.comparison.transactions_delta_pct));

const paymentTotal = computed(() =>
    (props.paymentSummary ?? []).reduce((sum, pm) => sum + Number(pm.total), 0)
);

const paymentShare = (amount) => {
    if (paymentTotal.value <= 0) return '0%';
    return Math.round((Number(amount) / paymentTotal.value) * 100) + '%';
};

// Hanya hari yang ada kegiatannya yang ditampilkan di tabel rincian; deret
// lengkapnya (termasuk hari nol) tetap ada di payload untuk grafik dan CSV.
const activeDays = computed(() => props.dailySeries.filter((d) => d.count > 0 || d.voided > 0));

const paymentTypeLabel = (type) => {
    if (type === 'cash') return 'Tunai';
    if (type === 'qris_static') return 'QRIS';
    return 'Transfer';
};
</script>

<template>
    <Head title="Laporan Bulanan" />

    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Header + Month Picker -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Laporan Bulanan</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Rekap {{ monthLabel }} — bulan kalender, {{ rangeLabel }}
                </p>
            </div>
            <div class="flex items-center gap-2">
                <MonthPicker v-model="selectedMonth" @update:modelValue="changeMonth" />
                <a
                    :href="exportUrl"
                    class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors shadow-sm"
                >
                    <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    CSV
                </a>
            </div>
        </div>

        <!-- Kartu ringkasan -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <MetricCard
                title="Omzet Bulan Ini"
                :value="formatCurrency(summary.total_revenue)"
                :subtitle="revenueDelta ? `${revenueDelta} dari ${comparison.label}` : `Belum ada omzet di ${comparison.label}`"
                icon="currency"
                color="success"
            />
            <MetricCard
                title="Transaksi Selesai"
                :value="summary.total_transactions"
                :subtitle="transactionsDelta ? `${transactionsDelta} dari ${comparison.label}` : `Belum ada transaksi di ${comparison.label}`"
                icon="receipt"
                color="primary"
            />
            <MetricCard
                title="Rata-rata per Transaksi"
                :value="formatCurrency(summary.average_transaction)"
                subtitle="nilai belanja rata-rata"
                icon="average"
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

        <!-- Ritme bulan -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Ritme Bulan</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs text-gray-500">Hari Berjualan</p>
                    <p class="mt-1 text-lg font-bold text-gray-900">
                        {{ summary.active_days }}
                        <span class="text-sm font-normal text-gray-400">dari {{ summary.days_in_month }} hari</span>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Rata-rata Omzet per Hari Berjualan</p>
                    <p class="mt-1 text-lg font-bold text-gray-900">{{ formatCurrency(summary.average_active_day_revenue) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Hari Teramai</p>
                    <p v-if="summary.best_day" class="mt-1 text-lg font-bold text-gray-900">
                        {{ formatDayLabel(summary.best_day.date) }}
                        <span class="text-sm font-normal text-gray-400">{{ formatCurrency(summary.best_day.revenue) }}</span>
                    </p>
                    <p v-else class="mt-1 text-lg font-bold text-gray-300">—</p>
                </div>
            </div>
        </div>

        <!-- Pajak terpungut ([BL-065] butir (e)).
             Angka kedua inilah yang dipakai pemilik untuk menyetorkan; tanpa
             pemisahan ini ia tenggelam di dalam omzet. -->
        <div v-if="tax.active" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ tax.label }} {{ monthLabel }}</h3>
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
                Rinciannya per hari ada di unduhan CSV. Struk kasir bukan faktur pajak — angka ini membantu
                menyiapkan setoran, bukan menggantikan e-Faktur.
            </p>
        </div>

        <!-- Perbandingan dengan bulan sebelumnya -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Dibanding {{ comparison.label }}</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">Ukuran</th>
                            <th class="text-right py-2 px-3 text-gray-500 font-medium">{{ comparison.label }}</th>
                            <th class="text-right py-2 px-3 text-gray-500 font-medium">{{ monthLabel }}</th>
                            <th class="text-right py-2 px-3 text-gray-500 font-medium">Selisih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-gray-50">
                            <td class="py-2.5 px-3 font-medium text-gray-800">Omzet</td>
                            <td class="py-2.5 px-3 text-right text-gray-600">{{ formatCurrency(comparison.total_revenue) }}</td>
                            <td class="py-2.5 px-3 text-right font-semibold text-gray-900">{{ formatCurrency(summary.total_revenue) }}</td>
                            <td class="py-2.5 px-3 text-right font-semibold" :class="deltaTone(comparison.revenue_delta_pct)">
                                {{ revenueDelta ?? 'Tidak ada pembanding' }}
                            </td>
                        </tr>
                        <tr>
                            <td class="py-2.5 px-3 font-medium text-gray-800">Transaksi</td>
                            <td class="py-2.5 px-3 text-right text-gray-600">{{ comparison.total_transactions }}</td>
                            <td class="py-2.5 px-3 text-right font-semibold text-gray-900">{{ summary.total_transactions }}</td>
                            <td class="py-2.5 px-3 text-right font-semibold" :class="deltaTone(comparison.transactions_delta_pct)">
                                {{ transactionsDelta ?? 'Tidak ada pembanding' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Rekap per metode pembayaran. Ditunda ([BL-037]) bersama produk
             terlaris: keduanya menyisir sebulan penuh. -->
        <Deferred data="paymentSummary">
            <template #fallback>
                <SkeletonPanel label="Memuat rekap metode pembayaran…">
                    <SkeletonTable :rows="4" :columns="4" :header="false" />
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
                            <th class="text-right py-2 px-3 text-gray-500 font-medium">Porsi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="pm in paymentSummary" :key="pm.name" class="border-b border-gray-50 last:border-0">
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
                                    {{ paymentTypeLabel(pm.type) }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-semibold text-gray-900">{{ formatCurrency(pm.total) }}</td>
                            <td class="py-2.5 px-3 text-right text-gray-500">{{ paymentShare(pm.total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        </Deferred>

        <!-- Produk terlaris -->
        <Deferred data="topProducts">
            <template #fallback>
                <SkeletonPanel label="Memuat produk terlaris…">
                    <SkeletonTable :rows="6" :columns="4" :header="false" />
                </SkeletonPanel>
            </template>

        <div v-if="topProducts.length > 0" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Top 10 Produk Terlaris Bulan Ini</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">#</th>
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">Varian</th>
                            <th class="text-right py-2 px-3 text-gray-500 font-medium">Qty Terjual</th>
                            <th class="text-right py-2 px-3 text-gray-500 font-medium">Omzet</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(product, index) in topProducts" :key="product.variant_name" class="border-b border-gray-50 last:border-0">
                            <td class="py-2.5 px-3 text-gray-400">{{ index + 1 }}</td>
                            <td class="py-2.5 px-3 font-medium text-gray-800">{{ product.variant_name }}</td>
                            <td class="py-2.5 px-3 text-right text-gray-700">{{ product.total_qty }}</td>
                            <td class="py-2.5 px-3 text-right font-semibold text-gray-900">{{ formatCurrency(product.total_revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        </Deferred>

        <!-- Tren harian: bentuk bulannya, sebelum angkanya dibaca satu per satu -->
        <TrendChart
            :data="dailySeries"
            :title="`Tren Omzet Harian — ${monthLabel}`"
            :empty-label="`Belum ada penjualan di ${monthLabel}`"
        />

        <!-- Rincian harian -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Rincian Harian</h3>
                <span class="text-xs text-gray-400">{{ activeDays.length }} hari ada kegiatan</span>
            </div>

            <div v-if="activeDays.length > 0" class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="text-left py-2 px-5 text-gray-500 font-medium">Tanggal</th>
                            <th class="text-right py-2 px-5 text-gray-500 font-medium">Transaksi</th>
                            <th class="text-right py-2 px-5 text-gray-500 font-medium">Omzet</th>
                            <th class="text-right py-2 px-5 text-gray-500 font-medium">Void</th>
                            <th class="text-right py-2 px-5 text-gray-500 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="day in activeDays" :key="day.date" class="border-b border-gray-50 last:border-0">
                            <td class="py-2.5 px-5 font-medium text-gray-800 capitalize">{{ formatDayLabel(day.date) }}</td>
                            <td class="py-2.5 px-5 text-right text-gray-700">{{ day.count }}</td>
                            <td class="py-2.5 px-5 text-right font-semibold text-gray-900">{{ formatCurrency(day.revenue) }}</td>
                            <td class="py-2.5 px-5 text-right" :class="day.voided > 0 ? 'text-gray-600' : 'text-gray-300'">{{ day.voided }}</td>
                            <td class="py-2.5 px-5 text-right">
                                <Link
                                    :href="`/owner/reports/daily?date=${day.date}`"
                                    class="text-xs text-primary hover:text-primary/80 font-medium"
                                >
                                    Lihat harian
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50 border-t border-gray-200">
                            <td class="py-2.5 px-5 font-semibold text-gray-700">Total</td>
                            <td class="py-2.5 px-5 text-right font-semibold text-gray-900">{{ summary.total_transactions }}</td>
                            <td class="py-2.5 px-5 text-right font-semibold text-gray-900">{{ formatCurrency(summary.total_revenue) }}</td>
                            <td class="py-2.5 px-5 text-right font-semibold text-gray-700">{{ summary.voided_count }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div v-else class="px-5 py-10 text-center">
                <p class="text-sm text-gray-500 font-medium">Belum ada penjualan di {{ monthLabel }}</p>
                <p class="text-xs text-gray-400 mt-1">
                    Transaksi yang tercatat di bulan ini akan langsung muncul di sini — termasuk penjualan offline yang baru tersinkron kemudian.
                </p>
            </div>
        </div>

        <!-- Catatan cakupan: dibaca sekali, mencegah salah paham berulang -->
        <p v-if="hasData" class="text-xs text-gray-400 px-1">
            Angka di atas memakai tanggal penjualan sebenarnya, bukan tanggal data masuk ke server — penjualan offline yang baru tersinkron tetap dihitung di bulan saat transaksinya terjadi.
        </p>
    </div>
</template>
