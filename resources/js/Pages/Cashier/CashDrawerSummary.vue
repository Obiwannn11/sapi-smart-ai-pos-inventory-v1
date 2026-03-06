<script setup>
import { usePage, router } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';

const props = defineProps({
    cashDrawer: Object,
    paymentSummary: Array,
    transactionCount: Number,
});

const { auth } = usePage().props;

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatDate = (date) => {
    return new Date(date).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const goToCashDrawer = () => {
    router.get('/cashier/cash-drawer');
};

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <FlashMessage />

        <!-- Header -->
        <nav class="bg-white shadow px-6 py-4 flex items-center justify-between">
            <h1 class="text-xl font-bold text-indigo-600">SAPI — Rekap Kas</h1>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-600">{{ auth.user.name }}</span>
                <button @click="logout" class="text-sm text-red-600 hover:text-red-800">Logout</button>
            </div>
        </nav>

        <main class="max-w-lg mx-auto py-12 px-6">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Header -->
                <div class="bg-indigo-600 px-6 py-5 text-white text-center">
                    <h2 class="text-xl font-semibold">Rekap Sesi Kas</h2>
                    <p class="text-indigo-200 text-sm mt-1">Sesi telah ditutup</p>
                </div>

                <div class="p-6 space-y-5">
                    <!-- Waktu -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Buka Kas</p>
                            <p class="text-sm font-medium text-gray-800">{{ formatDate(cashDrawer.opened_at) }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <p class="text-xs text-gray-500 mb-1">Tutup Kas</p>
                            <p class="text-sm font-medium text-gray-800">{{ formatDate(cashDrawer.closed_at) }}</p>
                        </div>
                    </div>

                    <!-- Ringkasan -->
                    <div class="space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Modal Awal</span>
                            <span class="font-medium">{{ formatCurrency(cashDrawer.opening_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Jumlah Transaksi</span>
                            <span class="font-medium">{{ transactionCount }} transaksi</span>
                        </div>
                    </div>

                    <!-- Rekap per Payment Method -->
                    <div v-if="paymentSummary.length > 0" class="border-t border-gray-200 pt-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Pendapatan per Metode Pembayaran</h3>
                        <div class="space-y-2">
                            <div
                                v-for="pm in paymentSummary"
                                :key="pm.name"
                                class="flex justify-between items-center text-sm bg-gray-50 rounded-lg px-3 py-2"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                          :class="{
                                              'bg-green-100 text-green-800': pm.type === 'cash',
                                              'bg-blue-100 text-blue-800': pm.type === 'qris_static',
                                              'bg-purple-100 text-purple-800': pm.type === 'bank_transfer',
                                          }">
                                        {{ pm.type === 'cash' ? 'Tunai' : pm.type === 'qris_static' ? 'QRIS' : 'Transfer' }}
                                    </span>
                                    <span class="text-gray-700">{{ pm.name }}</span>
                                </div>
                                <span class="font-medium text-gray-800">{{ formatCurrency(pm.total) }}</span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="border-t border-gray-200 pt-4">
                        <p class="text-sm text-gray-400 text-center">Belum ada transaksi di sesi ini</p>
                    </div>

                    <!-- Expected vs Actual -->
                    <div class="border-t border-gray-200 pt-4 space-y-3">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Expected Cash (uang tunai di laci)</span>
                            <span class="font-medium">{{ formatCurrency(cashDrawer.expected_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Closing Amount (aktual)</span>
                            <span class="font-medium">{{ formatCurrency(cashDrawer.closing_amount) }}</span>
                        </div>
                        <div class="flex justify-between text-sm font-semibold border-t border-gray-200 pt-3">
                            <span>Selisih</span>
                            <span :class="{
                                'text-green-600': Number(cashDrawer.difference) >= 0,
                                'text-red-600': Number(cashDrawer.difference) < 0,
                            }">
                                {{ Number(cashDrawer.difference) >= 0 ? '+' : '' }}{{ formatCurrency(cashDrawer.difference) }}
                            </span>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div v-if="cashDrawer.notes" class="border-t border-gray-200 pt-4">
                        <p class="text-xs text-gray-500 mb-1">Catatan</p>
                        <p class="text-sm text-gray-700">{{ cashDrawer.notes }}</p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="px-6 pb-6 space-y-3">
                    <button
                        @click="goToCashDrawer"
                        class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition"
                    >
                        Buka Sesi Baru
                    </button>
                    <button
                        @click="logout"
                        class="w-full py-3 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition"
                    >
                        Logout
                    </button>
                </div>
            </div>
        </main>
    </div>
</template>
