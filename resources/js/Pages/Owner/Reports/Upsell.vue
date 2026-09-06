<script setup>
import { computed, ref } from 'vue';
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import MetricCard from '@/Components/MetricCard.vue';
import DatePicker from '@/Components/DatePicker.vue';
import SkeletonPanel from '@/Components/Skeleton/SkeletonPanel.vue';
import SkeletonTable from '@/Components/Skeleton/SkeletonTable.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    filters: Object,
    summary: Object,
    // Gabungan vs mesin vs aturan sendiri ([BL-092]).
    sources: { type: Object, default: () => ({ auto: null, manual: null }) },
    // Ditunda ([BL-037]) — null selama rinciannya masih dimuat.
    byType: { type: Array, default: null },
    bySurface: { type: Array, default: null },
    topSuggestions: { type: Array, default: null },
    // Jenis yang saat ini tidak menghasilkan apa pun ([BL-099]). Tanpa ini,
    // nol pada baris jenis yang sudah dimatikan terbaca sebagai jenis yang
    // gagal — dan owner mematikan sesuatu yang sudah mati.
    inactiveTypes: { type: Array, default: () => [] },
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const formatCurrency = (value) => 'Rp ' + Number(value ?? 0).toLocaleString('id-ID');

const rate = (accepted, shown) => (shown > 0 ? Math.round((accepted / shown) * 1000) / 10 : 0);

const TYPE_LABELS = {
    attach: 'Tambah add-on',
    pressed_stock: 'Barang tertekan',
    upsize: 'Naik ukuran',
    // Tanpa baris ini tabelnya menampilkan kata "manual" mentah — satu-satunya
    // jenis yang owner tulis sendiri justru yang paling tidak dikenali.
    manual: 'Aturan Anda sendiri',
};

/**
 * Perjalanan satu saran, dari muncul sampai dibeli.
 *
 * Menggantikan empat kartu dan satu strip abu-abu yang memuat DUA persentase
 * berpenyebut berbeda — `accepted/shown` dan `accepted/offered` — beserta
 * paragraf yang mencoba menerangkan bedanya. Dua penyebut yang harus diingat
 * adalah dua penyebut yang tertukar; di sini masing-masing menempel pada
 * batang yang menjadi penyebutnya, dan selisih "tampil tanpa dijawab" terbaca
 * sebagai penyempitan batang, bukan sebagai kalimat. ([BL-025])
 */
const funnel = computed(() => {
    const { shown, offered, accepted, rejected, offer_rate: offerRate } = props.summary;

    const width = (value) => (shown > 0 ? (value / shown) * 100 : 0) + '%';

    return [
        {
            key: 'shown',
            label: 'Muncul di layar',
            value: shown,
            width: '100%',
            tone: 'bg-gray-200 text-gray-700',
            rate: null,
        },
        {
            key: 'offered',
            label: 'Ditawarkan kasir',
            value: offered,
            width: width(offered),
            tone: 'bg-gray-300 text-gray-800',
            rate: shown > 0 ? `${rate(offered, shown)}% dari yang muncul` : null,
        },
        {
            key: 'accepted',
            label: 'Jadi dibeli',
            value: accepted,
            width: width(accepted),
            tone: 'bg-success/20 text-success',
            rate: offered > 0 ? `${offerRate}% dari yang ditawarkan · ${rejected} ditolak` : null,
        },
    ];
});

/**
 * Dua kolom perbandingan ([BL-092]).
 *
 * Kolom "Gabungan" dibuang: isinya salinan persis corong di atasnya, dan
 * angka yang sama muncul dua kali di satu layar membuat pembacanya mencari
 * beda yang tidak ada.
 */
const sourceColumns = computed(() => [
    {
        key: 'auto',
        title: 'Otomatis (sistem)',
        href: null,
        accent: 'text-sky-700',
        summary: props.sources?.auto,
    },
    {
        key: 'manual',
        title: 'Aturan Anda',
        href: '/owner/upsell-rules',
        accent: 'text-emerald-700',
        summary: props.sources?.manual,
    },
].filter((column) => column.summary));

const SURFACE_LABELS = {
    pos: 'Kasir (POS)',
    self_order: 'Self-order',
};

const bucketLabel = (bucket, map) => map[bucket] ?? bucket;

