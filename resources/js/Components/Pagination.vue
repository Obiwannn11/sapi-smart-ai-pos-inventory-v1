<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * Navigasi halaman untuk paginator Laravel.
 *
 * Dibuat karena daftar `links` bawaan Laravel tidak punya atap: pada 33
 * halaman ia mengirim ~15 pil, dan label Indonesianya panjang ("« Sebelumnya",
 * "Berikutnya »"). Di dalam wadah `max-w-5xl` yang sudah dipotong sidebar,
 * barisnya tidak muat, pilnya menyusut, dan teks di dalam tiap pil pecah jadi
 * dua baris — persis yang terlihat di riwayat stok satu varian ramai.
 *
 * Jendelanya karena itu dipatok di sini, bukan diserahkan ke `onEachSide`:
 * halaman pertama, halaman terakhir, dan tetangga terdekat halaman aktif.
 * Berapa pun jumlah halamannya, yang tampil paling banyak tujuh pil.
 *
 * URL-nya tetap diambil dari `links` bawaan, bukan dirakit ulang dari `path`:
 * hanya URL bawaan itu yang membawa serta filter yang sedang aktif.
 */
const props = defineProps({
    paginator: { type: Object, required: true },
    // Satuan barisnya, untuk kalimat "… dari 1.647 data".
    unit: { type: String, default: 'data' },
    // Konsol platform memakai token semantik (`card`, `border`,
    // `muted-foreground`) sementara layar owner memakai palet abu-abu
    // langsung. Satu komponen melayani keduanya; yang berbeda hanya warnanya.
    tone: { type: String, default: 'owner' },
    // Kalimat "Menampilkan 1–50 dari 1.647" dimatikan di layar yang sudah
    // menulisnya sendiri di kaki tabel — mis. daftar stok, yang kakinya juga
    // memuat pemilih jumlah baris dan karena itu tetap tampil pada halaman
    // tunggal.
    summary: { type: Boolean, default: true },
});

const links = computed(() => props.paginator?.links ?? []);

const previous = computed(() => links.value[0] ?? null);
const next = computed(() => links.value[links.value.length - 1] ?? null);

const currentPage = computed(() => props.paginator?.current_page ?? 1);
const lastPage = computed(() => props.paginator?.last_page ?? 1);

const formatNumber = (value) => Number(value ?? 0).toLocaleString('id-ID');

/**
 * Pil nomor yang benar-benar digambar, berikut sisipan "…" di tempat yang
 * melompat. Ellipsis-nya bukan tombol — sesuatu yang berbentuk tombol tapi
 * tidak bisa ditekan adalah janji yang tidak ditepati.
 */
const pages = computed(() => {
    const numeric = links.value
        .slice(1, -1)
        .filter((link) => /^\d+$/.test(link.label))
        .map((link) => ({ ...link, page: Number(link.label) }));

    const keep = numeric.filter(({ page }) =>
        page === 1 || page === lastPage.value || Math.abs(page - currentPage.value) <= 1
    );

    const out = [];
    let previousPage = 0;

    for (const link of keep) {
        if (previousPage && link.page - previousPage > 1) {
            out.push({ gap: true, key: `gap-${previousPage}` });
        }
        out.push({ ...link, key: `page-${link.page}` });
        previousPage = link.page;
    }

    return out;
});

const TONES = {
    owner: {
        idle: 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
        disabled: 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed',
        caption: 'text-gray-500',
        gap: 'text-gray-400',
    },
    platform: {
        idle: 'bg-card text-muted-foreground border-border hover:bg-accent/40',
        disabled: 'bg-card text-muted-foreground/40 border-border cursor-not-allowed',
        caption: 'text-muted-foreground',
        gap: 'text-muted-foreground/60',
    },
};

const palette = computed(() => TONES[props.tone] ?? TONES.owner);

const pillClass = (isActive, hasUrl) => [
    'inline-flex items-center justify-center min-w-[2rem] px-2.5 py-1.5 text-sm rounded-lg border whitespace-nowrap transition-colors',
    isActive
        ? 'bg-primary text-primary-foreground border-primary'
        : hasUrl
            ? palette.value.idle
            : palette.value.disabled,
];
</script>

<template>
    <div
        v-if="lastPage > 1"
        class="flex flex-wrap items-center gap-x-4 gap-y-2"
        :class="[summary ? 'mt-4 justify-between' : 'justify-end']"
    >
        <p v-if="summary" :class="['text-sm whitespace-nowrap', palette.caption]">
            Menampilkan {{ formatNumber(paginator.from) }}–{{ formatNumber(paginator.to) }}
            dari {{ formatNumber(paginator.total) }} {{ unit }}
        </p>

        <nav class="flex items-center gap-1" aria-label="Navigasi halaman">
            <!-- Panah tetap terbaca sendiri di layar sempit; katanya menyusul
                 begitu ada ruang untuk membacanya. -->
            <Link
                v-if="previous?.url"
                :href="previous.url"
                :class="pillClass(false, true)"
                rel="prev"
                preserve-scroll
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                <span class="hidden sm:inline ml-1">Sebelumnya</span>
            </Link>
            <span v-else :class="pillClass(false, false)" aria-disabled="true">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                <span class="hidden sm:inline ml-1">Sebelumnya</span>
            </span>

            <!-- Di layar sempit deretan nomornya diganti satu keterangan:
                 tujuh pil pun masih memaksa baris kedua di lebar ponsel. -->
            <span :class="['sm:hidden px-2 text-sm tabular-nums whitespace-nowrap', palette.caption]">
                Hal. {{ currentPage }} / {{ lastPage }}
            </span>

            <template v-for="item in pages" :key="item.key">
                <span v-if="item.gap" :class="['hidden sm:inline px-1 text-sm select-none', palette.gap]">…</span>
                <Link
                    v-else
                    :href="item.url || '#'"
                    :class="['hidden sm:inline-flex tabular-nums', ...pillClass(item.active, Boolean(item.url))]"
                    :aria-current="item.active ? 'page' : undefined"
                    preserve-scroll
                >
                    {{ item.page }}
                </Link>
            </template>

            <Link
                v-if="next?.url"
                :href="next.url"
                :class="pillClass(false, true)"
                rel="next"
                preserve-scroll
            >
                <span class="hidden sm:inline mr-1">Berikutnya</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </Link>
            <span v-else :class="pillClass(false, false)" aria-disabled="true">
                <span class="hidden sm:inline mr-1">Berikutnya</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
            </span>
        </nav>
    </div>
</template>
