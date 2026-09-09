<script setup>
import { ref, computed, watch } from 'vue';
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import MetricCard from '@/Components/MetricCard.vue';
import MonthPicker from '@/Components/MonthPicker.vue';
import TrendChart from '@/Components/TrendChart.vue';
import TopProductsTable from '@/Components/TopProductsTable.vue';
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

const formatFullDayLabel = (date) => new Date(date).toLocaleDateString('id-ID', {
    timeZone: BUSINESS_TZ,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

const changeMonth = (newMonth) => {
    if (newMonth) selectedMonth.value = newMonth;
    router.get('/owner/reports/monthly', { month: selectedMonth.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const exportUrl = computed(() => `/owner/reports/monthly/export?month=${props.month}`);

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

// Hanya hari yang ada penjualannya yang masuk daftar rincian; deret lengkapnya
// (termasuk hari nol) tetap ada di payload untuk grafik dan CSV.
const activeDays = computed(() => props.dailySeries.filter((d) => d.count > 0));

// Rincian harian dibuka satu hari pada satu waktu. Tabel 31 baris × 5 kolom
// menuntut pemilik memindai seluruhnya untuk membaca satu hari; di sini
// tanggalnya dipilih, dan angkanya berdiri sendiri di sebelahnya.
//
// Keadaan pertamanya sengaja KOSONG, bukan hari pertama bulan itu. Panel yang
// sudah terisi saat halaman dibuka terbaca sebagai ringkasan bulan — dan
// angkanya akan dikira angka sebulan.
const selectedDate = ref(null);

// Berpindah bulan mengosongkannya kembali: tanggal yang dipilih di bulan lalu
// tidak ada di bulan ini, dan panel yang menggantung akan menampilkan angka
// kosong tanpa sebab yang terlihat.
watch(() => props.month, () => {
    selectedDate.value = null;
});

const selectedDay = computed(() =>
    activeDays.value.find((day) => day.date === selectedDate.value) ?? null
);

const selectedAverage = computed(() => {
    const day = selectedDay.value;
    if (!day || day.count <= 0) return 0;
    return day.revenue / day.count;
});

const selectDay = (day) => {
    selectedDate.value = selectedDate.value === day.date ? null : day.date;
};

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
                <p class="text-sm text-gray-500 mt-1">Rekap {{ monthLabel }}</p>
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

        <!-- Dua angka, dan hanya dua. Perbandingannya tidak ikut ke sini:
             pemilik yang membuka layar ini mencari "berapa bulan ini", dan
             "-24% dari Juli" adalah pertanyaan berikutnya, bukan pertanyaan
             yang sama. Jawabannya menunggu di kaki halaman. -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <MetricCard
                title="Omzet Bulan Ini"
                :value="formatCurrency(summary.total_revenue)"
                icon="currency"
                color="success"
            />
            <MetricCard
                title="Transaksi Selesai"
                :value="summary.total_transactions"
                subtitle="transaksi"
                icon="receipt"
                color="primary"
            />
        </div>

        <!-- Bentuk bulannya lebih dulu, sebelum angkanya dibaca satu per satu -->
        <TrendChart
            :data="dailySeries"
            :title="`Tren Omzet Harian — ${monthLabel}`"
            :empty-label="`Belum ada penjualan di ${monthLabel}`"
        />

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
                        <tr v-for="pm in paymentSummary" :key="pm.id" class="border-b border-gray-50 last:border-0">
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

        <TopProductsTable title="Top 10 Produk Terlaris Bulan Ini" :products="topProducts" />

        </Deferred>

        <!-- Rincian harian: tanggalnya di kiri, angkanya di kanan -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Rincian Harian</h3>
                <span class="text-xs text-gray-400">{{ activeDays.length }} hari ada penjualan</span>
            </div>

            <div v-if="activeDays.length > 0" class="grid md:grid-cols-2">
                <!-- Daftar tanggal -->
                <div class="md:border-r border-gray-100">
                    <ul class="max-h-96 overflow-y-auto divide-y divide-gray-50">
                        <li v-for="day in activeDays" :key="day.date">
                            <button
                                type="button"
                                :aria-pressed="selectedDate === day.date"
                                class="w-full flex items-center justify-between gap-3 px-5 py-2.5 text-left transition-colors focus:outline-none focus:ring-2 focus:ring-inset focus:ring-ring"
                                :class="selectedDate === day.date
                                    ? 'bg-primary/5 border-l-2 border-primary'
                                    : 'border-l-2 border-transparent hover:bg-gray-50'"
                                @click="selectDay(day)"
                            >
                                <span
                                    class="text-sm capitalize"
                                    :class="selectedDate === day.date ? 'font-semibold text-gray-900' : 'font-medium text-gray-700'"
                                >
                                    {{ formatDayLabel(day.date) }}
                                </span>
                                <span class="text-sm text-gray-500 tabular-nums">{{ formatCurrency(day.revenue) }}</span>
                            </button>
                        </li>
                    </ul>

                    <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-t border-gray-100">
                        <span class="text-sm font-semibold text-gray-700">
                            Total {{ summary.total_transactions }} transaksi
                        </span>
                        <span class="text-sm font-semibold text-gray-900 tabular-nums">
                            {{ formatCurrency(summary.total_revenue) }}
                        </span>
                    </div>
                </div>

                <!-- Angka hari yang dipilih -->
                <div class="p-5 border-t md:border-t-0 border-gray-100">
                    <div v-if="selectedDay">
                        <p class="text-xs text-gray-500">{{ formatFullDayLabel(selectedDay.date) }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">
                            {{ formatCurrency(selectedDay.revenue) }}
                        </p>

                        <div class="mt-5 grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Transaksi</p>
                                <p class="mt-1 text-lg font-semibold text-gray-900 tabular-nums">{{ selectedDay.count }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500">Rata-rata per Transaksi</p>
                                <p class="mt-1 text-lg font-semibold text-gray-900 tabular-nums">
                                    {{ formatCurrency(selectedAverage) }}
                                </p>
                            </div>
                        </div>

                        <Link
                            :href="`/owner/reports/daily?date=${selectedDay.date}`"
                            class="mt-5 inline-flex items-center gap-1 text-sm font-medium text-primary hover:text-primary/80"
                        >
                            Buka laporan harian
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                    </div>

                    <div v-else class="h-full flex flex-col items-center justify-center text-center py-8">
                        <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="mt-3 text-sm text-gray-500">Tekan salah satu tanggal untuk melihat rinciannya.</p>
                    </div>
                </div>
            </div>

            <div v-else class="px-5 py-10 text-center">
                <p class="text-sm text-gray-500 font-medium">Belum ada penjualan di {{ monthLabel }}</p>
                <p class="text-xs text-gray-400 mt-1">
                    Transaksi yang tercatat di bulan ini akan langsung muncul di sini — termasuk penjualan offline yang baru tersinkron kemudian.
                </p>
            </div>
        </div>

        <!-- Perbandingan dengan bulan sebelumnya. Paling bawah dengan sengaja:
             ia menjawab pertanyaan lanjutan, dan pertanyaan lanjutan tidak
             boleh berdiri di depan pertanyaan pertama. -->
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
    </div>
</template>