const applyFilter = () => {
    router.get('/owner/reports/upsell', { from: from.value, to: to.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Laporan Saran Jual" />

    <div class="max-w-6xl mx-auto space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Saran Jual (Upsell)</h1>
            </div>
            <div class="flex items-end gap-2">
                <DatePicker v-model="from" @update:modelValue="applyFilter" />
                <span class="pb-2 text-sm text-gray-400">—</span>
                <DatePicker v-model="to" @update:modelValue="applyFilter" />
            </div>
        </div>

        <!-- Bentuknya sendiri yang menjelaskan: batang yang menyempit ADALAH
             saran yang hilang di tiap tahap, dan tiap persentase menempel pada
             batang yang jadi penyebutnya. ([BL-025]) -->
        <div v-if="summary.shown > 0" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 lg:col-span-2">
                <div class="space-y-2.5">
                    <div v-for="stage in funnel" :key="stage.key" class="flex items-start gap-3">
                        <span class="w-24 shrink-0 pt-1.5 text-sm text-gray-600 sm:w-32">{{ stage.label }}</span>

                        <!-- Persentasenya turun ke bawah batang di layar sempit.
                             Ia tidak boleh dipaksa satu baris: "25% dari yang
                             ditawarkan · 3 ditolak" lebih panjang daripada sisa
                             ruang di sebelah batang 375px, dan yang meluber
                             keluar kartu tidak terbaca sama sekali. -->
                        <div class="flex min-w-0 flex-1 flex-col gap-1 lg:flex-row lg:items-center lg:gap-2">
                            <div
                                :class="['flex h-8 shrink-0 items-center justify-end rounded-md px-2.5', stage.tone]"
                                :style="{ width: stage.width, minWidth: '3rem' }"
                            >
                                <span class="text-sm font-semibold">{{ stage.value }}</span>
                            </div>
                            <span v-if="stage.rate" class="min-w-0 text-xs text-gray-500">
                                {{ stage.rate }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <MetricCard
                title="Tambahan Omzet"
                :value="formatCurrency(summary.extra_revenue)"
                icon="currency"
                color="success"
            />
        </div>

        <!-- Mesin vs aturan sendiri ([BL-092]). Berdampingan, bukan bergantian:
             perbandingan yang menuntut owner mengingat angka dari layar
             sebelumnya adalah perbandingan yang tidak pernah terjadi. -->
        <div v-if="summary.shown > 0" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h2 class="text-sm font-semibold text-gray-800">Otomatis vs Aturan Anda</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-gray-100">
                <div v-for="column in sourceColumns" :key="column.key" class="p-5">
                    <!-- Tautan, bukan keterangan: pertanyaan yang menyusul angka
                         ini selalu "di mana saya mengubahnya". -->
                    <a
                        v-if="column.href"
                        :href="column.href"
                        :class="['text-sm font-semibold underline decoration-transparent hover:decoration-current', column.accent]"
                    >{{ column.title }}</a>
                    <p v-else :class="['text-sm font-semibold', column.accent]">{{ column.title }}</p>

                    <div v-if="column.summary.shown === 0" class="mt-3 text-xs text-gray-400">
                        Belum ada saran dari sumber ini pada rentang ini.
                    </div>

                    <dl v-else class="mt-3 space-y-1.5 text-sm">
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500">Tampil</dt>
                            <dd class="font-medium text-gray-800">{{ column.summary.shown }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500">Ditawarkan</dt>
                            <dd class="font-medium text-gray-800">{{ column.summary.offered }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500">Diterima</dt>
                            <dd class="font-medium text-gray-800">{{ column.summary.accepted }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-gray-500">Sukses tawar</dt>
                            <dd class="font-semibold text-gray-900">
                                {{ column.summary.offered > 0 ? column.summary.offer_rate + '%' : '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-2 pt-1.5 border-t border-gray-100">
                            <dt class="text-gray-500">Tambahan omzet</dt>
                            <dd class="font-semibold text-gray-900">{{ formatCurrency(column.summary.extra_revenue) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Angka nol bukan kegagalan sistem; bedakan supaya owner tidak
             menyimpulkan fiturnya rusak. -->
        <div
            v-if="summary.shown === 0"
            class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center"
        >
            <p class="text-sm font-medium text-gray-700">Belum ada saran yang tercatat pada rentang ini.</p>
            <p class="mt-1 text-xs text-gray-500">
                Saran baru tercatat saat transaksi benar-benar jadi — keranjang yang dibatalkan dan transaksi
                yang di-void tidak dihitung.
            </p>
        </div>

        <template v-else>
            <!-- Rincian ditunda ([BL-037]): ringkasan di atas sudah terbaca,
                 ketiga tabel ini menyusul dalam dua permintaan. -->
            <Deferred :data="['byType', 'bySurface']">
                <template #fallback>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <SkeletonPanel flush label="Memuat rekap per jenis saran…">
                            <SkeletonTable :rows="4" :columns="4" />
                        </SkeletonPanel>
                        <SkeletonPanel flush label="Memuat rekap per permukaan…">
                            <SkeletonTable :rows="4" :columns="4" />
                        </SkeletonPanel>
                    </div>
                </template>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <!-- Kesimpulan dan tindakannya bersebelahan ([BL-099]): tabel
                         inilah yang memperlihatkan jenis mana yang tidak pernah
                         diterima, dan sampai sekarang tempat mematikannya tidak
                         punya jalan dari sini. -->
                    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold text-gray-800">Per Jenis Saran</h2>
                        <Link
                            href="/owner/settings/operations"
                            class="text-xs text-primary hover:underline font-medium shrink-0"
                        >
                            Atur jenis
                        </Link>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500">
                            <tr>
                                <th class="px-5 py-2 text-left font-medium">Jenis</th>
                                <th class="px-3 py-2 text-right font-medium">Tampil</th>
                                <th class="px-3 py-2 text-right font-medium">Diambil</th>
                                <th class="px-5 py-2 text-right font-medium">Omzet</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in byType" :key="row.bucket">
                                <td class="px-5 py-2.5 text-gray-800">
                                    {{ bucketLabel(row.bucket, TYPE_LABELS) }}
                                    <span
                                        v-if="inactiveTypes.includes(row.bucket)"
                                        class="ml-1.5 align-middle rounded px-1.5 py-0.5 bg-muted text-muted-foreground text-[10px] font-medium"
                                    >
                                        dimatikan
                                    </span>
                                </td>
                                <td class="px-3 py-2.5 text-right text-gray-600">{{ row.shown }}</td>
                                <td class="px-3 py-2.5 text-right text-gray-600">
                                    {{ row.accepted }}
                                    <span class="text-xs text-gray-400">({{ rate(row.accepted, row.shown) }}%)</span>
                                </td>
                                <td class="px-5 py-2.5 text-right font-medium text-gray-800">
                                    {{ formatCurrency(row.extra_revenue) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-800">Per Permukaan</h2>
                    </div>
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500">
                            <tr>
                                <th class="px-5 py-2 text-left font-medium">Permukaan</th>
                                <th class="px-3 py-2 text-right font-medium">Tampil</th>
                                <th class="px-3 py-2 text-right font-medium">Diambil</th>
                                <th class="px-5 py-2 text-right font-medium">Omzet</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in bySurface" :key="row.bucket">
                                <td class="px-5 py-2.5 text-gray-800">{{ bucketLabel(row.bucket, SURFACE_LABELS) }}</td>
                                <td class="px-3 py-2.5 text-right text-gray-600">{{ row.shown }}</td>
                                <td class="px-3 py-2.5 text-right text-gray-600">
                                    {{ row.accepted }}
                                    <span class="text-xs text-gray-400">({{ rate(row.accepted, row.shown) }}%)</span>
                                </td>
                                <td class="px-5 py-2.5 text-right font-medium text-gray-800">
                                    {{ formatCurrency(row.extra_revenue) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            </Deferred>

            <Deferred data="topSuggestions">
                <template #fallback>
                    <SkeletonPanel flush label="Memuat saran yang paling sering muncul…">
                        <SkeletonTable :rows="6" :columns="5" />
                    </SkeletonPanel>
                </template>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h2 class="text-sm font-semibold text-gray-800">Saran Paling Sering Muncul</h2>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500">
                        <tr>
                            <th class="px-5 py-2 text-left font-medium">Saran</th>
                            <th class="px-3 py-2 text-left font-medium">Jenis</th>
                            <th class="px-3 py-2 text-right font-medium">Tampil</th>
                            <th class="px-3 py-2 text-right font-medium">Diambil</th>
                            <th class="px-5 py-2 text-right font-medium">Omzet</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="row in topSuggestions" :key="row.type + row.label">
                            <td class="px-5 py-2.5 text-gray-800">{{ row.label }}</td>
                            <td class="px-3 py-2.5 text-gray-500">{{ bucketLabel(row.type, TYPE_LABELS) }}</td>
                            <td class="px-3 py-2.5 text-right text-gray-600">{{ row.shown }}</td>
                            <td class="px-3 py-2.5 text-right text-gray-600">
                                {{ row.accepted }}
                                <span class="text-xs text-gray-400">({{ rate(row.accepted, row.shown) }}%)</span>
                            </td>
                            <td class="px-5 py-2.5 text-right font-medium text-gray-800">
                                {{ formatCurrency(row.extra_revenue) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </Deferred>
        </template>
    </div>
</template>
