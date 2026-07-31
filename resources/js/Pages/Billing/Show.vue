<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    tenant: { type: Object, required: true },
    subscription: { type: Object, required: true },
    consent: { type: Object, required: true },
    subsidy: { type: Object, required: true },
    invoices: { type: Array, required: true },
    upgrade: { type: Object, required: true },
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

// --- Tambah pengguna ---
const upgradeForm = useForm({ additional_seats: 1 });

const submitUpgrade = () => upgradeForm.post('/langganan/tambah-pengguna', { preserveScroll: true });

const upgradeCost = computed(() => formatRupiah(props.upgrade.extra_seat_price * upgradeForm.additional_seats));

// --- Unggah bukti bayar ---
const proofTarget = ref(null);
const proofForm = useForm({ proof: null });

const openProof = (invoice) => {
    proofForm.reset();
    proofForm.clearErrors();
    proofTarget.value = invoice;
};

const submitProof = () => {
    proofForm.post(`/langganan/tagihan/${proofTarget.value.id}/bukti`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { proofTarget.value = null; },
    });
};

// --- Jalur Harga Adaptif ---
const revokeForm = useForm({});
const confirmingRevoke = ref(false);

const revokeSubsidy = () => {
    revokeForm.post('/langganan/subsidi/cabut', {
        preserveScroll: true,
        onSuccess: () => { confirmingRevoke.value = false; },
    });
};

