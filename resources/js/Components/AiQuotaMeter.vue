<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useAiQuota } from '@/composables/useAiQuota';

/**
 * Sisa kuota AI harian, dibaca dari SATU bentuk data yang sama di dua layar
 * (`[BL-062]`):
 *
 *   - `variant="compact"` — di AI Analysis, di sebelah tombol kirim. Perannya
 *     peringatan sebelum bertindak, jadi isinya satu baris dan satu meter.
 *   - `variant="detailed"` — di Pengaturan. Perannya konteks untuk keputusan
 *     BYOK, jadi angkanya dibedah: batas, terpakai, sisa, dan asal batasnya.
 *
 * Keduanya satu komponen supaya keadaan yang sama tidak pernah dijelaskan
 * dengan dua kalimat berbeda — persis masalah yang melahirkan `[BL-062]`.
 */
const props = defineProps({
    quota: { type: Object, required: true },
    variant: { type: String, default: 'compact' }, // compact | detailed
});

const isDetailed = computed(() => props.variant === 'detailed');

const { limit, used, remaining, state, isBlocked, percentUsed } = useAiQuota(() => props.quota);

// Kelas ditulis utuh, bukan dirakit dari potongan: Tailwind memindai berkas ini
// sebagai teks, dan kelas hasil interpolasi tidak akan pernah ikut ter-build.
const tones = {
    byok: {
        box: 'border-primary/20 bg-primary/5 text-primary',
        track: 'bg-primary/15',
        bar: 'bg-primary',
        dot: 'bg-primary',
    },
    ok: {
        box: 'border-blue-100 bg-blue-50 text-blue-700',
        track: 'bg-blue-100',
        bar: 'bg-blue-500',
        dot: 'bg-blue-500',
    },
    low: {
        box: 'border-amber-200 bg-amber-50 text-amber-800',
        track: 'bg-amber-100',
        bar: 'bg-amber-500',
        dot: 'bg-amber-500',
    },
    empty: {
        box: 'border-destructive/20 bg-destructive/5 text-destructive',
        track: 'bg-destructive/15',
        bar: 'bg-destructive',
        dot: 'bg-destructive',
    },
    unavailable: {
        box: 'border-destructive/20 bg-destructive/5 text-destructive',
        track: 'bg-destructive/15',
        bar: 'bg-destructive',
        dot: 'bg-destructive',
    },
};

const tone = computed(() => tones[state.value]);

const statusLabel = computed(() => ({
    byok: 'Tanpa batas',
    ok: 'Tersedia',
    low: 'Hampir habis',
    empty: 'Habis',
    unavailable: 'Tidak tersedia',
}[state.value]));

/** Jalan keluarnya — hanya ditulis saat keadaannya memang menutup jalan. */
const escapeHatch = computed(() => ({
    empty: 'Kuota berulang besok, atau isi API key sendiri untuk pemakaian tanpa batas.',
    unavailable: 'Isi API key sendiri, atau naikkan paket.',
}[state.value] ?? null));

const limitSourceNote = computed(() => {
    if (props.quota?.limit_source === 'plan' && props.quota?.plan_name) {
        return `Batas ini berasal dari paket ${props.quota.plan_name}.`;
    }

    return 'Batas ini adalah bawaan platform — paket Anda tidak menetapkan batas sendiri.';
});

// Promo disebut sebagai barisnya sendiri, bukan dilebur ke angka batas: jatah
// yang naik tanpa alasan yang terbaca akan dikira jatah tetap, dan hari promo
// berakhir akan terbaca sebagai aplikasi yang rusak (`[BL-047]`(a)).
const bonusNote = computed(() => {
    const bonus = Number(props.quota?.bonus ?? 0);

    if (bonus <= 0) {
        return null;
    }

    const label = props.quota?.bonus_label;

    return `Termasuk tambahan ${bonus} analisis/hari${label ? ` dari promo ${label}` : ''}, yang berlaku sementara.`;
});
</script>

