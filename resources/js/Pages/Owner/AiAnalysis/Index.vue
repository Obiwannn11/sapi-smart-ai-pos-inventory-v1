<script setup>
import { ref, computed, watch, nextTick, onUnmounted } from 'vue';
import { useForm, router, Link, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import DatePicker from '@/Components/DatePicker.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import MetricCard from '@/Components/MetricCard.vue';
import Button from '@/Components/Button.vue';
import AiQuotaMeter from '@/Components/AiQuotaMeter.vue';
import SkeletonText from '@/Components/Skeleton/SkeletonText.vue';
import { useAiQuota } from '@/composables/useAiQuota';
import { BUSINESS_TZ, businessDaysAgo, businessToday } from '@/support/date';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    analyses: { type: Array, default: () => [] },
    active: { type: Object, default: null },
    // Kuota dikirim ke HALAMAN INI, bukan cuma ke Pengaturan (`[BL-062]`):
    // di sinilah ia dibelanjakan, jadi di sinilah sisanya perlu terbaca
    // SEBELUM tombolnya ditekan.
    aiQuota: { type: Object, default: () => ({}) },
    // Peta nama varian ke barangnya ([BL-100] tahap 2). SATU peta untuk semua
    // analisis — pemetaannya cuma bergantung pada katalog hari ini. Yang
    // membedakan antar-analisis adalah nama mana yang BOLEH dipetakan, dan itu
    // dibawa tiap barisnya sendiri lewat `context_variants`.
    variantLinks: { type: Object, default: () => ({}) },
});

// Keadaan kuota dipakai dua kali di halaman ini — oleh meternya dan oleh tombol
// yang dimatikan — jadi keduanya membacanya dari satu perhitungan yang sama.
const { isBlocked: quotaBlocked } = useAiQuota(() => props.aiQuota);

// ── Static maps ──────────────────────────────────────────────────────────────
const typeOptions = [
    { value: 'general', label: 'Insight Umum' },
    { value: 'discount', label: 'Saran Diskon' },
    { value: 'profit_projection', label: 'Proyeksi Profit' },
    { value: 'custom', label: 'Pertanyaan Sendiri' },
];

const typeLabels = {
    general: 'Insight Umum',
    discount: 'Saran Diskon',
    profit_projection: 'Proyeksi Profit',
    custom: 'Pertanyaan Sendiri',
};

const statusMeta = {
    pending: { label: 'Menunggu', class: 'bg-gray-100 text-gray-600' },
    processing: { label: 'Memproses', class: 'bg-blue-50 text-blue-600' },
    completed: { label: 'Selesai', class: 'bg-success/10 text-success' },
    failed: { label: 'Gagal', class: 'bg-destructive/10 text-destructive' },
};

// ── Create form ──────────────────────────────────────────────────────────────
// Rentang bawaan dihitung dari hari TOKO ([BL-082]). Analisis yang dipesan
// pukul 01.00 dulu meminta rentang yang berakhir kemarin.
const form = useForm({
    type: 'general',
    from: businessDaysAgo(29),
    to: businessToday(),
    prompt: '',
});

// The analysis currently shown in the Result card: prefer the server-provided
// `active` (from the show route), otherwise the locally selected one.
const selectedId = ref(props.active?.id ?? null);

const displayed = computed(() => {
    if (props.active) {
        return props.active;
    }
    if (selectedId.value) {
        return props.analyses.find((a) => a.id === selectedId.value) ?? null;
    }
    return null;
});

const submit = () => {
    form.post('/owner/ai-analysis', {
        preserveScroll: true,
        onSuccess: () => {
            selectedId.value = props.analyses[0]?.id ?? null;
            if (form.type !== 'custom') {
                form.reset('prompt');
            }
            scrollToResult();
        },
    });
};

