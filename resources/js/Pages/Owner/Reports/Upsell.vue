<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import MetricCard from '@/Components/MetricCard.vue';
import DatePicker from '@/Components/DatePicker.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    filters: Object,
    summary: Object,
    byType: Array,
    bySurface: Array,
    topSuggestions: Array,
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
            <MetricCard title="Diambil" :value="summary.accepted" icon="receipt" color="primary" />
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
        </template>
    </div>
</template>
