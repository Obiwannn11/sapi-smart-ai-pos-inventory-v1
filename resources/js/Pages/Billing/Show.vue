<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    tenant: { type: Object, required: true },
    subscription: { type: Object, required: true },
    consent: { type: Object, required: true },
});

const page = usePage();
const flashError = computed(() => page.props.flash?.error);

/**
 * Server mengirim tanggal kalender polos ('2026-08-21'). `new Date(string)`
 * membacanya sebagai tengah malam UTC, sehingga tampilannya bergantung pada
 * zona waktu peramban — di zona yang di belakang UTC hasilnya mundur sehari.
 * Zona Indonesia kebetulan aman, tapi tanggal jatuh tempo yang benar hanya
 * karena kebetulan bukan tanggal yang benar. Komponennya dirakit sendiri agar
 * selalu dibaca sebagai tanggal lokal.
 */
const parseDate = (value) => {
    if (!value) return null;
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const formatDate = (value) =>
    parseDate(value)?.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) ?? null;

const formatRupiah = (value) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);

const daysUntil = (value) => {
    const date = parseDate(value);
    if (!date) return null;
    return Math.ceil((date - new Date().setHours(0, 0, 0, 0)) / 86400000);
};

/**
 * Satu sumber untuk judul, nada, dan penjelasan tiap keadaan. Dipisah dari
 * template supaya kalimatnya bisa dibaca berdampingan — nada yang melompat
 * antar keadaan justru terlihat di sini, bukan saat tersebar di markup.
 */
const state = computed(() => {
    const trialEnds = formatDate(props.subscription.trial_ends_at);
    const periodEnds = formatDate(props.subscription.current_period_end);
    const suspendsAt = formatDate(props.subscription.suspends_at);

    switch (props.tenant.status) {
        case 'trial':
            return {
                tone: 'neutral',
                label: 'Masa coba',
                heading: 'Anda sedang dalam masa coba',
                body: `Semua fitur terbuka sampai ${trialEnds}. Menjelang tanggal itu Anda bisa memilih untuk berlangganan.`,
            };
        case 'active':
            return {
                tone: 'positive',
                label: 'Aktif',
                heading: 'Langganan Anda aktif',
                body: `Periode berjalan sampai ${periodEnds}.`,
            };
        case 'grace':
            return {
                tone: 'warning',
                label: 'Masa tenggang',
                heading: 'Masa langganan sudah berakhir',
                body:
                    `Data lama tetap bisa dibuka dan diunduh, tapi transaksi dan perubahan baru tidak bisa disimpan. ` +
                    `Bila belum diselesaikan sampai ${suspendsAt}, akses akan ditutup sepenuhnya.`,
            };
        case 'suspended':
            return {
                tone: 'critical',
                label: 'Ditangguhkan',
                heading: 'Akses ditangguhkan',
                body: 'Selesaikan pembayaran untuk membuka kembali akses. Data Anda tidak dihapus.',
            };
        default:
            return { tone: 'neutral', label: '—', heading: 'Status langganan', body: '' };
    }
});

const toneClasses = {
    neutral: 'border-border bg-card text-foreground',
    positive: 'border-border bg-card text-foreground',
    warning: 'border-amber-500/40 bg-amber-500/10 text-foreground',
    critical: 'border-destructive/40 bg-destructive/10 text-foreground',
};

const trialDaysLeft = computed(() =>
    props.tenant.status === 'trial' ? daysUntil(props.subscription.trial_ends_at) : null,
);

const backHref = computed(() => (props.tenant.is_owner ? '/owner/dashboard' : '/cashier/pos'));
</script>

<template>
    <Head title="Langganan" />

    <div class="min-h-screen bg-background px-6 py-14">
        <div class="mx-auto w-full max-w-lg">

            <div class="flex items-baseline gap-2">
                <span class="text-xl font-bold text-foreground tracking-tight leading-none">SAPI</span>
                <span class="text-[0.65rem] font-semibold text-muted-foreground uppercase tracking-widest">POS</span>
            </div>

            <h1 class="mt-8 text-2xl font-bold text-foreground tracking-tight">Langganan</h1>
            <p class="mt-1.5 text-sm text-muted-foreground">{{ tenant.name }}</p>

            <div v-if="flashError" role="alert" class="mt-6 rounded-lg border border-destructive/40 bg-destructive/10 px-3.5 py-3 text-sm text-foreground">
                {{ flashError }}
            </div>

            <div :class="['mt-6 rounded-xl border px-5 py-5', toneClasses[state.tone]]">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ state.label }}</p>
                <h2 class="mt-2 text-lg font-semibold text-foreground">{{ state.heading }}</h2>
                <p class="mt-2 text-sm text-muted-foreground leading-relaxed">{{ state.body }}</p>

                <p v-if="trialDaysLeft !== null" class="mt-3 text-sm font-medium text-foreground tabular-nums">
                    Sisa {{ trialDaysLeft }} hari
                </p>
            </div>

            <dl class="mt-6 divide-y divide-border rounded-xl border border-border bg-card">
                <div class="flex items-baseline justify-between px-5 py-3.5">
                    <dt class="text-sm text-muted-foreground">Paket</dt>
                    <dd class="text-sm font-medium text-foreground">{{ subscription.plan_name }}</dd>
                </div>
                <div class="flex items-baseline justify-between px-5 py-3.5">
                    <dt class="text-sm text-muted-foreground">Tarif</dt>
                    <dd class="text-sm font-medium text-foreground tabular-nums">
                        {{ formatRupiah(subscription.price_locked ?? subscription.base_price) }}
                        <span class="text-muted-foreground font-normal">/bulan</span>
                    </dd>
                </div>
                <div class="flex items-baseline justify-between px-5 py-3.5">
                    <dt class="text-sm text-muted-foreground">Pengguna aktif</dt>
                    <dd class="text-sm font-medium text-foreground tabular-nums">
                        {{ subscription.seats_used }} dari {{ subscription.seats }}
                    </dd>
                </div>
            </dl>

            <div class="mt-6 rounded-xl border border-border bg-card px-5 py-4">
                <p class="text-sm font-medium text-foreground">Persetujuan langganan</p>
                <p class="mt-1 text-sm text-muted-foreground leading-relaxed">
                    <template v-if="consent.agreed">
                        Sudah disetujui. Anda bisa membacanya kembali kapan saja.
                    </template>
                    <template v-else>
                        Belum disetujui. Dokumen ini menjelaskan apa yang kami lihat dan apa yang tidak.
                    </template>
                </p>
                <Link
                    href="/langganan/persetujuan"
                    class="mt-3 inline-block text-sm font-medium text-primary hover:text-primary/80 transition-colors duration-150"
                >
                    {{ consent.agreed ? 'Baca dokumen persetujuan' : 'Baca dan setujui' }}
                </Link>
            </div>

            <p class="mt-6 text-xs text-muted-foreground leading-relaxed">
                Pembayaran masih dicatat manual. Hubungi pengelola layanan untuk menyelesaikan tagihan —
                pencatatan mandiri dari halaman ini menyusul.
            </p>

            <p v-if="tenant.status !== 'suspended'" class="mt-8 text-sm">
                <Link :href="backHref" class="font-medium text-primary hover:text-primary/80 transition-colors duration-150">
                    Kembali ke aplikasi
                </Link>
            </p>
        </div>
    </div>
</template>
