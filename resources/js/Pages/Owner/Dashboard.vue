<script setup>
import { Head, Link } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import MetricCard from '@/Components/MetricCard.vue';
import BadgeCard from '@/Components/BadgeCard.vue';
import DailyChart from '@/Components/DailyChart.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    metrics: Object,
    dailyTrend: Array,
    badges: Array,
    recentTransactions: Array,
});

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatTime = (datetime) => {
    return new Date(datetime).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDate = (datetime) => {
    return new Date(datetime).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
    });
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Page Header -->
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-sm text-gray-500 mt-1">Ringkasan bisnis Anda hari ini</p>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <MetricCard
                title="Pendapatan Hari Ini"
                :value="formatCurrency(metrics.today_revenue)"
                icon="currency"
                color="green"
            />
            <MetricCard
                title="Transaksi Hari Ini"
                :value="metrics.today_count"
                subtitle="transaksi"
                icon="receipt"
                color="blue"
            />
            <MetricCard
                title="Rata-rata / Trx"
                :value="formatCurrency(metrics.today_average)"
                icon="average"
                color="purple"
            />
            <MetricCard
                title="Minggu Ini"
                :value="formatCurrency(metrics.week_revenue)"
                icon="chart"
                color="indigo"
            />
        </div>

        <!-- Rekap per Payment Method -->
        <div v-if="metrics.today_by_payment_method?.length > 0" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Rekap per Metode Pembayaran (Hari Ini)</h3>
            <div class="flex flex-wrap gap-3">
                <div
                    v-for="pm in metrics.today_by_payment_method"
                    :key="pm.name"
                    class="flex items-center gap-2 bg-gray-50 rounded-lg px-4 py-2.5"
                >
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                        :class="{
                            'bg-green-100 text-green-800': pm.type === 'cash',
                            'bg-blue-100 text-blue-800': pm.type === 'qris_static',
                            'bg-purple-100 text-purple-800': pm.type === 'bank_transfer',
                        }"
                    >
                        {{ pm.type === 'cash' ? 'Tunai' : pm.type === 'qris_static' ? 'QRIS' : 'Transfer' }}
                    </span>
                    <span class="text-sm text-gray-700">{{ pm.name }}</span>
                    <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(pm.total) }}</span>
                </div>
            </div>
        </div>

        <!-- Badges -->
        <div v-if="badges.length > 0" class="space-y-3">
            <h3 class="text-sm font-semibold text-gray-700">Alert & Notifikasi</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <BadgeCard
                    v-for="badge in badges"
                    :key="badge.type"
                    :type="badge.type"
                    :severity="badge.severity"
                    :title="badge.title"
                    :count="badge.count"
                    :message="badge.message"
                    :items="badge.items"
                />
            </div>
        </div>

        <!-- Chart Trend 7 Hari -->
        <DailyChart :data="dailyTrend" />

        <!-- Transaksi Terbaru -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Transaksi Terbaru</h3>
                <Link href="/owner/transactions" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                    Lihat Semua →
                </Link>
            </div>

            <div v-if="recentTransactions.length > 0">
                <div
                    v-for="tx in recentTransactions"
                    :key="tx.id"
                    class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ tx.code }}</p>
                            <p class="text-xs text-gray-400">{{ tx.user?.name || '-' }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900">{{ formatCurrency(tx.total_amount) }}</p>
                        <p class="text-xs text-gray-400">{{ formatDate(tx.created_at) }} {{ formatTime(tx.created_at) }}</p>
                    </div>
                </div>
            </div>
            <div v-else class="px-5 py-8 text-center text-sm text-gray-400">
                Belum ada transaksi hari ini
            </div>
        </div>

        <!-- Quick Links -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <Link href="/owner/reports/daily" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md hover:border-indigo-200 transition-all text-center group">
                <svg class="w-6 h-6 mx-auto text-indigo-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-xs font-medium text-gray-700 group-hover:text-indigo-600">Laporan Harian</p>
            </Link>
            <Link href="/owner/transactions" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md hover:border-indigo-200 transition-all text-center group">
                <svg class="w-6 h-6 mx-auto text-indigo-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <p class="text-xs font-medium text-gray-700 group-hover:text-indigo-600">Riwayat Transaksi</p>
            </Link>
            <Link href="/owner/cash-drawers" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md hover:border-indigo-200 transition-all text-center group">
                <svg class="w-6 h-6 mx-auto text-indigo-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <p class="text-xs font-medium text-gray-700 group-hover:text-indigo-600">Riwayat Kas</p>
            </Link>
            <Link href="/owner/stock" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md hover:border-indigo-200 transition-all text-center group">
                <svg class="w-6 h-6 mx-auto text-indigo-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
                <p class="text-xs font-medium text-gray-700 group-hover:text-indigo-600">Kelola Stok</p>
            </Link>
        </div>
    </div>
</template>