// ── Polling (Inertia v2) ─────────────────────────────────────────────────────
// While the shown analysis is pending/processing, refresh the relevant props
// every 3s until it reaches a terminal state.
//
// `aiQuota` ikut disegarkan karena jatah baru terpotong saat analisisnya
// BERHASIL — di antrean, bukan saat tombol ditekan. Tanpa ini meternya masih
// memperlihatkan angka sebelum analisis ini berjalan, dan owner baru tahu
// jatahnya berkurang setelah memuat ulang halaman.
//
// Jadwalnya dipasang ulang sesudah SETIAP muat ulang, bukan hanya saat
// statusnya berubah. Versi sebelumnya menggantungkan seluruh rantai pada
// watcher: satu muat ulang dijadwalkan, dan jadwal berikutnya baru lahir kalau
// `status` bernilai lain. Analisis yang butuh lebih dari 3 detik menjawab
// `processing` dua kali berturut-turut — nilainya sama, watcher-nya diam, dan
// polling-nya mati di situ. Pemutarnya lalu berputar selamanya di layar owner
// untuk analisis yang di basis data sudah `completed`. Terlihat sekali:
// analisis yang cepat selesai memang berpindah pending → processing →
// completed, tiga nilai berbeda, jadi rantainya utuh secara kebetulan.
let timer = null;

const stopPolling = () => {
    if (timer) {
        clearTimeout(timer);
        timer = null;
    }
};

const schedulePoll = () => {
    stopPolling();

    const status = displayed.value?.status;
    if (status !== 'pending' && status !== 'processing') {
        return;
    }

    timer = setTimeout(() => {
        router.reload({
            only: ['active', 'analyses', 'aiQuota'],
            // Dipasang ulang apa pun hasilnya — termasuk saat statusnya tidak
            // berubah sama sekali, yang justru keadaan paling lazim.
            onFinish: schedulePoll,
        });
    }, 3000);
};

// Watcher-nya tetap ada untuk pemicu yang BUKAN muat ulang: analisis yang
// dipilih dari riwayat, dan pemuatan halaman pertama.
watch(() => displayed.value?.status, schedulePoll, { immediate: true });
onUnmounted(stopPolling);