const invoiceStatusLabels = {
    unpaid: 'Belum dibayar',
    awaiting_verification: 'Menunggu diperiksa',
    paid: 'Lunas',
    rejected: 'Ditolak',
};
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

            <!-- Jalur Harga Adaptif -->
            <div class="mt-6 rounded-xl border border-border bg-card px-5 py-4">
                <p class="text-sm font-medium text-foreground">
                    {{ subsidy.is_active ? 'Harga Adaptif — aktif' : 'Harga Adaptif' }}
                </p>

                <template v-if="subsidy.is_active">
                    <p v-if="subsidy.bracket" class="mt-1 text-sm text-muted-foreground leading-relaxed">
                        Kelompok <span class="font-medium text-foreground">{{ subsidy.bracket.label }}</span>
                        berdasarkan omzet {{ formatRupiah(subsidy.bracket.revenue) }} pada periode
                        {{ subsidy.bracket.period }} — tarif {{ formatRupiah(subsidy.bracket.price) }}/bulan.
                    </p>
                    <p v-else class="mt-1 text-sm text-muted-foreground leading-relaxed">
                        Omzet Anda belum dihitung. Perhitungan pertama berjalan di awal bulan berikutnya.
                    </p>

                    <p v-if="subsidy.reverts_at" class="mt-2 text-sm text-foreground">
                        Persetujuan sudah dicabut. Tarif adaptif berlaku sampai {{ formatDate(subsidy.reverts_at) }},
                        setelah itu kembali ke Harga Tetap.
                    </p>

                    <div v-else-if="tenant.is_owner" class="mt-3">
                        <button
                            v-if="!confirmingRevoke"
                            class="text-sm font-medium text-destructive hover:text-destructive/80"
                            @click="confirmingRevoke = true"
                        >
                            Cabut persetujuan Harga Adaptif
                        </button>

                        <div v-else class="rounded-lg border border-destructive/40 bg-destructive/10 px-3.5 py-3">
                            <p class="text-sm text-foreground leading-relaxed">
                                Ringkasan omzet Anda dihapus seketika. Tarif adaptif tetap berlaku sampai akhir
                                periode berjalan, jadi tagihan Anda tidak naik mendadak.
                            </p>
                            <div class="mt-3 flex gap-3">
                                <button
                                    :disabled="revokeForm.processing"
                                    class="px-3 py-1.5 text-xs font-medium bg-destructive text-white rounded-lg hover:bg-destructive/90 disabled:opacity-50"
                                    @click="revokeSubsidy"
                                >
                                    Ya, cabut
                                </button>
                                <button class="px-3 py-1.5 text-xs font-medium text-foreground border border-border rounded-lg hover:bg-accent/40" @click="confirmingRevoke = false">
                                    Batal
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <p class="mt-1 text-sm text-muted-foreground leading-relaxed">
                        Tarif yang mengikuti omzet usaha Anda, bukan daftar harga tetap. Sebagai gantinya, omzet
                        bulanan Anda dihitung otomatis dan angka persisnya bisa dilihat pengelola layanan untuk
                        menentukan tarif. Bacalah dokumennya sebelum memutuskan.
                    </p>

                    <p v-if="!subsidy.can_switch" class="mt-2 text-sm text-foreground">
                        Perpindahan jalur berikutnya bisa diajukan mulai {{ formatDate(subsidy.switch_available_at) }}.
                    </p>
                    <Link
                        v-else-if="tenant.is_owner"
                        href="/langganan/persetujuan/subsidized"
                        class="mt-3 inline-block text-sm font-medium text-primary hover:text-primary/80 transition-colors duration-150"
                    >
                        Baca ketentuan Harga Adaptif
                    </Link>
                </template>
            </div>

            <!-- Tambah pengguna -->
            <div v-if="tenant.is_owner" class="mt-6 rounded-xl border border-border bg-card px-5 py-4">
                <p class="text-sm font-medium text-foreground">Tambah pengguna</p>

                <p v-if="upgrade.is_provisional_blocked" class="mt-1 text-sm text-muted-foreground leading-relaxed">
                    Bukti bayar Anda pernah ditolak, jadi penambahan pengguna kini baru berlaku setelah bukti
                    transfernya kami periksa.
                </p>
                <p v-else class="mt-1 text-sm text-muted-foreground leading-relaxed">
                    Unggah bukti transfernya dan penggunanya langsung aktif — pemeriksaan menyusul.
                </p>

                <p v-if="upgrade.has_open_request" class="mt-3 text-sm text-foreground">
                    Ada permintaan penambahan yang belum selesai. Selesaikan tagihannya di bawah dulu.
                </p>

                <form v-else class="mt-3 flex flex-wrap items-end gap-3" @submit.prevent="submitUpgrade">
                    <div>
                        <label for="additional-seats" class="block text-xs font-medium text-muted-foreground mb-1">Jumlah</label>
                        <input
                            id="additional-seats"
                            v-model.number="upgradeForm.additional_seats"
                            type="number"
                            min="1"
                            max="20"
                            class="w-24 px-3 py-2 border border-border rounded-lg text-sm bg-card text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="upgradeForm.processing"
                        class="px-4 py-2 bg-primary text-primary-foreground text-sm font-semibold rounded-lg hover:bg-primary/90 disabled:opacity-50"
                    >
                        Terbitkan tagihan · {{ upgradeCost }}
                    </button>
                </form>

                <p v-if="upgradeForm.errors.additional_seats" role="alert" class="mt-2 text-xs text-destructive">
                    {{ upgradeForm.errors.additional_seats }}
                </p>
            </div>

            <!-- Tagihan -->
            <div class="mt-6 rounded-xl border border-border bg-card overflow-hidden">
                <p class="px-5 pt-4 text-sm font-medium text-foreground">Tagihan</p>

                <ul class="mt-2 divide-y divide-border">
                    <li v-for="invoice in invoices" :key="invoice.id" class="px-5 py-3.5">
                        <div class="flex items-baseline justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-foreground">
                                    {{ invoice.kind === 'upgrade' ? 'Tambah pengguna' : 'Langganan' }} · {{ invoice.period }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ invoiceStatusLabels[invoice.status] }} · jatuh tempo {{ formatDate(invoice.due_date) }}
                                </p>
                                <p v-if="invoice.rejection_reason" class="mt-1 text-xs text-destructive">
                                    {{ invoice.rejection_reason }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-medium text-foreground tabular-nums">{{ formatRupiah(invoice.amount) }}</p>
                                <button
                                    v-if="tenant.is_owner && invoice.status !== 'paid'"
                                    class="mt-1 text-xs font-medium text-primary hover:text-primary/80"
                                    @click="openProof(invoice)"
                                >
                                    {{ invoice.has_proof ? 'Unggah ulang bukti' : 'Unggah bukti transfer' }}
                                </button>
                            </div>
                        </div>
                    </li>

                    <li v-if="invoices.length === 0" class="px-5 py-6 text-sm text-muted-foreground">
                        Belum ada tagihan.
                    </li>
                </ul>
            </div>

            <p class="mt-6 text-xs text-muted-foreground leading-relaxed">
                Pembayaran masih dicatat manual: transfer, lalu unggah buktinya di sini. Kami periksa dan
                mengonfirmasi menyusul.
            </p>

            <!-- Unggah bukti -->
            <Teleport to="body">
                <div v-if="proofTarget" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="proofTarget = null" />
                    <div class="relative w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-2xl">
                        <h3 class="text-lg font-semibold text-foreground mb-1">Unggah Bukti Transfer</h3>
                        <p class="text-sm text-muted-foreground mb-4">
                            {{ proofTarget.period }} · {{ formatRupiah(proofTarget.amount) }}
                        </p>

                        <form class="space-y-4" @submit.prevent="submitProof">
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.pdf"
                                class="w-full text-sm text-foreground file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border file:border-border file:bg-card file:text-sm file:text-foreground"
                                @input="proofForm.proof = $event.target.files[0]"
                            />
                            <p v-if="proofForm.errors.proof" role="alert" class="text-xs text-destructive">
                                {{ proofForm.errors.proof }}
                            </p>

                            <div class="flex justify-end gap-3">
                                <button type="button" class="px-4 py-2 text-sm font-medium text-foreground border border-border rounded-lg hover:bg-accent/40" @click="proofTarget = null">
                                    Batal
                                </button>
                                <button type="submit" :disabled="proofForm.processing" class="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 disabled:opacity-50">
                                    {{ proofForm.processing ? 'Mengunggah...' : 'Unggah' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>

            <p v-if="tenant.status !== 'suspended'" class="mt-8 text-sm">
                <Link :href="backHref" class="font-medium text-primary hover:text-primary/80 transition-colors duration-150">
                    Kembali ke aplikasi
                </Link>
            </p>
        </div>
    </div>
</template>
