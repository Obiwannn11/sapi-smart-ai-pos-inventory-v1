<script setup>
import { router, Head } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import CashierTopbar from '@/Components/CashierTopbar.vue';
import { ref, computed, nextTick } from 'vue';

const props = defineProps({
    openDrawer: Object,
});

const openingAmount = ref(0);
const openingAmountDisplay = ref('0');
const closingAmount = ref(0);
const closingAmountDisplay = ref('');
const notes = ref('');
const processing = ref(false);
const step = ref(1); // 1 = form, 2 = summary

const selisih = computed(() => {
    return closingAmount.value - Number(props.openDrawer?.opening_amount || 0);
});

const previewClose = () => {
    step.value = 2;
};

const backToForm = () => {
    step.value = 1;
};

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatNumber = (value) => {
    const num = Number(String(value).replace(/\D/g, ''));
    if (!num) return '';
    return num.toLocaleString('id-ID');
};

const onOpeningInput = (event) => {
    const raw = event.target.value.replace(/\D/g, '');
    const num = Number(raw) || 0;
    openingAmount.value = num;
    openingAmountDisplay.value = num > 0 ? formatNumber(num) : '';
    nextTick(() => { event.target.value = openingAmountDisplay.value; });
};

const onOpeningFocus = (event) => {
    event.target.value = openingAmount.value > 0 ? String(openingAmount.value) : '';
    event.target.select();
};

const onOpeningBlur = (event) => {
    openingAmountDisplay.value = openingAmount.value > 0 ? formatNumber(openingAmount.value) : '0';
    event.target.value = openingAmountDisplay.value;
};

const onClosingInput = (event) => {
    const raw = event.target.value.replace(/\D/g, '');
    const num = Number(raw) || 0;
    closingAmount.value = num;
    closingAmountDisplay.value = num > 0 ? formatNumber(num) : '';
    nextTick(() => { event.target.value = closingAmountDisplay.value; });
};

const onClosingFocus = (event) => {
    event.target.value = closingAmount.value > 0 ? String(closingAmount.value) : '';
    event.target.select();
};

const onClosingBlur = (event) => {
    closingAmountDisplay.value = closingAmount.value > 0 ? formatNumber(closingAmount.value) : '';
    event.target.value = closingAmountDisplay.value;
};

const openCashDrawer = () => {
    if (processing.value) return;
    processing.value = true;
    router.post('/cashier/cash-drawer/open', {
        opening_amount: openingAmount.value,
    }, {
        onFinish: () => processing.value = false,
    });
};

const closeCashDrawer = () => {
    if (processing.value) return;
    processing.value = true;
    router.post('/cashier/cash-drawer/close', {
        closing_amount: closingAmount.value,
        notes: notes.value || null,
    }, {
        onFinish: () => processing.value = false,
    });
};

const goToPOS = () => {
    router.get('/cashier/pos');
};
</script>

