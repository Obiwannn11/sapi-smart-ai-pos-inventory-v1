<script setup>
import { computed, ref } from 'vue';

/**
 * Produk terlaris, dengan pecahan variannya di bawah tiap baris.
 *
 * Barisnya adalah PRODUK. Sebelum ini tabelnya dikelompokkan pada
 * `transaction_items.variant_name`, dan yang terbaca pemilik adalah "Single",
 * "Iced", "Plain" — penanda varian yang dipakai ulang oleh belasan produk,
 * bukan nama barang. Rinciannya tetap ada, tapi sebagai lapisan kedua: produk
 * menjawab "apa yang laku", varian menjawab "dalam bentuk apa".
 */
const props = defineProps({
    title: { type: String, required: true },
    products: { type: Array, default: () => [] },
    // Kolom omzet dinamai berbeda di dua laporan yang memakai tabel ini.
    revenueLabel: { type: String, default: 'Omzet' },
});

const formatCurrency = (value) => 'Rp ' + Math.round(Number(value)).toLocaleString('id-ID');

/**
 * Kolom harga normal dan potongan hanya muncul kalau periodenya memang punya
 * potongan ([BL-116] butir 2).
 *
 * Dua kolom nol di setiap baris bukan kejujuran, melainkan derau yang harus
 * dibaca ulang tiap bulan oleh mayoritas yang tidak pernah mendiskon — alasan
 * yang sama dengan kolom pajak di rekap bulanan. Yang menentukan adalah ANGKA
 * periodenya, bukan setelan tenant hari ini, supaya laporan bulan lalu tetap
 * bisa menjelaskan dirinya sesudah aturan diskonnya dihapus.
 */
const hasDiscounts = computed(
    () => props.products.some((product) => Number(product.total_discount ?? 0) > 0),
);

// Produk dengan satu varian tidak punya apa pun untuk dibuka — rinciannya
// sudah sama dengan barisnya sendiri.
const hasBreakdown = (product) => (product.variants?.length ?? 0) > 1;

const expanded = ref(new Set());

const toggle = (product) => {
    if (!hasBreakdown(product)) return;
    const next = new Set(expanded.value);
    next.has(product.product_id) ? next.delete(product.product_id) : next.add(product.product_id);
    expanded.value = next;
};

const isOpen = (product) => expanded.value.has(product.product_id);

const variantSummary = (product) => {
    const count = product.variants?.length ?? 0;
    if (count <= 1) return product.variants?.[0]?.variant_name ?? '';
    return `${count} varian`;
};
</script>

<template>
    <div v-if="products.length > 0" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ title }}</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left py-2 px-3 text-gray-500 font-medium w-8">#</th>
                        <th class="text-left py-2 px-3 text-gray-500 font-medium">Produk</th>
                        <th class="text-right py-2 px-3 text-gray-500 font-medium">Qty Terjual</th>
                        <!-- Urutannya mengikuti aliran uang: harga normal ->
                             potongan -> yang benar-benar tertagih. -->
                        <th v-if="hasDiscounts" class="text-right py-2 px-3 text-gray-500 font-medium">Harga Normal</th>
                        <th v-if="hasDiscounts" class="text-right py-2 px-3 text-gray-500 font-medium">Potongan</th>
                        <th class="text-right py-2 px-3 text-gray-500 font-medium">{{ revenueLabel }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(product, index) in products" :key="product.product_id">
                        <tr
                            class="border-b border-gray-50"
                            :class="hasBreakdown(product) ? 'cursor-pointer hover:bg-gray-50' : ''"
                            @click="toggle(product)"
                        >
                            <td class="py-2.5 px-3 text-gray-400 tabular-nums">{{ index + 1 }}</td>
                            <td class="py-2.5 px-3">
                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="hasBreakdown(product)"
                                        type="button"
                                        class="text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-ring rounded"
                                        :aria-expanded="isOpen(product)"
                                        :aria-label="`Rincian varian ${product.product_name}`"
                                        @click.stop="toggle(product)"
                                    >
                                        <svg
                                            class="w-4 h-4 transition-transform"
                                            :class="isOpen(product) ? 'rotate-90' : ''"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                    <span v-else class="w-4" />
                                    <span class="font-medium text-gray-800">{{ product.product_name }}</span>
                                    <span class="text-xs text-gray-400">{{ variantSummary(product) }}</span>
                                </div>
                            </td>
                            <td class="py-2.5 px-3 text-right text-gray-700 tabular-nums">{{ product.total_qty }}</td>
                            <td v-if="hasDiscounts" class="py-2.5 px-3 text-right text-gray-500 tabular-nums">
                                {{ formatCurrency(product.total_gross) }}
                            </td>
                            <td v-if="hasDiscounts" class="py-2.5 px-3 text-right tabular-nums" :class="product.total_discount > 0 ? 'text-amber-700' : 'text-gray-300'">
                                {{ product.total_discount > 0 ? '−' + formatCurrency(product.total_discount) : '—' }}
                            </td>
                            <td class="py-2.5 px-3 text-right font-semibold text-gray-900 tabular-nums">
                                {{ formatCurrency(product.total_revenue) }}
                            </td>
                        </tr>

                        <tr
                            v-for="variant in (isOpen(product) ? product.variants : [])"
                            :key="variant.variant_id"
                            class="border-b border-gray-50 bg-gray-50/60"
                        >
                            <td />
                            <td class="py-1.5 px-3 pl-9 text-gray-600">{{ variant.variant_name }}</td>
                            <td class="py-1.5 px-3 text-right text-gray-600 tabular-nums">{{ variant.total_qty }}</td>
                            <td v-if="hasDiscounts" class="py-1.5 px-3 text-right text-gray-400 tabular-nums">
                                {{ formatCurrency(variant.total_gross) }}
                            </td>
                            <td v-if="hasDiscounts" class="py-1.5 px-3 text-right tabular-nums" :class="variant.total_discount > 0 ? 'text-amber-600' : 'text-gray-300'">
                                {{ variant.total_discount > 0 ? '−' + formatCurrency(variant.total_discount) : '—' }}
                            </td>
                            <td class="py-1.5 px-3 text-right text-gray-600 tabular-nums">
                                {{ formatCurrency(variant.total_revenue) }}
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</template>