<template>
    <!-- ── Ringkas: satu baris + meter, untuk dipasang di dekat tombol ────── -->
    <div
        v-if="!isDetailed"
        class="flex items-start gap-2 rounded-lg border px-3 py-2 text-xs"
        :class="tone.box"
    >
        <svg
            v-if="state === 'byok'"
            class="w-4 h-4 shrink-0 mt-px"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.8"
            aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l6.964-6.964A6 6 0 1121 9z" />
        </svg>
        <svg
            v-else-if="isBlocked"
            class="w-4 h-4 shrink-0 mt-px"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.8"
            aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <svg
            v-else
            class="w-4 h-4 shrink-0 mt-px"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            stroke-width="1.8"
            aria-hidden="true"
        >
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>

        <div class="min-w-0 flex-1">
            <p class="leading-relaxed">
                <template v-if="state === 'byok'">
                    Memakai kunci API sendiri — <strong>tanpa batas</strong> kuota harian.
                </template>
                <template v-else-if="state === 'unavailable'">
                    Paket ini tidak menyertakan analisis AI.
                </template>
                <template v-else-if="state === 'empty'">
                    Kuota gratis hari ini sudah habis (<strong>{{ used }}</strong> dari {{ limit }}).
                </template>
                <template v-else>
                    Sisa kuota gratis hari ini: <strong>{{ remaining }}</strong> dari {{ limit }} analisis.
                </template>
            </p>

            <div
                v-if="state !== 'byok' && limit > 0"
                class="mt-1.5 h-1 rounded-full overflow-hidden"
                :class="tone.track"
                role="progressbar"
                :aria-valuenow="used"
                aria-valuemin="0"
                :aria-valuemax="limit"
            >
                <div
                    class="h-full rounded-full transition-all duration-300"
                    :class="tone.bar"
                    :style="{ width: percentUsed + '%' }"
                ></div>
            </div>

            <p v-if="escapeHatch" class="mt-1 leading-relaxed">
                {{ escapeHatch }}
                <!-- Langsung ke halaman kredensial, bukan ke pintu Pengaturan.
                     Jalan keluar yang ditawarkan di sini adalah BYOK, dan sejak
                     `[BL-039]` kolom kuncinya tidak lagi ada di halaman depan. -->
                <Link href="/owner/settings/integrations" class="font-medium underline underline-offset-2 hover:no-underline">
                    Atur Kunci API
                </Link>
            </p>
        </div>
    </div>

    <!-- ── Lengkap: angkanya dibedah, untuk halaman Pengaturan ───────────── -->
    <div v-else class="rounded-xl border border-gray-200 overflow-hidden">
        <div class="flex items-start justify-between gap-3 px-4 py-3 border-b border-gray-200 bg-gray-50/70">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Kuota Gratis Harian</h3>
                <p class="text-xs text-gray-500 mt-0.5">Berlaku selama Anda memakai kunci bersama milik aplikasi.</p>
            </div>
            <span
                class="inline-flex items-center gap-1.5 shrink-0 px-2.5 py-1 rounded-full border text-xs font-medium"
                :class="tone.box"
            >
                <span class="w-1.5 h-1.5 rounded-full" :class="tone.dot" aria-hidden="true"></span>
                {{ statusLabel }}
            </span>
        </div>

        <!-- BYOK: tidak ada yang perlu dijatah, jadi tidak ada angka kuota yang
             jujur bisa ditampilkan di sini. -->
        <div v-if="state === 'byok'" class="px-4 py-4">
            <p class="text-sm text-gray-700 leading-relaxed">
                Kunci API Anda sendiri sedang dipakai, jadi <strong>tidak ada batas harian</strong> dari kami.
                Pemakaian dan biayanya berjalan langsung di akun provider Anda.
            </p>
            <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                Kosongkan kolom API Key di bawah bila ingin kembali memakai kuota gratis<template v-if="limit > 0">
                — {{ limit }} analisis per hari</template>.
            </p>
        </div>

        <!-- Batas nol: paketnya memang tidak menyertakan AI, dan itu keadaan
             yang berbeda dari "kuota habis". -->
        <div v-else-if="state === 'unavailable'" class="px-4 py-4">
            <p class="text-sm text-gray-700 leading-relaxed">
                Paket <template v-if="quota.plan_name"><strong>{{ quota.plan_name }}</strong></template><template v-else>Anda</template>
                tidak menyertakan analisis AI gratis.
            </p>
            <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                Isi kunci API Anda sendiri di bawah untuk memakai fitur ini tanpa batas, atau naikkan paket.
            </p>
        </div>

        <div v-else class="px-4 py-4 space-y-4">
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5">
                    <p class="text-xs text-gray-500">Batas harian</p>
                    <p class="text-lg font-semibold text-gray-900 mt-0.5 tabular-nums">{{ limit }}</p>
                </div>
                <div class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2.5">
                    <p class="text-xs text-gray-500">Terpakai</p>
                    <p class="text-lg font-semibold text-gray-900 mt-0.5 tabular-nums">{{ used }}</p>
                </div>
                <div class="rounded-lg border px-3 py-2.5" :class="tone.box">
                    <p class="text-xs">Sisa</p>
                    <p class="text-lg font-semibold mt-0.5 tabular-nums">{{ remaining }}</p>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between text-xs text-gray-500 mb-1.5">
                    <span>Pemakaian hari ini</span>
                    <span class="tabular-nums">{{ used }}/{{ limit }} · {{ percentUsed }}%</span>
                </div>
                <div
                    class="h-2 rounded-full overflow-hidden"
                    :class="tone.track"
                    role="progressbar"
                    :aria-valuenow="used"
                    aria-valuemin="0"
                    :aria-valuemax="limit"
                >
                    <div
                        class="h-full rounded-full transition-all duration-300"
                        :class="tone.bar"
                        :style="{ width: percentUsed + '%' }"
                    ></div>
                </div>
            </div>

            <ul class="space-y-1.5 text-xs text-gray-500 leading-relaxed">
                <li class="flex gap-1.5">
                    <span class="text-gray-300" aria-hidden="true">•</span>
                    <span>{{ limitSourceNote }}</span>
                </li>
                <li v-if="bonusNote" class="flex gap-1.5">
                    <span class="text-gray-300" aria-hidden="true">•</span>
                    <span>{{ bonusNote }}</span>
                </li>
                <li class="flex gap-1.5">
                    <span class="text-gray-300" aria-hidden="true">•</span>
                    <span>Hitungan kembali nol setiap hari. Jatah yang tidak terpakai tidak menumpuk ke hari berikutnya.</span>
                </li>
                <li class="flex gap-1.5">
                    <span class="text-gray-300" aria-hidden="true">•</span>
                    <span>Hanya analisis yang <strong>berhasil</strong> memotong kuota — yang gagal tidak dihitung.</span>
                </li>
            </ul>

            <div class="pt-1">
                <Link
                    href="/owner/ai-analysis"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:text-primary/80"
                >
                    Buka AI Analysis
                    <span aria-hidden="true">→</span>
                </Link>
            </div>
        </div>
    </div>
</template>
