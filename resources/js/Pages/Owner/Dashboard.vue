<script setup>
import { computed } from 'vue';
import { Deferred, Head, Link } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import MetricCard from '@/Components/MetricCard.vue';
import BadgeCard from '@/Components/BadgeCard.vue';
import DailyChart from '@/Components/DailyChart.vue';
import SkeletonPanel from '@/Components/Skeleton/SkeletonPanel.vue';
import SkeletonGrid from '@/Components/Skeleton/SkeletonGrid.vue';
import SkeletonCard from '@/Components/Skeleton/SkeletonCard.vue';
import SkeletonChart from '@/Components/Skeleton/SkeletonChart.vue';
import SkeletonList from '@/Components/Skeleton/SkeletonList.vue';
import { BUSINESS_TZ, businessToday, parseDateOnly } from '@/support/date';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    metrics: Object,
    dailyTrend: Array,
    badges: Array,
    // Sinyal pagi ([BL-105] butir 3) — null selama masih dimuat.
    pressedToday: { type: Object, default: null },
    recentTransactions: Array,
    subscription: Object,
});

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

const formatDate = (datetime) => {
    return new Date(datetime).toLocaleDateString('id-ID', {
        timeZone: BUSINESS_TZ,
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
    return Math.ceil((new Date(year, month - 1, day) - parseDateOnly(businessToday())) / 86400000);
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

/** Bulan kalender polos ('2026-07') — dirakit komponennya sendiri, sama alasannya. */
const formatMonth = (value) => {
    if (!value) return null;
    const [year, month] = value.split('-').map(Number);
    return new Date(year, month - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
};

/**
 * Momen pilihan jalur di akhir masa coba (`[BL-044]`(c)).
 *
 * Server yang memutuskan kapan ia muncul dan jalur mana yang terbuka — halaman
 * ini hanya merangkai kalimatnya. Kelayakan yang dihitung ulang di sisi klien
 * akan menawarkan pintu yang ditolak server begitu ambangnya digeser, dan
 * tenant menemukannya hanya setelah menekan tombolnya.
 */
const trialChoice = computed(() => props.subscription?.trial_choice ?? null);

/**
 * Kapan angkanya mengeras. Tagihan pertama terbit tujuh hari sebelum masa coba
 * habis dan tidak pernah diterbitkan dua kali, jadi pilihan yang diambil
 * sesudahnya berlaku pada tagihan BERIKUTNYA. Dikatakan apa adanya di kedua
 * sisi tanggal itu — tenant yang mengira keringanannya berlaku bulan ini akan
 * membaca tagihannya sebagai kesalahan sistem.
 */
const trialChoiceNote = computed(() => {
    const choice = trialChoice.value;
    if (!choice) return null;

    return choice.first_invoice_issued
        ? `Tagihan pertama sudah terbit pada ${formatCalendarDate(choice.first_invoice_at)}. Pindah jalur sekarang tetap bisa, tapi berlakunya pada tagihan berikutnya — bukan yang sedang berjalan.`
        : `Masa coba berakhir ${formatCalendarDate(choice.trial_ends_at)}, dan tagihan pertama terbit ${formatCalendarDate(choice.first_invoice_at)}. Pilihan yang diambil sebelum tanggal itu langsung berlaku pada tagihan pertama.`;
});

/** Tarif perkiraan jalur Adaptif, atau null bila belum bisa dihitung. */
const adaptivePrice = computed(() => {
    const estimate = trialChoice.value?.adaptive?.estimate;
    if (!estimate || estimate.transaction_count === 0 || estimate.price === null) return null;
    return estimate.price;
});

/** Satu kalimat penjelas di bawah tarif Adaptif. */
const adaptiveNote = computed(() => {
    const estimate = trialChoice.value?.adaptive?.estimate;
    if (!estimate) return null;

    const month = formatMonth(estimate.period);

    // Nol penjualan BUKAN omzet nol. Membiarkannya jatuh ke kelompok termurah
    // akan menjanjikan tarif yang tidak seorang pun bisa penuhi janjinya.
    if (estimate.transaction_count === 0) {
        return `Belum ada penjualan tercatat sepanjang ${month}, jadi tarifnya belum bisa diperkirakan. Jalurnya tetap terbuka untuk diajukan.`;
    }

    if (estimate.price === null) {
        return `Omzet Anda ${formatCurrency(estimate.revenue)} pada ${month}, tapi belum ada kelompok tarif yang cocok untuknya.`;
    }

    return estimate.is_cheaper
        ? `Perkiraan dari omzet ${formatCurrency(estimate.revenue)} pada ${month} — ${formatCurrency(estimate.current_price - estimate.price)} lebih murah, dengan syarat data omzet Anda dibuka kepada kami.`
        : `Perkiraan dari omzet ${formatCurrency(estimate.revenue)} pada ${month} — tidak lebih murah daripada Harga Tetap, jadi pindah jalur belum menguntungkan Anda.`;
});

/**
 * Kenapa jalur Adaptif tertutup. Kalimatnya sengaja sejalan dengan halaman
 * `/langganan`: dua jawaban berbeda atas pertanyaan yang sama membuat tenant
 * bertanya mana yang benar.
 */
const adaptiveBlocked = computed(() => {
    const adaptive = trialChoice.value?.adaptive;
    if (!adaptive || adaptive.eligible) return null;

    if (adaptive.reason === 'above_ceiling') {
        return `Omzet Anda ${formatCurrency(adaptive.revenue)} — di atas batas ${formatCurrency(adaptive.ceiling)} untuk keringanan. Jalur yang berlaku bagi Anda adalah paket berbayar penuh.`;
    }

    if (adaptive.reason === 'cooldown') {
        return `Perpindahan jalur berikutnya baru bisa diajukan mulai ${formatCalendarDate(adaptive.available_at)}.`;
    }

    return 'Anda sudah berada di jalur Harga Adaptif — tidak ada yang perlu diajukan.';
});

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

            <!-- Momen pilihan jalur di akhir masa coba (BL-044(c)). Kedua jalur
                 berdampingan berikut angkanya: peringatan yang hanya berkata
                 "masa coba Anda akan habis" memberi tahu tanpa memberi jalan. -->
            <div
                v-if="trialChoice"
                class="mt-4 pt-4 border-t"
                :class="billingTone.divider"
            >
                <p class="text-sm font-semibold text-gray-900">Pilih jalur harga Anda</p>
                <p class="mt-1 text-xs" :class="billingTone.body">{{ trialChoiceNote }}</p>

                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <!-- Jalur yang berlaku sendiri bila tenant tidak memilih apa
                         pun. Disebut lebih dulu justru karena itu: pilihan diam
                         tetap sebuah pilihan, dan tenant berhak tahu isinya. -->
                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <p class="text-xs font-semibold text-gray-700">Harga Tetap</p>
                        <p class="mt-0.5 text-sm font-semibold text-gray-900 tabular-nums">
                            {{ formatCurrency(trialChoice.fixed.base_price) }}<span class="text-xs font-normal text-gray-500">/bulan</span>
                        </p>
                        <p class="mt-1 text-xs text-gray-500">
                            Paket {{ trialChoice.fixed.name }}. Berlaku sendiri bila Anda tidak memilih apa pun, dan data penjualan Anda tetap tertutup.
                        </p>
                    </div>

                    <div
                        v-if="trialChoice.adaptive.eligible"
                        class="rounded-lg border border-emerald-200 bg-emerald-50 p-3"
                    >
                        <p class="text-xs font-semibold text-emerald-800">Harga Adaptif</p>
                        <p class="mt-0.5 text-sm font-semibold text-emerald-900 tabular-nums">
                            <template v-if="adaptivePrice !== null">
                                {{ formatCurrency(adaptivePrice) }}<span class="text-xs font-normal text-emerald-700">/bulan</span>
                            </template>
                            <template v-else>Belum bisa diperkirakan</template>
                        </p>
                        <p class="mt-1 text-xs text-emerald-700">{{ adaptiveNote }}</p>
                        <Link
                            href="/langganan/harga-adaptif"
                            class="mt-2 inline-block text-xs font-medium text-emerald-800 hover:text-emerald-900"
                        >
                            Lihat &amp; ajukan Harga Adaptif →
                        </Link>
                    </div>

                    <!-- Jalur tertutup tetap ditampilkan, berikut sebabnya.
                         Menghilangkannya membuat tenant mengira ia tidak pernah
                         ditawari, lalu menanyakannya lewat dukungan. -->
                    <div v-else class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                        <p class="text-xs font-semibold text-gray-700">Harga Adaptif tidak tersedia</p>
                        <p class="mt-1 text-xs text-gray-500">{{ adaptiveBlocked }}</p>
                    </div>
                </div>
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

        <!-- Sinyal pagi ([BL-105] butir 3). Di ATAS lencana, dan itu urutan yang
             disengaja: lencana menjawab "apa keadaannya", kartu ini menjawab
             "apa yang harus saya kerjakan hari ini". Yang kedua lebih dulu.

             Ia sengaja tumpang tindih sebagian dengan lencana "Mendekati
             Expired" dan "Dead Stock" — bedanya ada di kolom terakhir, dan
             itulah seluruh gunanya: barang tertekan yang belum punya potongan
             tetap disarankan kasir, tapi pada harga katalog. Kalau suatu hari
             pengulangannya terasa berisik, yang dicabut lencananya, bukan kartu
             ini. -->
        <Deferred data="badges">
            <template #fallback>
                <SkeletonCard icon :lines="2" padding="p-5" />
            </template>

            <div
                v-if="pressedToday && pressedToday.count > 0"
                class="bg-white rounded-xl shadow-sm border border-warning/30 ring-1 ring-warning/20 p-5 space-y-4"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <!-- Namanya ikut di sini, bukan cuma di laporannya
                             ([BL-105] butir 4). Owner bertemu rantai yang sama
                             di dua layar; tanpa nama yang sama di keduanya, ia
                             tidak punya cara tahu bahwa keduanya satu hal. -->
                        <h3 class="text-sm font-semibold text-gray-800">
                            Penyelamat Stok <span class="font-normal text-gray-400">· perlu keluar hari ini</span>
                        </h3>
                        <p class="mt-1 text-2xl font-bold text-gray-900">
                            {{ pressedToday.count }} barang · {{ formatCurrency(pressedToday.value) }} modal
                        </p>
                    </div>

                    <!-- Kalimat yang jadi alasan kartu ini ada. Angka "belum
                         punya potongan" tidak muncul di layar mana pun sebelum
                         ini, padahal ia yang menentukan apakah saran kasir
                         punya peluang atau cuma basa-basi. -->
                    <div class="text-right">
                        <p v-if="pressedToday.unarmed > 0" class="text-sm text-warning-foreground">
                            <span class="font-semibold">{{ pressedToday.unarmed }}</span> belum punya potongan otomatis
                        </p>
                        <p v-else class="text-sm text-success">Semuanya sudah punya potongan otomatis</p>
                        <Link
                            href="/owner/discount-rules"
                            class="mt-1 inline-block text-xs font-medium text-primary hover:underline"
                        >
                            Atur potongan →
                        </Link>
                    </div>
                </div>

                <ul class="divide-y divide-gray-100">
                    <li
                        v-for="item in pressedToday.items"
                        :key="item.variant_id"
                        class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900">{{ item.label }}</p>
                            <p class="text-xs text-gray-500">{{ item.note }} · sisa {{ item.stock }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-gray-700">{{ formatCurrency(item.value) }}</span>
                            <span
                                :class="[
                                    'rounded px-1.5 py-0.5 text-xs font-medium',
                                    item.armed
                                        ? 'bg-success/15 text-success'
                                        : 'bg-warning/15 text-warning-foreground',
                                ]"
                            >
                                {{ item.armed ? 'Potongan aktif' : 'Belum ada potongan' }}
                            </span>
                        </div>
                    </li>
                </ul>

                <p class="text-xs text-gray-400">
                    <template v-if="pressedToday.count > pressedToday.items.length">
                        Menampilkan {{ pressedToday.items.length }} dari {{ pressedToday.count }} —
                        <Link href="/owner/stock?status=near_expiry" class="text-primary hover:underline">lihat semua di Stok</Link>
                        ·
                    </template>
                    <Link href="/owner/reports/upsell" class="text-primary hover:underline">Berapa yang berhasil diselamatkan?</Link>
                </p>
            </div>
        </Deferred>

        <!-- Badges. Ditunda: kartunya lahir dari agregat paling berat di halaman
             ini, dan bukan angka pertama yang dicari owner saat membuka layar. -->
        <Deferred data="badges">
            <template #fallback>
                <SkeletonPanel label="Memuat alert & notifikasi…">
                    <SkeletonGrid :count="2" columns="grid-cols-1 md:grid-cols-2">
                        <SkeletonCard icon :lines="2" padding="p-4" />
                    </SkeletonGrid>
                </SkeletonPanel>
            </template>

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
        </Deferred>

        <!-- Chart Trend 7 Hari. Kerangkanya setinggi kanvas aslinya (h-64) supaya
             panel di bawahnya tidak melompat saat grafiknya jadi. -->
        <Deferred data="dailyTrend">
            <template #fallback>
                <SkeletonPanel label="Memuat grafik 7 hari…">
                    <SkeletonChart :bars="7" height-class="h-64" />
                </SkeletonPanel>
            </template>

            <DailyChart :data="dailyTrend" />
        </Deferred>

        <!-- Transaksi Terbaru -->
        <Deferred data="recentTransactions">
            <template #fallback>
                <SkeletonPanel flush action label="Memuat transaksi terbaru…">
                    <SkeletonList :rows="5" />
                </SkeletonPanel>
            </template>

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
        </Deferred>

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
