<script setup>
import { usePage, router } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import { ref } from 'vue';

const props = defineProps({
    openDrawer: Object,
});

const { auth } = usePage().props;

const openingAmount = ref('');
const closingAmount = ref('');
const notes = ref('');
const processing = ref(false);

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const openCashDrawer = () => {
    if (processing.value) return;
    processing.value = true;
    router.post('/cashier/cash-drawer/open', {
        opening_amount: Number(openingAmount.value) || 0,
    }, {
        onFinish: () => processing.value = false,
    });
};

const closeCashDrawer = () => {
    if (processing.value) return;
    processing.value = true;
    router.post('/cashier/cash-drawer/close', {
        closing_amount: Number(closingAmount.value) || 0,
        notes: notes.value || null,
    }, {
        onFinish: () => processing.value = false,
    });
};

const goToPOS = () => {
    router.get('/cashier/pos');
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
            <h1 class="text-xl font-bold text-indigo-600">SAPI — Kas</h1>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-600">{{ auth.user.name }}</span>
                <button @click="logout" class="text-sm text-red-600 hover:text-red-800">Logout</button>
            </div>
        </nav>

        <main class="max-w-lg mx-auto py-12 px-6">
            <!-- Belum ada sesi terbuka → Form Buka Kas -->
            <div v-if="!openDrawer" class="bg-white rounded-xl shadow-lg p-8">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-indigo-100 rounded-full mb-4">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-semibold text-gray-800">Buka Kas</h2>
                    <p class="text-gray-500 mt-1">Masukkan jumlah uang awal di laci kas</p>
                </div>

                <form @submit.prevent="openCashDrawer" class="space-y-4">
                    <div>
                        <label for="opening_amount" class="block text-sm font-medium text-gray-700 mb-1">
                            Uang Awal (Rp)
                        </label>
                        <input
                            id="opening_amount"
                            v-model="openingAmount"
                            type="number"
                            min="0"
                            step="1000"
                            placeholder="0"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg text-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            required
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="processing"
                        class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition disabled:opacity-50"
                    >
                        {{ processing ? 'Memproses...' : 'Buka Kas & Mulai Jualan' }}
                    </button>
                </form>
            </div>

            <!-- Sesi sudah terbuka → Info + Tutup Kas -->
            <div v-else class="bg-white rounded-xl shadow-lg p-8">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-semibold text-gray-800">Sesi Kas Aktif</h2>
                </div>

                <!-- Info Sesi -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6 space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Dibuka pada</span>
                        <span class="font-medium text-gray-800">{{ new Date(openDrawer.opened_at).toLocaleString('id-ID') }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Modal awal</span>
                        <span class="font-medium text-gray-800">{{ formatCurrency(openDrawer.opening_amount) }}</span>
                    </div>
                </div>

                <!-- Tombol ke POS -->
                <button
                    @click="goToPOS"
                    class="w-full py-3 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition mb-3"
                >
                    Lanjut ke POS
                </button>

                <!-- Form Tutup Kas -->
                <div class="border-t border-gray-200 pt-6 mt-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Tutup Kas</h3>
                    <form @submit.prevent="closeCashDrawer" class="space-y-4">
                        <div>
                            <label for="closing_amount" class="block text-sm font-medium text-gray-700 mb-1">
                                Jumlah Uang Aktual di Laci (Rp)
                            </label>
                            <input
                                id="closing_amount"
                                v-model="closingAmount"
                                type="number"
                                min="0"
                                step="1000"
                                placeholder="Hitung uang fisik lalu masukkan"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                required
                            />
                        </div>
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                                Catatan (opsional)
                            </label>
                            <textarea
                                id="notes"
                                v-model="notes"
                                rows="2"
                                placeholder="Catatan akhir shift..."
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <button
                            type="submit"
                            :disabled="processing"
                            class="w-full py-3 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition disabled:opacity-50"
                        >
                            {{ processing ? 'Memproses...' : 'Tutup Kas' }}
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</template>
