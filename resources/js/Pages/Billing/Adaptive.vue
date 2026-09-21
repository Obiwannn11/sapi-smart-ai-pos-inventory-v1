<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';

// Cangkang yang sama dengan halaman langganan, dan alasannya sama: tenant yang
// ditangguhkan diarahkan ke sini, jadi halaman ini tidak boleh jadi pulau
// tersendiri. Terbuka untuk staf juga — yang digerbang owner adalah tindakan
// menyetujuinya, bukan membaca alasannya.
defineOptions({ layout: OwnerLayout });

const props = defineProps({
    tenant: { type: Object, required: true },
    current: { type: Object, required: true },
    ladder: { type: Array, required: true },
    verdict: { type: Object, required: true },
    bracket: { type: Object, default: null },
    estimate: { type: Object, default: null },
    consent: { type: Object, required: true },
    switch_minimum_months: { type: Number, required: true },
});

/**
 * Tanggal kalender polos dirakit sendiri, bukan lewat `new Date(string)` —
 * yang membacanya sebagai tengah malam UTC dan mundur sehari di zona yang di
 * belakangnya. Sama persis dengan Billing/Show.vue; keduanya menampilkan
 * tanggal yang sama kepada orang yang sama.
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

const formatMonth = (value) => {
    if (!value) return null;
    const [year, month] = value.split('-').map(Number);
    return new Date(year, month - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
};

/** Batas atas eksklusif — 'di bawah Rp 2 jt', bukan 'sampai Rp 2 jt'. */
const rangeLabel = (bracket) => {
    if (bracket.min === null && bracket.max === null) return 'Semua omzet';
    if (bracket.min === null) return `Di bawah ${formatRupiah(bracket.max)}`;
    if (bracket.max === null) return `${formatRupiah(bracket.min)} ke atas`;
    return `${formatRupiah(bracket.min)} – di bawah ${formatRupiah(bracket.max)}`;
};

/** Omzet yang jadi dasar penilaian: tercatat bila sudah di dalam, perkiraan bila belum. */
const measuredRevenue = computed(() => props.bracket?.revenue ?? props.estimate?.revenue ?? null);

/** Anak tangga mana yang sedang berlaku (atau akan berlaku) bagi tenant ini. */
const activeLabel = computed(() => props.bracket?.label ?? props.estimate?.label ?? null);

/**
 * Satu sumber untuk judul, nada, dan jalan keluar tiap verdict.
 *
 * Ketiga penolakan punya jalan keluar yang berbeda — menunggu, membayar penuh,
 * atau tidak melakukan apa-apa — dan itulah seluruh alasan `[BL-055]`(c) minta
 * `can_switch` diperluas jadi alasan. Kalimatnya dikumpulkan di sini supaya
 * nadanya bisa dibaca berdampingan, bukan tersebar di markup.
 */
const state = computed(() => {
    const { reason, revenue, ceiling, available_at: availableAt } = props.verdict;

    if (reason === 'active') {
        return {
            key: 'active',
            tone: 'positive',
            chip: 'Aktif',
            headline: 'Anda berada di jalur Harga Adaptif',
            body: props.bracket
                ? `Tarif Anda mengikuti kelompok ${props.bracket.label}, dihitung dari omzet ${formatRupiah(props.bracket.revenue)} pada ${formatMonth(props.bracket.period)}.`
                : 'Omzet Anda sedang dihitung. Angkanya muncul di sini begitu perhitungan pertama selesai.',
        };
    }

    if (reason === 'above_ceiling') {
        return {
            key: 'above_ceiling',
            tone: 'warning',
            chip: 'Tidak memenuhi syarat',
            headline: 'Omzet Anda di atas batas keringanan',
            body: `Omzet Anda ${formatRupiah(revenue)}, sementara Harga Adaptif berlaku sampai di bawah ${formatRupiah(ceiling)}. Keringanan ini ditujukan untuk usaha beromzet rendah, jadi jalur yang berlaku bagi Anda adalah paket berbayar penuh.`,
        };
    }

    if (reason === 'cooldown') {
        return {
            key: 'cooldown',
            tone: 'neutral',
            chip: 'Belum bisa diajukan',
            headline: 'Jalur harga baru saja berpindah',
            body: `Perpindahan jalur hanya bisa dilakukan setiap ${props.switch_minimum_months} bulan, supaya tarif tidak ikut naik-turun mengikuti bulan ramai dan sepi. Anda bisa mengajukannya lagi mulai ${formatDate(availableAt) ?? '-'}.`,
        };
    }

    return {
        key: 'eligible',
        tone: 'positive',
        chip: 'Bisa diajukan',
        headline: 'Anda bisa mengajukan Harga Adaptif',
        body: 'Tarif Anda akan mengikuti kelompok omzet di bawah ini, dihitung ulang tiap bulan. Sebagai gantinya, omzet bulanan Anda dihitung otomatis dan angkanya bisa dilihat pengelola layanan untuk menentukan tarif.',
    };
});

