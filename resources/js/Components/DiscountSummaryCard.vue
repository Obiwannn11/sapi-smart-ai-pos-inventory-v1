<script setup>
import { computed } from 'vue';

/**
 * Rekap potongan harga satu periode — sehari atau sebulan.
 *
 * TIGA angka, dan memisahkannya adalah inti kartu ini:
 *
 *   Harga normal — berapa yang akan tertagih kalau tidak ada potongan sama
 *     sekali. Ini "penjualan produk murni" yang ditanyakan pemilik ([BL-116]).
 *   Total dipotong — berapa yang dikorbankan untuk menghabiskan stok.
 *   Di bawah batas untung — bagian yang dijual RUGI, tiap barisnya dengan
 *     persetujuan owner dan alasan tertulis ([BL-018]).
 *
 * Angka ketiga yang paling ingin dilihat owner, dan tanpa dipisahkan ia
 * tenggelam di dalam angka kedua: penjualan rugi terlihat persis seperti
 * diskon 5% yang sehat.
 *
 * Dipakai rekap harian DAN rekap bulanan, dari satu pembaca yang sama di
 * `ReportController::discountSummary()`. Dua salinan kartu ini akan berbeda
 * suatu hari, dan yang berbeda adalah definisi uang.
 */
const props = defineProps({
    title: { type: String, default: 'Potongan Harga' },
    summary: { type: Object, required: true },
});

const formatCurrency = (value) => 'Rp ' + Math.round(Number(value)).toLocaleString('id-ID');

// Bagian potongan terhadap harga normal — yang menjawab "banyak dipakai atau
// tidak" tanpa harus membandingkan dua angka besar di kepala. Penyebutnya nol
// hanya kalau tidak ada penjualan sama sekali, dan kartunya pun tidak muncul.
const shareOfGross = computed(() => {
    const gross = Number(props.summary.gross_sales ?? 0);
    if (gross <= 0) return null;

    return (Number(props.summary.total_given ?? 0) / gross) * 100;
});

const formatShare = (pct) => pct.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + '%';

const lines = computed(() => props.summary.below_floor_lines ?? []);

// Daftar baris rugi dibatasi di server untuk periode panjang. Kalau terpotong,
// kartunya mengaku — daftar yang diam-diam terpotong membuat owner mengira
// sudah melihat seluruhnya.
const hiddenLines = computed(() => Math.max(0, Number(props.summary.below_floor_items ?? 0) - lines.value.length));
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ title }}</h3>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- Urutannya mengikuti aliran uang: harga normal -> potongan ->
                 bagian yang dijual rugi. -->
            <div class="rounded-lg border border-gray-200 p-3">
                <p class="text-xs text-gray-500">Harga normal barang</p>
                <p class="mt-0.5 text-lg font-bold text-gray-900 tabular-nums">{{ formatCurrency(summary.gross_sales) }}</p>
                <p class="text-xs text-gray-400">tertagih {{ formatCurrency(summary.net_sales) }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 p-3">
                <p class="text-xs text-gray-500">Total dipotong</p>
                <p class="mt-0.5 text-lg font-bold text-gray-900 tabular-nums">−{{ formatCurrency(summary.total_given) }}</p>
                <p class="text-xs text-gray-400">
                    {{ summary.items_discounted }} baris penjualan<span v-if="shareOfGross !== null"> · {{ formatShare(shareOfGross) }} dari harga normal</span>
                </p>
            </div>

            <div
                class="rounded-lg border p-3"
                :class="summary.below_floor_items > 0 ? 'border-amber-200 bg-amber-50' : 'border-gray-200'"
            >
                <p class="text-xs" :class="summary.below_floor_items > 0 ? 'text-amber-800' : 'text-gray-500'">
                    Di bawah batas untung
                </p>
                <p class="mt-0.5 text-lg font-bold tabular-nums" :class="summary.below_floor_items > 0 ? 'text-amber-900' : 'text-gray-900'">
                    {{ formatCurrency(summary.below_floor_total) }}
                </p>
                <p class="text-xs" :class="summary.below_floor_items > 0 ? 'text-amber-700' : 'text-gray-400'">
                    {{ summary.below_floor_items }} baris penjualan
                </p>
            </div>
        </div>

        <div v-if="lines.length > 0" class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left py-2 px-3 text-gray-500 font-medium">Varian</th>
                        <th class="text-right py-2 px-3 text-gray-500 font-medium">Qty</th>
                        <th class="text-right py-2 px-3 text-gray-500 font-medium">Dijual</th>
                        <th class="text-right py-2 px-3 text-gray-500 font-medium">Batas</th>
                        <th class="text-left py-2 px-3 text-gray-500 font-medium">Alasan</th>
                        <th class="text-left py-2 px-3 text-gray-500 font-medium">Disetujui</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(line, index) in lines" :key="index" class="border-b border-gray-50">
                        <td class="py-2.5 px-3 font-medium text-gray-800">{{ line.variant_name }}</td>
                        <td class="py-2.5 px-3 text-right text-gray-700">{{ line.qty }}</td>
                        <td class="py-2.5 px-3 text-right text-gray-900">
                            {{ formatCurrency(line.unit_price) }}
                            <span class="block text-xs text-gray-400 line-through">{{ formatCurrency(line.original_unit_price) }}</span>
                        </td>
                        <td class="py-2.5 px-3 text-right text-gray-500">{{ formatCurrency(line.floor) }}</td>
                        <td class="py-2.5 px-3 text-gray-600">{{ line.reason }}</td>
                        <td class="py-2.5 px-3 text-gray-600">{{ line.approved_by ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>

            <p v-if="hiddenLines > 0" class="mt-2 px-3 text-xs text-gray-500">
                Menampilkan {{ lines.length }} baris dengan potongan terbesar; {{ hiddenLines }} baris lainnya ada di unduhan CSV.
            </p>
        </div>
    </div>
</template>
