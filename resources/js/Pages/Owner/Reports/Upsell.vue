<script setup>
import { ref } from 'vue';
import { Deferred, Head, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import MetricCard from '@/Components/MetricCard.vue';
import DatePicker from '@/Components/DatePicker.vue';
import SkeletonPanel from '@/Components/Skeleton/SkeletonPanel.vue';
import SkeletonTable from '@/Components/Skeleton/SkeletonTable.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    filters: Object,
    summary: Object,
    // Ditunda ([BL-037]) — null selama rinciannya masih dimuat.
    byType: { type: Array, default: null },
    bySurface: { type: Array, default: null },
    topSuggestions: { type: Array, default: null },
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const formatCurrency = (value) => 'Rp ' + Number(value ?? 0).toLocaleString('id-ID');

const TYPE_LABELS = {
    attach: 'Tambah add-on',
    pressed_stock: 'Barang tertekan',
    upsize: 'Naik ukuran',
};

const SURFACE_LABELS = {
    pos: 'Kasir (POS)',
    self_order: 'Self-order',
};

const bucketLabel = (bucket, map) => map[bucket] ?? bucket;

const rate = (accepted, shown) => (shown > 0 ? Math.round((accepted / shown) * 1000) / 10 : 0);

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
                <p class="text-sm text-gray-500 mt-1">
                    Berapa saran yang muncul, berapa yang diambil, dan berapa tambahan omzetnya.
                </p>
            </div>
            <div class="flex items-end gap-2">
                <DatePicker v-model="from" @update:modelValue="applyFilter" />
                <span class="pb-2 text-sm text-gray-400">—</span>
                <DatePicker v-model="to" @update:modelValue="applyFilter" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <MetricCard title="Saran Tampil" :value="summary.shown" icon="chart" color="muted" />
            <MetricCard title="Diterima" :value="summary.accepted" icon="receipt" color="primary" />
            <MetricCard
                title="Tingkat Terima"
                :value="summary.conversion_rate + '%'"
                icon="average"
                color="success"
            />
            <MetricCard
                title="Tambahan Omzet"
                :value="formatCurrency(summary.extra_revenue)"
                icon="currency"
                color="success"
            />
        </div>

        <!-- Dua angka yang mudah tertukar, dan bedanya menentukan apa yang
             sebenarnya sedang dinilai. `Tingkat Terima` dihitung dari SEMUA
             saran yang tampil, jadi ia ikut mengukur seberapa sering kasir
             benar-benar menawarkan. `Tingkat Sukses Tawar` hanya menghitung
             yang benar-benar sampai ke pelanggan — inilah yang menilai mutu
             sarannya sendiri. ([BL-025]) -->
        <div v-if="summary.offered > 0" class="rounded-lg border border-border bg-muted/40 px-4 py-3">
            <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1 text-sm">
                <span class="font-medium text-foreground">
                    Tingkat sukses tawar: {{ summary.offer_rate }}%
                </span>
                <span class="text-muted-foreground">
                    dari {{ summary.offered }} yang benar-benar ditawarkan
                    ({{ summary.accepted }} diterima, {{ summary.rejected }} ditolak)
                </span>
                <span v-if="summary.shown > summary.offered" class="text-muted-foreground">
                    · {{ summary.shown - summary.offered }} tampil tanpa dijawab
                </span>
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
                Saran baru tercatat saat transaksi benar-benar jadi — keranjang yang dibatalkan tidak dihitung.
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
                    <div class="px-5 py-3 border-b border-gray-100">
                        <h2 class="text-sm font-semibold text-gray-800">Per Jenis Saran</h2>
                        <p class="text-xs text-gray-500 mt-0.5">
                            Jenis yang tak pernah diterima bisa dimatikan di <code>config/upsell.php</code>.
                        </p>
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
                                <td class="px-5 py-2.5 text-gray-800">{{ bucketLabel(row.bucket, TYPE_LABELS) }}</td>
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
                        <p class="text-xs text-gray-500 mt-0.5">
                            Kasir harus mengucapkan tawarannya; self-order tidak.
                        </p>
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
