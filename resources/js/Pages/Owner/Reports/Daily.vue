<script setup>
import { ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import MetricCard from '@/Components/MetricCard.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    date: String,
    summary: Object,
    transactions: Array,
    paymentSummary: Array,
    topProducts: Array,
});

const selectedDate = ref(props.date);

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatTime = (datetime) => {
    return new Date(datetime).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const changeDate = () => {
    router.get('/owner/reports/daily', { date: selectedDate.value }, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Expandable transactions
const expandedTxIds = ref(new Set());

const toggleTx = (id) => {
    if (expandedTxIds.value.has(id)) {
        expandedTxIds.value.delete(id);
    } else {
        expandedTxIds.value.add(id);
    }
};
</script>

<template>
    <Head title="Laporan Harian" />

    <div class="max-w-6xl mx-auto space-y-6">
        <!-- Header + Date Picker -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Laporan Harian</h1>
                <p class="text-sm text-gray-500 mt-1">Detail penjualan per hari</p>
            </div>
            <div class="flex items-center gap-2">
                <input
                    v-model="selectedDate"
                    type="date"
                    class="rounded-lg border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
                    @change="changeDate"
                />
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <MetricCard
                title="Total Pendapatan"
                :value="formatCurrency(summary.total_revenue)"
                icon="currency"
                color="green"
            />
            <MetricCard
                title="Total Transaksi"
                :value="summary.total_transactions"
                subtitle="transaksi selesai"
                icon="receipt"
                color="blue"
            />
            <MetricCard
                title="Transaksi Void"
                :value="summary.voided_count"
                subtitle="dibatalkan"
                icon="average"
                color="purple"
            />
        </div>

        <!-- Rekap per Payment Method -->
        <div v-if="paymentSummary.length > 0" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Rekap per Metode Pembayaran</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">Metode</th>
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">Tipe</th>
                            <th class="text-right py-2 px-3 text-gray-500 font-medium">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="pm in paymentSummary" :key="pm.name" class="border-b border-gray-50">
                            <td class="py-2.5 px-3 font-medium text-gray-800">{{ pm.name }}</td>
                            <td class="py-2.5 px-3">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                    :class="{
                                        'bg-green-100 text-green-800': pm.type === 'cash',
                                        'bg-blue-100 text-blue-800': pm.type === 'qris_static',
                                        'bg-purple-100 text-purple-800': pm.type === 'bank_transfer',
                                    }"
                                >
                                    {{ pm.type === 'cash' ? 'Tunai' : pm.type === 'qris_static' ? 'QRIS' : 'Transfer' }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right font-semibold text-gray-900">{{ formatCurrency(pm.total) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Top Products -->
        <div v-if="topProducts.length > 0" class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Top 10 Produk Terlaris</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">#</th>
                            <th class="text-left py-2 px-3 text-gray-500 font-medium">Varian</th>
                            <th class="text-right py-2 px-3 text-gray-500 font-medium">Qty Terjual</th>
                            <th class="text-right py-2 px-3 text-gray-500 font-medium">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(product, index) in topProducts" :key="product.variant_name" class="border-b border-gray-50">
                            <td class="py-2.5 px-3 text-gray-400">{{ index + 1 }}</td>
                            <td class="py-2.5 px-3 font-medium text-gray-800">{{ product.variant_name }}</td>
                            <td class="py-2.5 px-3 text-right text-gray-700">{{ product.total_qty }}</td>
                            <td class="py-2.5 px-3 text-right font-semibold text-gray-900">{{ formatCurrency(product.total_revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Transaction List -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Daftar Transaksi</h3>
            </div>

            <div v-if="transactions.length > 0">
                <div
                    v-for="tx in transactions"
                    :key="tx.id"
                    class="border-b border-gray-50 last:border-0"
                >
                    <!-- Transaction header row -->
                    <div
                        class="flex items-center justify-between px-5 py-3 cursor-pointer hover:bg-gray-50 transition-colors"
                        @click="toggleTx(tx.id)"
                    >
                        <div class="flex items-center gap-3">
                            <svg
                                class="w-4 h-4 text-gray-400 transition-transform"
                                :class="{ 'rotate-90': expandedTxIds.has(tx.id) }"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            <div>
                                <span class="text-sm font-medium text-gray-800">{{ tx.code }}</span>
                                <span class="text-xs text-gray-400 ml-2">{{ tx.user?.name }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm font-semibold text-gray-900">{{ formatCurrency(tx.total_amount) }}</span>
                            <span class="text-xs text-gray-400 ml-2">{{ formatTime(tx.created_at) }}</span>
                        </div>
                    </div>

                    <!-- Expanded detail -->
                    <div v-if="expandedTxIds.has(tx.id)" class="bg-gray-50 px-5 py-3 space-y-2">
                        <div v-for="item in tx.items" :key="item.id" class="flex justify-between text-xs text-gray-600">
                            <span>{{ item.variant_name }} × {{ item.qty }}</span>
                            <span class="font-medium">{{ formatCurrency(item.subtotal) }}</span>
                        </div>
                        <div v-if="tx.payments?.length" class="border-t border-gray-200 pt-2 mt-2">
                            <div v-for="pay in tx.payments" :key="pay.id" class="flex justify-between text-xs text-gray-500">
                                <span>{{ pay.payment_method?.name || 'Payment' }}</span>
                                <span>{{ formatCurrency(pay.amount) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="px-5 py-8 text-center text-sm text-gray-400">
                Tidak ada transaksi pada tanggal ini
            </div>
        </div>
    </div>
</template>