// ── Result metrics ───────────────────────────────────────────────────────────
const formatDate = (value) => {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleDateString('id-ID', {
        timeZone: BUSINESS_TZ,
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};

const periodLabel = (analysis) => {
    if (!analysis?.params?.from) {
        return '—';
    }
    return `${formatDate(analysis.params.from)} – ${formatDate(analysis.params.to)}`;
};

/**
 * Panjang rentang yang dianalisis, dalam hari (inklusif kedua ujungnya).
 *
 * Menggantikan "Token Terpakai" di kartu tengah: jumlah token adalah ongkos
 * internal yang tidak bisa ditindaklanjuti owner, sedangkan panjang rentang
 * justru yang menentukan cara membaca hasilnya — proyeksi di bawahnya adalah
 * rata-rata harian dikali angka ini.
 */
const periodDays = (analysis) => {
    if (!analysis?.params?.from || !analysis?.params?.to) {
        return null;
    }
    const from = new Date(`${analysis.params.from}T00:00:00`);
    const to = new Date(`${analysis.params.to}T00:00:00`);
    const days = Math.round((to - from) / 86400000) + 1;

    return days > 0 ? days : null;
};

// ── Minimal, safe markdown renderer ──────────────────────────────────────────
// Escapes HTML first, then applies a small subset of markdown so LLM output
// renders readably without pulling in a dependency.
const escapeHtml = (str) =>
    str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

/**
 * Nama varian yang boleh disentuh renderer untuk analisis yang sedang dibuka
 * ([BL-100] tahap 3), sudah dalam bentuk HTML-escaped.
 *
 * Perpotongan dua daftar, dan perpotongan itulah penjaganya: `variantLinks`
 * menjawab "nama ini ada di katalog?", sedangkan `context_variants` menjawab
 * "nama ini benar-benar disodorkan ke model?". Nama karangan model lolos
 * pertanyaan pertama sebagai `missing` — ia memang tidak ada di katalog — dan
 * tanpa pertanyaan kedua ia akan ditandai "sudah dihapus", yang mengubah
 * halusinasi jadi pernyataan yang terlihat berwenang.
 */
const escapeRegExp = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const activeVariantLinks = computed(() => {
    const eligible = displayed.value?.context_variants ?? [];
    const map = {};

    for (const name of eligible) {
        const link = props.variantLinks?.[name];
        if (link) {
            // Dikunci pakai nama yang sudah di-escape: teksnya sudah lewat
            // `escapeHtml` saat dicocokkan nanti, jadi varian bernama "A&W"
            // muncul di sana sebagai "A&amp;W".
            map[escapeHtml(name)] = { ...link, label: name };
        }
    }

    return map;
});

const variantPattern = computed(() => {
    const names = Object.keys(activeVariantLinks.value);

    if (names.length === 0) {
        return null;
    }

    // Terpanjang dulu. Tanpa itu "Iced" menang atas "Iced Tea" dan menautkan
    // separuh nama barang yang lain ke barang yang salah.
    const alternatives = names
        .slice()
        .sort((a, b) => b.length - a.length)
        .map(escapeRegExp)
        .join('|');

    // Batas kiri ditangkap, batas kanan cuma dilihat: lookbehind dihindari
    // supaya peramban lama tidak melempar SyntaxError saat memuat halaman —
    // dan halaman yang gagal dimuat jauh lebih buruk daripada tautan yang
    // sesekali terlewat karena dua nama berdempetan.
    return new RegExp(`(^|[^\\p{L}\\p{N}])(${alternatives})(?![\\p{L}\\p{N}])`, 'gu');
});

/**
 * Satu nama varian, sudah jadi tautan atau penanda ([BL-100] tahap 3).
 *
 * `linked` mendarat di `?variant=` dan bukan di `?q=`: pencarian boleh cocok
 * dengan banyak barang, sedangkan yang dimaksud analisis cuma satu. Id, bukan
 * nama, supaya tautannya tidak putus saat variannya diganti nama.
 *
 * `missing` DITANDAI dan sengaja tidak bertautan — keputusan pemilik
 * 2026-09-06. Barangnya sudah tidak ada, jadi tidak ada tempat untuk dituju;
 * yang perlu diketahui owner justru bahwa ia sudah tidak ada, karena tanpa itu
 * ia mengira sarannya belum ditindaklanjuti.
 */
const decorateVariant = (name, link) => {
    if (!link) {
        return name;
    }

    if (link.state === 'linked') {
        return `<a href="/owner/products?variant=${link.variant_id}" data-variant-link class="text-primary underline decoration-dotted underline-offset-2 hover:decoration-solid">${name}</a>`;
    }

    return `<span class="text-gray-500 border-b border-dashed border-gray-400" title="Sudah tidak ada di katalog">${name}</span>`;
};

/**
 * Sisipkan tautan ke dalam HTML yang sudah jadi, tanpa merusaknya.
 *
 * Hanya potongan DI LUAR tag yang disentuh. `<strong>` yang baru saja
 * disisipkan di atas — dan `href` yang ditulis fungsi ini sendiri — tidak boleh
 * ikut dicocokkan; satu varian yang kebetulan bernama sepotong markup akan
 * melahirkan HTML yang rusak, dan `v-html` merendernya apa adanya.
 */
const linkVariants = (html, links, pattern) => {
    if (!pattern) {
        return html;
    }

    return html.replace(/(<[^>]*>)|([^<]+)/g, (match, tag, text) => {
        if (tag !== undefined) {
            return tag;
        }

        return text.replace(pattern, (full, before, name) => before + decorateVariant(name, links[name]));
    });
};

const renderMarkdown = (raw, links = {}, pattern = null) => {
    if (!raw) {
        return '';
    }

    const lines = escapeHtml(raw).split('\n');
    const html = [];

    // Daftar yang sedang terbuka, terluar dulu: `{ type, itemOpen }`.
    //
    // Penomoran yang meloncat kembali ke 1 lahir dari sini, bukan dari model.
    // Model menulis langkah bernomor lalu menjelaskannya dengan butir-butir di
    // bawahnya; renderer lama menutup <ol> begitu bertemu butir pertama, lalu
    // membukanya lagi di langkah berikutnya — dan <ol> yang baru selalu mulai
    // dari 1. Karena itu <li> dibiarkan TERBUKA di sini: daftar bertitik di
    // bawah sebuah langkah ditulis DI DALAM langkah itu, <ol>-nya tidak pernah
    // tertutup, dan nomornya berjalan 1, 2, 3 apa pun angka yang diketik model.
    const stack = [];
    const top = () => stack[stack.length - 1] ?? null;

    const bulletRe = /^[-*+]\s+(.*)$/;
    const orderedRe = /^\d+[.)]\s+(.*)$/;

    const inline = (text) =>
        linkVariants(
            text
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/(^|[^*])\*(?!\s)(.+?)\*/g, '$1<em>$2</em>'),
            links,
            pattern,
        );

    const closeItem = () => {
        const level = top();
        if (level && level.itemOpen) {
            html.push('</li>');
            level.itemOpen = false;
        }
    };

    const closeList = () => {
        closeItem();
        html.push(`</${stack.pop().type}>`);
    };

    const closeAll = () => {
        while (stack.length) {
            closeList();
        }
    };

    const openList = (type) => {
        html.push(`<${type}>`);
        stack.push({ type, itemOpen: false });
    };

    /**
     * Mulai satu butir bertipe `type`, membuka atau menutup daftar seperlunya.
     *
     * Hanya SATU arah yang dianggap bersarang: butir bertitik di bawah langkah
     * bernomor. Arah sebaliknya — daftar bernomor sesudah daftar bertitik —
     * jauh lebih sering berarti dua daftar terpisah daripada satu di dalam
     * yang lain, jadi yang lama ditutup.
     */
    const pushItem = (type, content) => {
        let level = -1;
        for (let d = stack.length - 1; d >= 0; d--) {
            if (stack[d].type === type) {
                level = d;
                break;
            }
        }

        if (level === -1) {
            if (type === 'ul' && top()?.type === 'ol' && top().itemOpen) {
                openList('ul');
            } else {
                closeAll();
                openList(type);
            }
        } else {
            while (stack.length - 1 > level) {
                closeList();
            }
            closeItem();
        }

        html.push(`<li>${inline(content)}`);
        top().itemOpen = true;
    };

    for (const line of lines) {
        const trimmed = line.trim();

        // Baris kosong tidak lagi menutup apa pun: model menyelanginya di
        // antara butir demi keterbacaan, dan menutup daftar di situ persis
        // yang dulu memutus penomoran.
        if (trimmed === '') {
            continue;
        }

        const heading = trimmed.match(/^(#{1,4})\s+(.*)$/);
        if (heading) {
            closeAll();
            const level = heading[1].length + 2; // # → h3
            html.push(`<h${level}>${inline(heading[2])}</h${level}>`);
            continue;
        }

        const bullet = trimmed.match(bulletRe);
        if (bullet) {
            pushItem('ul', bullet[1]);
            continue;
        }

        const ordered = trimmed.match(orderedRe);
        if (ordered) {
            pushItem('ol', ordered[1]);
            continue;
        }

        // Paragraf menjorok di bawah daftar yang terbuka adalah lanjutan
        // butirnya, bukan penutupnya.
        if (top()?.itemOpen && /^\s{2,}/.test(line)) {
            html.push(`<p>${inline(trimmed)}</p>`);
            continue;
        }

        closeAll();
        html.push(`<p>${inline(trimmed)}</p>`);
    }

    closeAll();
    return html.join('');
};

const resultHtml = computed(() => renderMarkdown(
    displayed.value?.result ?? '',
    activeVariantLinks.value,
    variantPattern.value,
));

/**
 * Nama yang barangnya sudah tidak ada DAN benar-benar disebut hasil ini.
 *
 * Dipakai untuk satu baris keterangan di bawah kartu. Menerangkan garis
 * putus-putus di tiap kemunculannya akan membuat teksnya penuh lencana; sekali
 * di bawah sudah cukup, dan hanya kalau memang ada yang perlu diterangkan.
 */
const missingMentioned = computed(() => {
    const pattern = variantPattern.value;

    if (!pattern) {
        return [];
    }

    const links = activeVariantLinks.value;
    const found = new Set();

    escapeHtml(displayed.value?.result ?? '').replace(pattern, (full, before, name) => {
        if (links[name]?.state === 'missing') {
            found.add(links[name].label);
        }

        return full;
    });

    return [...found];
});

/**
 * Tautan di dalam `v-html` bukan `<Link>`, jadi tanpa ini tiap klik memuat
 * ulang seluruh aplikasi. Ditangkap di kartunya, bukan dipasang per tautan —
 * HTML-nya lahir dari string, tidak ada tempat menempelkan handler Vue.
 */
const openVariantLink = (event) => {
    const anchor = event.target.closest?.('a[data-variant-link]');

    if (!anchor) {
        return;
    }

    event.preventDefault();
    router.visit(anchor.getAttribute('href'));
};

// Kartu hasil berdiri DI ATAS tabel riwayat, jadi menekan "Lihat" pada baris
// yang jauh ke bawah dulu terasa seperti tombol yang tidak berbuat apa-apa:
// isinya berganti di luar layar. Barisnya digulirkan ke hasilnya sendiri.
const resultCard = ref(null);

const scrollToResult = () => {
    nextTick(() => {
        resultCard.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
};

const selectAnalysis = (analysis) => {
    selectedId.value = analysis.id;
    scrollToResult();
};
</script>

<template>
    <Head title="AI Analysis" />

    <div class="max-w-5xl mx-auto space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold text-gray-900">AI Analysis</h1>
            <p class="text-sm text-gray-500 mt-1">
                Analisis bisnis bertenaga AI dari data penjualan, profit, dan stok Anda.
            </p>
        </div>

        <!-- ── Create form ─────────────────────────────────────────────────── -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h2 class="text-base font-semibold text-gray-900 mb-4">Buat Analisis</h2>

            <!-- `@container`: kisinya diukur terhadap LEBAR KARTU, bukan lebar
                 jendela. Bedanya bukan teori — dengan breakpoint jendela biasa,
                 `lg:` menyala tepat di 1024px sementara sidebar owner memakan
                 ~224px, menyisakan kolom 153px; tanggalnya terpotong justru di
                 lebar yang paling lazim dipakai laptop. Yang menentukan muat
                 atau tidak memang kartunya, dan hanya query kontainer yang bisa
                 melihatnya. -->
            <form @submit.prevent="submit" class="@container space-y-4">
                <!-- Empat kolom SAMA BESAR, satu baris di layar lebar.
                     Lebarnya sengaja tidak mengikuti panjang isinya: "Insight
                     Umum" jauh lebih pendek daripada "Jum, 4 September 2026",
                     dan kolom yang menyesuaikan diri pada teks menghasilkan
                     baris yang ragged — tiap kontrol berhenti di tempat yang
                     berbeda tanpa alasan yang bisa dilihat pemakainya.
                     Rentangnya juga dipecah jadi dua field berlabel sendiri:
                     dengan begitu keduanya jadi kolom penuh yang sederajat,
                     dan pemisah "–" yang dulu memakan ruang di tengah baris
                     tidak lagi diperlukan. -->
                <div class="grid gap-4 @md:grid-cols-2 @4xl:grid-cols-4 @4xl:items-end">
                    <!-- Type -->
                    <SelectDropdown
                        v-model="form.type"
                        :options="typeOptions"
                        label="Tipe Analisis"
                        :error="form.errors.type"
                    />

                    <!-- Period -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dari Tanggal</label>
                        <DatePicker v-model="form.from" block />
                        <p v-if="form.errors.from" class="mt-1 text-xs text-red-600">{{ form.errors.from }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sampai Tanggal</label>
                        <DatePicker v-model="form.to" block />
                        <p v-if="form.errors.to" class="mt-1 text-xs text-red-600">{{ form.errors.to }}</p>
                    </div>

                    <!-- Custom prompt — ikut di dalam kisi yang sama, dan
                         DITARUH SEBELUM tombolnya. Kalau ia berdiri di luar
                         kisi, tombol kirim berakhir di atas kolom isian yang
                         dikirimnya. -->
                    <div v-if="form.type === 'custom'" class="@md:col-span-2 @4xl:col-span-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Pertanyaan</label>
                        <textarea
                            v-model="form.prompt"
                            rows="3"
                            placeholder="Contoh: Menu apa yang paling menguntungkan dan bagaimana cara meningkatkan penjualannya?"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                            :class="{ 'border-red-300': form.errors.prompt }"
                        />
                        <p v-if="form.errors.prompt" class="mt-1 text-xs text-red-600">{{ form.errors.prompt }}</p>
                    </div>

                    <!-- Tombolnya ikut ke dalam baris yang sama, dan ikut
                         selebar kolomnya. Sebelumnya ia berdiri sendiri di
                         baris kedua bersama meter kuota, padahal seluruh
                         kontrolnya muat dalam satu baris.
                         `col-start-4` dipatok, bukan dibiarkan mengalir:
                         begitu kolom pertanyaan muncul dan memakan satu baris
                         penuh, tombol yang mengalir akan mendarat di kolom
                         pertama — kiri bawah, tempat yang tidak dicari orang
                         saat mencari tombol kirim. -->
                    <Button
                        type="submit"
                        :loading="form.processing"
                        :disabled="quotaBlocked"
                        block
                        class="@md:col-start-2 @4xl:col-start-4"
                    >
                        <template #icon>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                            </svg>
                        </template>
                        {{ form.processing ? 'Memproses...' : 'Analisa' }}
                    </Button>
                </div>

                <!-- Sisa kuota tetap berdiri tepat di bawah tombol yang
                     membelanjakannya, dan tombolnya tetap mati saat jatahnya
                     nol: menolak di layar jauh lebih murah — dan jauh lebih
                     jelas — daripada menolak di antrean beberapa detik
                     kemudian. Yang berubah hanya arahnya, dari di sebelah
                     tombol jadi di bawahnya, karena tombolnya naik ke baris
                     kontrol. Alasannya tidak diulang di bawah tombol:
                     menuliskannya dua kali hanya membuat keduanya lebih mudah
                     diabaikan. -->
                <AiQuotaMeter :quota="aiQuota" variant="compact" />
            </form>
        </div>

        <!-- ── Result ──────────────────────────────────────────────────────── -->
        <div v-if="displayed" ref="resultCard" class="scroll-mt-6 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">{{ typeLabels[displayed.type] }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5">{{ periodLabel(displayed) }}</p>
                </div>
                <span
                    class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium"
                    :class="statusMeta[displayed.status]?.class"
                >
                    {{ statusMeta[displayed.status]?.label ?? displayed.status }}
                </span>
            </div>

            <!-- Processing / pending skeleton -->
            <div
                v-if="displayed.status === 'pending' || displayed.status === 'processing'"
                class="space-y-3"
                role="status"
                aria-busy="true"
            >
                <div class="flex items-center gap-2 text-sm text-blue-600">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Memproses analisis…
                </div>
                <!-- Kerangka teks memakai komponen bersama ([BL-037]); bukan
                     blok animate-pulse sendiri. Yang ditunggu di sini pekerjaan
                     antrean, bukan prop Inertia — tapi bahasa pemuatannya tetap
                     satu. -->
                <SkeletonText :lines="4" line-class="h-3" last-width="w-2/3" />
            </div>

            <!-- Failed -->
            <div
                v-else-if="displayed.status === 'failed'"
                class="flex items-start gap-2 rounded-lg bg-destructive/5 border border-destructive/20 px-4 py-3 text-sm text-destructive"
            >
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ displayed.error || 'Analisis gagal diproses.' }}</span>
            </div>

            <!-- Completed -->
            <div v-else-if="displayed.status === 'completed'" class="space-y-4">
                <div class="grid gap-3 sm:grid-cols-3">
                    <MetricCard
                        title="Tipe"
                        :value="typeLabels[displayed.type]"
                        icon="chart"
                        color="primary"
                    />
                    <MetricCard
                        title="Rentang Data"
                        :value="periodDays(displayed) ? `${periodDays(displayed)} hari` : '—'"
                        :subtitle="periodLabel(displayed)"
                        icon="average"
                        color="muted"
                    />
                    <MetricCard
                        title="Dibuat"
                        :value="formatDate(displayed.created_at)"
                        icon="receipt"
                        color="success"
                    />
                </div>

                <article
                    class="ai-result max-w-none text-sm text-gray-700 leading-relaxed"
                    @click="openVariantLink"
                    v-html="resultHtml"
                ></article>

                <p v-if="missingMentioned.length" class="text-xs text-gray-500 leading-relaxed">
                    Nama bergaris putus-putus sudah tidak ada di katalog:
                    <strong>{{ missingMentioned.join(', ') }}</strong>.
                    Angkanya tetap benar — barangnya memang pernah terjual di rentang ini.
                </p>
            </div>
        </div>

        <!-- ── History ─────────────────────────────────────────────────────── -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-base font-semibold text-gray-900">Riwayat Analisis</h2>
            </div>

            <div v-if="analyses.length === 0" class="px-6 py-10 text-center text-sm text-gray-400">
                Belum ada analisis. Buat analisis pertama Anda di atas.
            </div>

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-medium text-gray-400 border-b border-gray-100">
                        <th class="px-6 py-3">Tipe</th>
                        <th class="px-6 py-3 hidden sm:table-cell">Periode</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3 hidden md:table-cell">Waktu</th>
                        <th class="px-6 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="analysis in analyses"
                        :key="analysis.id"
                        class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors"
                        :class="{ 'bg-primary/5': displayed && displayed.id === analysis.id }"
                    >
                        <td class="px-6 py-3 font-medium text-gray-700">{{ typeLabels[analysis.type] }}</td>
                        <td class="px-6 py-3 text-gray-500 hidden sm:table-cell">{{ periodLabel(analysis) }}</td>
                        <td class="px-6 py-3">
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                :class="statusMeta[analysis.status]?.class"
                            >
                                {{ statusMeta[analysis.status]?.label ?? analysis.status }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-gray-400 hidden md:table-cell">{{ formatDate(analysis.created_at) }}</td>
                        <!-- Tombol betulan, bukan teks berwarna: baris riwayat
                             sudah punya beberapa teks berwarna sendiri (badge
                             status), jadi satu-satunya hal yang bisa DITEKAN di
                             baris ini perlu terlihat seperti tombol. -->
                        <td class="px-6 py-3 text-right">
                            <Button
                                :variant="displayed && displayed.id === analysis.id ? 'primary' : 'soft'"
                                size="sm"
                                @click="selectAnalysis(analysis)"
                            >
                                {{ displayed && displayed.id === analysis.id ? 'Ditampilkan' : 'Lihat' }}
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
.ai-result :deep(h3) {
    font-size: 1rem;
    font-weight: 700;
    color: #111827;
    margin: 1rem 0 0.5rem;
}
/* `##` adalah penanda bagian yang diminta prompt, dan ia mendarat di sini
   sebagai h4 — jadi h4 perlu terbaca sebagai judul bagian, bukan sebagai teks
   biasa yang kebetulan tebal. */
.ai-result :deep(h4) {
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
    margin: 1.25rem 0 0.5rem;
}
.ai-result :deep(h4:first-child) {
    margin-top: 0;
}
.ai-result :deep(h5),
.ai-result :deep(h6) {
    font-size: 0.9375rem;
    font-weight: 600;
    color: #1f2937;
    margin: 0.875rem 0 0.375rem;
}
.ai-result :deep(p) {
    margin: 0.5rem 0;
}
.ai-result :deep(ul),
.ai-result :deep(ol) {
    margin: 0.5rem 0;
    padding-left: 1.25rem;
}
/* Butir penjelas di bawah satu langkah bernomor sekarang benar-benar berada DI
   DALAM langkahnya; tanpa ini jaraknya menggandakan margin butir induknya. */
.ai-result :deep(li > ul),
.ai-result :deep(li > ol) {
    margin: 0.25rem 0 0.25rem;
}
.ai-result :deep(li > p) {
    margin: 0.25rem 0;
}
.ai-result :deep(ul) {
    list-style: disc;
}
.ai-result :deep(ol) {
    list-style: decimal;
}
.ai-result :deep(li) {
    margin: 0.25rem 0;
}
.ai-result :deep(strong) {
    font-weight: 600;
    color: #111827;
}
</style>