const tones = {
    positive: 'border-emerald-500/40 bg-emerald-500/[0.07]',
    warning: 'border-amber-500/40 bg-amber-500/10',
    neutral: 'border-border bg-muted/40',
};

const chipTones = {
    positive: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    warning: 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    neutral: 'border-border bg-muted text-muted-foreground',
};
</script>

<template>
    <div>
        <Head title="Harga Adaptif" />

        <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6">
            <Link
                href="/langganan"
                class="inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors duration-150 hover:text-foreground"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Kembali ke Langganan
            </Link>

            <h1 class="mt-4 text-xl font-semibold text-foreground">Harga Adaptif</h1>
            <p class="mt-1 text-sm text-muted-foreground leading-relaxed">
                Keringanan tarif untuk usaha beromzet rendah. Tarifnya mengikuti omzet bulan yang baru tutup,
                bukan daftar harga tetap — dan dihitung ulang tiap bulan.
            </p>

            <!-- Verdict: satu kotak yang menjawab "apakah saya bisa, dan kalau
                 tidak, kenapa". Tombol yang mati tanpa kalimat ini membuat orang
                 mengira aplikasinya rusak. -->
            <div :class="['mt-5 rounded-xl border px-5 py-4', tones[state.tone]]">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-semibold text-foreground">{{ state.headline }}</p>
                    <span
                        :class="['shrink-0 rounded-full border px-2.5 py-0.5 text-xs font-semibold', chipTones[state.tone]]"
                    >
                        {{ state.chip }}
                    </span>
                </div>
                <p class="mt-1.5 text-sm text-muted-foreground leading-relaxed">{{ state.body }}</p>

                <!-- Pemindahan yang sudah dijadwalkan. Diletakkan di sini, bukan
                     di catatan kaki: tagihan yang naik bulan depan adalah hal
                     paling penting di halaman ini bagi tenant yang mengalaminya. -->
                <p
                    v-if="current.reverts_at && current.revert_reason === 'above_ceiling'"
                    class="mt-3 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3.5 py-2.5 text-sm text-foreground leading-relaxed"
                >
                    Tarif adaptif Anda berlaku sampai {{ formatDate(current.reverts_at) }}. Setelah itu tarif Anda
                    mengikuti paket berbayar penuh — periode berjalan tidak diubah, jadi tagihan yang sudah terbit
                    tetap seperti semula.
                </p>
            </div>

            <!-- Tarif yang berlaku sekarang, dan kapan ia terakhir benar-benar
                 diterapkan. Keduanya pertanyaan yang berbeda. -->
            <div class="mt-6 rounded-xl border border-border bg-card px-5 py-4">
                <p class="text-sm font-medium text-foreground">Tarif Anda sekarang</p>

                <div class="mt-3 flex flex-wrap items-end gap-x-8 gap-y-3">
                    <div>
                        <p class="text-xs text-muted-foreground">Paket {{ current.plan_name }}</p>
                        <p class="mt-0.5 text-2xl font-semibold text-foreground tabular-nums">
                            {{ formatRupiah(current.price) }}
                            <span class="text-sm font-normal text-muted-foreground">/bulan</span>
                        </p>
                    </div>

                    <div v-if="current.last_invoice">
                        <p class="text-xs text-muted-foreground">Terakhir ditagihkan</p>
                        <p class="mt-0.5 text-sm font-medium text-foreground tabular-nums">
                            {{ formatRupiah(current.last_invoice.amount) }}
                            <span class="font-normal text-muted-foreground">
                                · {{ formatMonth(current.last_invoice.period) }}
                            </span>
                        </p>
                    </div>

                    <div v-if="current.is_adaptive && current.track_changed_at">
                        <p class="text-xs text-muted-foreground">Masuk jalur adaptif</p>
                        <p class="mt-0.5 text-sm font-medium text-foreground">
                            {{ formatDate(current.track_changed_at) }}
                        </p>
                    </div>
                </div>

                <p v-if="measuredRevenue !== null" class="mt-3 text-xs text-muted-foreground leading-relaxed">
                    Omzet yang dipakai menilai: {{ formatRupiah(measuredRevenue) }}
                    <template v-if="bracket"> ({{ formatMonth(bracket.period) }}, tercatat)</template>
                    <template v-else-if="estimate"> ({{ formatMonth(estimate.period) }}, perkiraan — belum dikirim ke mana pun)</template>
                </p>
            </div>

            <!-- Tangga bracket lengkap, dari `pricing_rules`. Bukan tabel yang
                 diketik di template: daftar harga yang ditulis tangan akan
                 berselisih dengan tagihan pada hari pertama sebuah bracket
                 disunting. -->
            <div class="mt-6 rounded-xl border border-border bg-card px-5 py-4">
                <p class="text-sm font-medium text-foreground">Kelompok tarif</p>
                <p class="mt-1 text-sm text-muted-foreground leading-relaxed">
                    Omzet bulan yang baru tutup menentukan kelompok Anda. Batas atas tiap kelompok tidak termasuk —
                    omzet yang tepat di angka batas masuk kelompok berikutnya.
                </p>

                <div v-if="ladder.length" class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <th scope="col" class="py-2 pr-4 font-medium">Kelompok</th>
                                <th scope="col" class="py-2 pr-4 font-medium">Omzet bulanan</th>
                                <th scope="col" class="py-2 text-right font-medium">Tarif</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="bracketRow in ladder"
                                :key="bracketRow.label"
                                :class="[
                                    'border-b border-border/60 last:border-0',
                                    bracketRow.label === activeLabel ? 'bg-emerald-500/[0.07]' : '',
                                ]"
                            >
                                <td class="py-2.5 pr-4 font-medium text-foreground">
                                    {{ bracketRow.label }}
                                    <span
                                        v-if="bracketRow.label === activeLabel"
                                        class="ml-1.5 rounded-full border border-emerald-500/40 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 dark:text-emerald-300"
                                    >
                                        Anda
                                    </span>
                                </td>
                                <td class="py-2.5 pr-4 text-muted-foreground tabular-nums">{{ rangeLabel(bracketRow) }}</td>
                                <td class="py-2.5 text-right font-semibold text-foreground tabular-nums">
                                    {{ formatRupiah(bracketRow.price) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-else class="mt-3 rounded-lg border border-border bg-muted/40 px-4 py-3 text-sm text-muted-foreground leading-relaxed">
                    Belum ada kelompok tarif yang ditetapkan. Hubungi kami bila Anda ingin mengajukan keringanan.
                </p>

                <p v-if="verdict.ceiling !== null" class="mt-3 text-xs text-muted-foreground leading-relaxed">
                    Harga Adaptif berlaku sampai omzet di bawah {{ formatRupiah(verdict.ceiling) }}. Di atas itu,
                    tarif yang berlaku adalah paket berbayar penuh.
                </p>
            </div>

            <!-- Tindakan. Owner saja, dan tiap verdict punya tujuan yang
                 berbeda — yang di atas ambang diarahkan ke tagihannya, bukan
                 dibiarkan menatap tombol mati. -->
            <div v-if="tenant.is_owner" class="mt-6">
                <template v-if="state.key === 'eligible'">
                    <Link
                        href="/langganan/persetujuan/subsidized"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition-colors duration-150 hover:bg-emerald-600/90"
                    >
                        Baca ketentuan lalu ajukan
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                    <p class="mt-2 text-xs text-muted-foreground leading-relaxed">
                        Pengajuan dinilai otomatis begitu ketentuannya Anda setujui — tidak ada antrean pemeriksaan.
                        Tarif barunya berlaku mulai periode berikutnya.
                    </p>
                </template>

                <Link
                    v-else-if="state.key === 'above_ceiling'"
                    href="/langganan"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-foreground transition-colors duration-150 hover:bg-accent/40"
                >
                    Lihat tagihan dan paket Anda
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>

                <Link
                    v-else-if="state.key === 'active'"
                    href="/langganan"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-border px-4 py-2.5 text-sm font-medium text-foreground transition-colors duration-150 hover:bg-accent/40"
                >
                    Kelola di halaman Langganan
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>

            <p v-else class="mt-6 text-xs text-muted-foreground">
                Pengajuan jalur harga adalah keputusan pemilik usaha.
            </p>
        </div>
    </div>
</template>