<template>
    <Head title="Kelola Kas" />
    <div class="min-h-screen bg-background">
        <FlashMessage />

        <!-- Header -->
        <CashierTopbar title="Kas" />

        <main class="max-w-lg mx-auto py-12 px-6">
            <!-- Belum ada sesi terbuka → Form Buka Kas -->
            <div v-if="!openDrawer" class="bg-white rounded-xl shadow-lg p-8">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-primary/10 rounded-full mb-4">
                        <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                            type="text"
                            inputmode="numeric"
                            :value="openingAmountDisplay"
                            @input="onOpeningInput"
                            @focus="onOpeningFocus"
                            @blur="onOpeningBlur"
                            placeholder="0"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg text-lg focus:ring-2 focus:ring-ring focus:border-ring"
                            required
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="processing"
                        class="w-full py-3 bg-primary text-primary-foreground font-semibold rounded-lg hover:bg-primary/90 transition disabled:opacity-50"
                    >
                        {{ processing ? 'Memproses...' : 'Buka Kas & Mulai Jualan' }}
                    </button>
                </form>
            </div>

            <!-- Sesi sudah terbuka → Info + Tutup Kas -->
            <div v-else class="bg-white rounded-xl shadow-lg p-8">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-success/10 rounded-full mb-4">
                        <svg class="w-8 h-8 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    class="w-full py-3 bg-primary text-primary-foreground font-semibold rounded-lg hover:bg-primary/90 transition mb-3"
                >
                    Lanjut ke POS
                </button>

                <!-- Tutup Kas: dua langkah (form → ringkasan) -->
                <div class="border-t border-border pt-6 mt-6">
                    <!-- Langkah 1: Input -->
                    <template v-if="step === 1">
                        <h3 class="text-base font-semibold text-foreground mb-4">Tutup Kas</h3>
                        <form @submit.prevent="previewClose" class="space-y-4">
                            <div>
                                <label for="closing_amount" class="block text-sm font-medium text-foreground mb-1">
                                    Uang fisik di laci (Rp)
                                </label>
                                <input
                                    id="closing_amount"
                                    type="text"
                                    inputmode="numeric"
                                    :value="closingAmountDisplay"
                                    @input="onClosingInput"
                                    @focus="onClosingFocus"
                                    @blur="onClosingBlur"
                                    placeholder="Hitung uang fisik, lalu masukkan"
                                    class="w-full px-4 py-3 border border-border rounded-lg focus:ring-2 focus:ring-ring focus:border-ring bg-card text-foreground placeholder:text-muted-foreground"
                                    required
                                />
                            </div>
                            <div>
                                <label for="notes" class="block text-sm font-medium text-foreground mb-1">
                                    Catatan (opsional)
                                </label>
                                <textarea
                                    id="notes"
                                    v-model="notes"
                                    rows="2"
                                    placeholder="Catatan akhir shift..."
                                    class="w-full px-4 py-3 border border-border rounded-lg focus:ring-2 focus:ring-ring focus:border-ring bg-card text-foreground placeholder:text-muted-foreground resize-none"
                                />
                            </div>
                            <button
                                type="submit"
                                class="w-full py-3 bg-secondary text-secondary-foreground font-semibold rounded-lg hover:bg-secondary/80 transition text-sm"
                            >
                                Lihat Ringkasan
                            </button>
                        </form>
                    </template>

                    <!-- Langkah 2: Ringkasan rekonsiliasi -->
                    <template v-else>
                        <div class="flex items-center gap-2 mb-4">
                            <button @click="backToForm" class="text-muted-foreground hover:text-foreground transition" aria-label="Kembali">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <h3 class="text-base font-semibold text-foreground">Ringkasan Tutup Kas</h3>
                        </div>

                        <div class="bg-muted rounded-lg p-4 space-y-3 mb-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Modal awal</span>
                                <span class="font-medium text-foreground font-mono">{{ formatCurrency(openDrawer.opening_amount) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Uang fisik aktual</span>
                                <span class="font-medium text-foreground font-mono">{{ formatCurrency(Number(closingAmount) || 0) }}</span>
                            </div>
                            <div class="border-t border-border pt-3 flex justify-between text-sm">
                                <span class="text-muted-foreground">Selisih</span>
                                <span
                                    :class="[
                                        'font-semibold font-mono',
                                        selisih > 0  && 'text-success',
                                        selisih < 0  && 'text-destructive',
                                        selisih === 0 && 'text-muted-foreground',
                                    ]"
                                >
                                    <template v-if="selisih === 0">Sesuai</template>
                                    <template v-else-if="selisih > 0">+{{ formatCurrency(selisih) }}</template>
                                    <template v-else>{{ formatCurrency(selisih) }}</template>
                                </span>
                            </div>
                            <div v-if="notes" class="border-t border-border pt-3 text-sm">
                                <span class="text-muted-foreground">Catatan: </span>
                                <span class="text-foreground">{{ notes }}</span>
                            </div>
                        </div>

                        <button
                            @click="closeCashDrawer"
                            :disabled="processing"
                            class="w-full py-3 bg-destructive text-destructive-foreground font-semibold rounded-lg hover:bg-destructive/90 transition disabled:opacity-50 text-sm"
                        >
                            {{ processing ? 'Memproses...' : 'Konfirmasi & Tutup Kas' }}
                        </button>
                    </template>
                </div>
            </div>
        </main>
    </div>
</template>
