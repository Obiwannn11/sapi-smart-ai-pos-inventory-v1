<script setup>
import { computed } from 'vue';
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
    subscription: Object,
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

/**
 * Tanggal kalender polos ('2026-08-21') dirakit komponennya sendiri, bukan
 * lewat `new Date(string)`: bentuk itu dibaca sebagai tengah malam UTC,
 * sehingga di zona yang di belakang UTC hasilnya mundur sehari. Zona Indonesia
 * kebetulan aman, tapi jatuh tempo yang benar hanya karena kebetulan bukan
 * jatuh tempo yang benar. Pola yang sama dipakai halaman Langganan.
 */
const formatCalendarDate = (value) => {
    if (!value) return null;
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const daysUntil = (value) => {
    if (!value) return null;
    const [year, month, day] = value.split('-').map(Number);
    return Math.ceil((new Date(year, month - 1, day) - new Date().setHours(0, 0, 0, 0)) / 86400000);
};

/**
 * Satu sumber untuk nada dan kalimat tiap keadaan langganan. Ringkasan di sini
 * sengaja lebih pendek daripada halaman `/langganan` — tugasnya cuma memberi
 * tahu owner bahwa ada sesuatu yang perlu dilihat, dan menunjukkan jalannya.
 */
const billingState = computed(() => {
    switch (props.subscription?.status) {
        case 'trial':
            return {
                tone: 'neutral',
                label: 'Masa coba',
                body: `Semua fitur terbuka sampai ${formatCalendarDate(props.subscription.trial_ends_at)}.`,
            };
        case 'active':
            return {
                tone: 'neutral',
                label: 'Langganan aktif',
                body: `Periode berjalan sampai ${formatCalendarDate(props.subscription.period_ends_at)}.`,
            };
        case 'grace':
            return {
                tone: 'warning',
                label: 'Masa tenggang',
                body:
                    'Transaksi dan perubahan baru tidak bisa disimpan. Akses ditutup sepenuhnya pada '
                    + `${formatCalendarDate(props.subscription.suspends_at)}.`,
            };
        case 'suspended':
            return {
                tone: 'danger',
                label: 'Ditangguhkan',
                body: 'Selesaikan pembayaran untuk membuka kembali akses. Data Anda tidak dihapus.',
            };
        default:
            return { tone: 'neutral', label: 'Langganan', body: '' };
    }
});

const billingTones = {
    neutral: {
        card: 'bg-white border-gray-200', label: 'text-gray-700', body: 'text-gray-500',
        divider: 'border-gray-100', chip: 'bg-gray-100 text-gray-600',
    },
    warning: {
        card: 'bg-amber-50 border-amber-200', label: 'text-amber-800', body: 'text-amber-700',
        divider: 'border-amber-200', chip: 'bg-amber-100 text-amber-800',
    },
    danger: {
        card: 'bg-red-50 border-red-200', label: 'text-red-800', body: 'text-red-700',
        divider: 'border-red-200', chip: 'bg-red-100 text-red-800',
    },
};

const billingTone = computed(() => billingTones[billingState.value.tone]);

const trialDaysLeft = computed(() =>
    props.subscription?.status === 'trial' ? daysUntil(props.subscription.trial_ends_at) : null,
);

const invoiceStatusLabels = {
    unpaid: 'Belum dibayar',
    awaiting_verification: 'Menunggu diperiksa',
    rejected: 'Bukti ditolak',
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

        <!-- Ringkasan Langganan -->
        <div
            v-if="subscription"
            :class="['rounded-xl shadow-sm border p-5', billingTone.card]"
        >
            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-2">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold" :class="billingTone.label">{{ billingState.label }}</p>
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="billingTone.chip">
                            {{ subscription.track === 'subsidized' ? 'Harga Adaptif' : 'Harga Tetap' }}
                        </span>
                        <span
                            v-if="trialDaysLeft !== null"
                            class="text-xs px-2 py-0.5 rounded-full font-medium tabular-nums"
                            :class="billingTone.chip"
                        >
                            Sisa {{ trialDaysLeft }} hari
                        </span>
                    </div>
                    <p class="mt-1 text-sm" :class="billingTone.body">{{ billingState.body }}</p>
                </div>
                <Link href="/langganan" class="text-xs text-primary hover:text-primary/80 font-medium whitespace-nowrap">
                    Kelola Langganan →
                </Link>
            </div>

            <!-- Tagihan berjalan. Absen berarti tidak ada yang perlu dibayar —
                 dan dalam hal itu diam lebih jujur daripada menampilkan "Rp 0". -->
            <div
                v-if="subscription.outstanding"
                class="mt-4 pt-4 border-t flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
                :class="billingTone.divider"
            >
                <div class="flex flex-wrap items-baseline gap-2">
                    <span class="text-sm font-semibold text-gray-900 tabular-nums">
                        {{ formatCurrency(subscription.outstanding.amount) }}
                    </span>
                    <span class="text-xs px-2 py-0.5 rounded-full font-medium" :class="billingTone.chip">
                        {{ invoiceStatusLabels[subscription.outstanding.status] }}
                    </span>
                    <span v-if="subscription.outstanding.kind === 'upgrade'" class="text-xs text-gray-500">
                        penambahan pengguna
                    </span>
                </div>
                <span v-if="subscription.outstanding.due_date" class="text-xs" :class="billingTone.body">
                    Jatuh tempo {{ formatCalendarDate(subscription.outstanding.due_date) }}
                </span>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <MetricCard
                title="Pendapatan Hari Ini"
                :value="formatCurrency(metrics.today_revenue)"
                icon="currency"
                color="success"
            />
            <MetricCard
                title="Transaksi Hari Ini"
                :value="metrics.today_count"
                subtitle="transaksi"
                icon="receipt"
                color="primary"
            />
            <MetricCard
                title="Rata-rata / Trx"
                :value="formatCurrency(metrics.today_average)"
                icon="average"
                color="muted"
            />
            <MetricCard
                title="Minggu Ini"
                :value="formatCurrency(metrics.week_revenue)"
                icon="chart"
                color="primary"
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
                            'bg-success/10 text-success': pm.type === 'cash',
                            'bg-primary/10 text-primary': pm.type === 'qris_static',
                            'bg-secondary text-secondary-foreground': pm.type === 'bank_transfer',
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
        <div v-if="badges.length > 0" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-semibold text-gray-700">Alert & Notifikasi</h3>
                <span class="text-xs text-gray-400">{{ badges.length }} kartu</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-start">
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
                <Link href="/owner/transactions" class="text-xs text-primary hover:text-primary/80 font-medium">
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
                        <div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-medium text-gray-800">{{ tx.code }}</p>
                                <span
                                    v-if="tx.source === 'self_order'"
                                    class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full font-medium"
                                >
                                    Self Order
                                </span>
                            </div>
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
            <Link href="/owner/reports/daily" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md hover:border-primary/30 transition-all text-center group">
                <svg class="w-6 h-6 mx-auto text-primary mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-xs font-medium text-gray-700 group-hover:text-primary">Laporan Harian</p>
            </Link>
            <Link href="/owner/transactions" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md hover:border-primary/30 transition-all text-center group">
                <svg class="w-6 h-6 mx-auto text-primary mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <p class="text-xs font-medium text-gray-700 group-hover:text-primary">Riwayat Transaksi</p>
            </Link>
            <Link href="/owner/cash-drawers" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md hover:border-primary/30 transition-all text-center group">
                <svg class="w-6 h-6 mx-auto text-primary mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <p class="text-xs font-medium text-gray-700 group-hover:text-primary">Riwayat Kas</p>
            </Link>
            <Link href="/owner/stock" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md hover:border-primary/30 transition-all text-center group">
                <svg class="w-6 h-6 mx-auto text-primary mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                </svg>
                <p class="text-xs font-medium text-gray-700 group-hover:text-primary">Kelola Stok</p>
            </Link>
        </div>
    </div>
</template>
