<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { useForm, router, Link, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import DatePicker from '@/Components/DatePicker.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import MetricCard from '@/Components/MetricCard.vue';
import Button from '@/Components/Button.vue';
import AiQuotaMeter from '@/Components/AiQuotaMeter.vue';
import { useAiQuota } from '@/composables/useAiQuota';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    analyses: { type: Array, default: () => [] },
    active: { type: Object, default: null },
    // Kuota dikirim ke HALAMAN INI, bukan cuma ke Pengaturan (`[BL-062]`):
    // di sinilah ia dibelanjakan, jadi di sinilah sisanya perlu terbaca
    // SEBELUM tombolnya ditekan.
    aiQuota: { type: Object, default: () => ({}) },
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
const toStr = (d) => [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
].join('-');

const today = new Date();
const thirtyDaysAgo = new Date();
thirtyDaysAgo.setDate(today.getDate() - 29);

const form = useForm({
    type: 'general',
    from: toStr(thirtyDaysAgo),
    to: toStr(today),
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
let timer = null;
watch(
    () => displayed.value?.status,
    (status) => {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
        if (status === 'pending' || status === 'processing') {
            timer = setTimeout(() => {
                router.reload({ only: ['active', 'analyses', 'aiQuota'] });
            }, 3000);
        }
    },
    { immediate: true },
);
onUnmounted(() => timer && clearTimeout(timer));

// ── Result metrics ───────────────────────────────────────────────────────────
const formatDate = (value) => {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleDateString('id-ID', {
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

const numberFmt = new Intl.NumberFormat('id-ID');

// ── Minimal, safe markdown renderer ──────────────────────────────────────────
// Escapes HTML first, then applies a small subset of markdown so LLM output
// renders readably without pulling in a dependency.
const escapeHtml = (str) =>
    str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

const renderMarkdown = (raw) => {
    if (!raw) {
        return '';
    }

    const lines = escapeHtml(raw).split('\n');
    const html = [];
    let listType = null; // 'ul' | 'ol'

    const bulletRe = /^[-*]\s+(.*)$/;
    const orderedRe = /^\d+\.\s+(.*)$/;

    const closeList = () => {
        if (listType) {
            html.push(`</${listType}>`);
            listType = null;
        }
    };

    // Whether the next non-empty line continues the currently open list. LLMs
    // often insert a blank line between list items for readability; without this
    // each item would close and reopen the list, restarting <ol> numbering at 1.
    const nextLineContinuesList = (startIndex) => {
        for (let j = startIndex; j < lines.length; j++) {
            const next = lines[j].trim();
            if (next === '') {
                continue;
            }
            if (listType === 'ul') {
                return bulletRe.test(next);
            }
            if (listType === 'ol') {
                return orderedRe.test(next);
            }
            return false;
        }
        return false;
    };

    const inline = (text) =>
        text
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/(^|[^*])\*(?!\s)(.+?)\*/g, '$1<em>$2</em>');

    for (let i = 0; i < lines.length; i++) {
        const trimmed = lines[i].trim();

        if (trimmed === '') {
            // Keep the list open across blank separators between items.
            if (!nextLineContinuesList(i + 1)) {
                closeList();
            }
            continue;
        }

        const heading = trimmed.match(/^(#{1,4})\s+(.*)$/);
        if (heading) {
            closeList();
            const level = heading[1].length + 2; // # → h3
            html.push(`<h${level}>${inline(heading[2])}</h${level}>`);
            continue;
        }

        const bullet = trimmed.match(bulletRe);
        if (bullet) {
            if (listType !== 'ul') {
                closeList();
                html.push('<ul>');
                listType = 'ul';
            }
            html.push(`<li>${inline(bullet[1])}</li>`);
            continue;
        }

        const ordered = trimmed.match(orderedRe);
        if (ordered) {
            if (listType !== 'ol') {
                closeList();
                html.push('<ol>');
                listType = 'ol';
            }
            html.push(`<li>${inline(ordered[1])}</li>`);
            continue;
        }

        closeList();
        html.push(`<p>${inline(trimmed)}</p>`);
    }

    closeList();
    return html.join('');
};

const resultHtml = computed(() => renderMarkdown(displayed.value?.result ?? ''));

const selectAnalysis = (analysis) => {
    selectedId.value = analysis.id;
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

            <form @submit.prevent="submit" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <!-- Type -->
                    <SelectDropdown
                        v-model="form.type"
                        :options="typeOptions"
                        label="Tipe Analisis"
                        :error="form.errors.type"
                    />

                    <!-- Period -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Periode</label>
                        <div class="flex items-center gap-2">
                            <DatePicker v-model="form.from" />
                            <span class="text-gray-400 text-sm">–</span>
                            <DatePicker v-model="form.to" />
                        </div>
                        <p v-if="form.errors.from" class="mt-1 text-xs text-red-600">{{ form.errors.from }}</p>
                        <p v-else-if="form.errors.to" class="mt-1 text-xs text-red-600">{{ form.errors.to }}</p>
                    </div>
                </div>

                <!-- Custom prompt -->
                <div v-if="form.type === 'custom'">
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

                <!-- Sisa kuota berdiri tepat di sebelah tombol yang
                     membelanjakannya, dan tombolnya mati saat jatahnya nol:
                     menolak di layar jauh lebih murah — dan jauh lebih jelas —
                     daripada menolak di antrean beberapa detik kemudian. -->
                <div class="flex flex-col gap-3 pt-1 sm:flex-row sm:items-center sm:justify-between">
                    <AiQuotaMeter :quota="aiQuota" variant="compact" class="sm:max-w-md" />

                    <!-- Alasannya tidak diulang di bawah tombol: meternya
                         berdiri di baris yang sama, dan menuliskannya dua kali
                         hanya membuat keduanya lebih mudah diabaikan. -->
                    <Button type="submit" :loading="form.processing" :disabled="quotaBlocked" class="shrink-0">
                        <template #icon>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                            </svg>
                        </template>
                        {{ form.processing ? 'Memproses...' : 'Analisa' }}
                    </Button>
                </div>
            </form>
        </div>

        <!-- ── Result ──────────────────────────────────────────────────────── -->
        <div v-if="displayed" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
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
            <div v-if="displayed.status === 'pending' || displayed.status === 'processing'" class="space-y-3">
                <div class="flex items-center gap-2 text-sm text-blue-600">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                    </svg>
                    Memproses analisis…
                </div>
                <div class="space-y-2 animate-pulse">
                    <div class="h-3 bg-gray-100 rounded w-3/4"></div>
                    <div class="h-3 bg-gray-100 rounded w-full"></div>
                    <div class="h-3 bg-gray-100 rounded w-5/6"></div>
                    <div class="h-3 bg-gray-100 rounded w-2/3"></div>
                </div>
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
                        title="Token Terpakai"
                        :value="displayed.tokens_used ? numberFmt.format(displayed.tokens_used) : '—'"
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
                    v-html="resultHtml"
                ></article>
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
                        <td class="px-6 py-3 text-right">
                            <button
                                type="button"
                                class="text-primary hover:text-primary/80 text-sm font-medium"
                                @click="selectAnalysis(analysis)"
                            >
                                Lihat →
                            </button>
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
.ai-result :deep(h4),
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
