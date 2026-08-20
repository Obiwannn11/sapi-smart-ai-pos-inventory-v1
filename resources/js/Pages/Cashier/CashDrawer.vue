<script setup>
import { router, Head } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import CashierTopbar from '@/Components/CashierTopbar.vue';
import { ref, computed, nextTick } from 'vue';

const props = defineProps({
    openDrawer: Object,
    // Rekonsiliasi sesi berjalan dari CashDrawerReconciliation — null saat
    // belum ada sesi terbuka.
    reconciliation: { type: Object, default: null },
});

const openingAmount = ref(0);
const openingAmountDisplay = ref('0');
const closingAmount = ref(0);
const closingAmountDisplay = ref('');
const notes = ref('');
const processing = ref(false);
const refreshing = ref(false);
const step = ref(1); // 1 = form, 2 = summary

// Uang yang seharusnya ada di laci = modal + tunai masuk − kembalian keluar.
// Sebelumnya halaman ini membandingkan uang fisik dengan modal awal saja,
// sehingga seluruh penjualan tunai shift itu terbaca sebagai "kelebihan".
const expectedAmount = computed(() => Number(props.reconciliation?.expected_amount ?? 0));

const selisih = computed(() => closingAmount.value - expectedAmount.value);

/**
 * Penghitungan buta selama sesi berjalan ([BL-086]).
 *
 * Sebelum ini panel sesi aktif memajang "Seharusnya di laci" sepanjang shift,
 * dan kasir tinggal mengetik ulang angka itu di kolom uang fisik: selisihnya
 * selalu nol, dan laci yang benar-benar kurang tidak pernah ketahuan. Angka
 * yang menjadi JAWABAN tidak boleh terbaca sebelum hitungannya disetorkan.
 *
 * Yang disembunyikan bukan cuma totalnya, melainkan **ketiga angka yang
 * membentuknya** — modal + tunai masuk − kembalian keluar. Menyembunyikan
 * total sambil memajang penjumlahnya bukan penghitungan buta, itu soal
 * hitungan.
 *
 * **Ini penyembunyian di sisi klien, dan itu diakui:** `reconciliation` tetap
 * ikut props Inertia dan terbaca dari devtools. Untuk peragaan dan untuk
 * menghilangkan godaan sehari-hari, ini cukup; penegakan sungguhan menuntut
 * `index()` berhenti mengirimkannya sampai hitungan fisik disetorkan, dan itu
 * tercatat sebagai butir yang belum dikerjakan di `[BL-086]`.
 */
const showExpected = ref(false);
const toggleExpected = () => { showExpected.value = !showExpected.value; };

/**
 * Ambil ulang angka rekonsiliasi sebelum menampilkan ringkasan.
 *
 * Penjualan bisa terjadi setelah halaman ini dibuka, dan `close()` menghitung
 * ulang di server. Tanpa muat ulang, ringkasan bisa menjanjikan selisih yang
 * berbeda dari yang akhirnya tercatat — cara tercepat membuat kasir berhenti
 * mempercayai kedua angkanya.
 */
const previewClose = () => {
    if (refreshing.value) return;
    refreshing.value = true;

    router.reload({
        only: ['reconciliation'],
        onSuccess: () => { step.value = 2; },
        onFinish: () => { refreshing.value = false; },
    });
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
                    <template v-if="reconciliation && showExpected">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Penjualan tunai</span>
                            <span class="font-medium text-gray-800 font-mono">+{{ formatCurrency(reconciliation.cash_in) }}</span>
                        </div>
                        <div v-if="Number(reconciliation.change_out) > 0" class="flex justify-between text-sm">
                            <span class="text-gray-500">Kembalian keluar</span>
                            <span class="font-medium text-gray-800 font-mono">−{{ formatCurrency(reconciliation.change_out) }}</span>
                        </div>
                    </template>

                    <!-- Tersembunyi secara bawaan ([BL-086]). Yang dipajang saat
                         tertutup adalah ALASANNYA, bukan sekadar titik-titik:
                         kasir yang tidak tahu kenapa angkanya hilang akan
                         mengira halamannya rusak. -->
                    <div v-if="reconciliation" class="border-t border-border pt-2">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-sm text-gray-500">
                                Seharusnya di laci
                                <span
                                    :class="showExpected ? 'font-semibold text-gray-800 font-mono' : 'font-mono text-gray-400'"
                                >{{ showExpected ? formatCurrency(expectedAmount) : '••••••' }}</span>
                            </span>
                            <button
                                type="button"
                                @click="toggleExpected"
                                :aria-expanded="showExpected"
                                class="text-xs font-medium text-primary hover:underline shrink-0"
                            >
                                {{ showExpected ? 'Sembunyikan' : 'Tampilkan uang seharusnya' }}
                            </button>
                        </div>
                        <p v-if="!showExpected" class="text-xs text-muted-foreground mt-1 leading-relaxed">
                            Disembunyikan supaya hitungan uang fisik Anda jujur. Hitung dulu isi laci, masukkan angkanya, dan ringkasan tutup kas akan membandingkannya sendiri.
                        </p>
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
                                :disabled="refreshing"
                                class="w-full py-3 bg-secondary text-secondary-foreground font-semibold rounded-lg hover:bg-secondary/80 transition text-sm disabled:opacity-50"
                            >
                                {{ refreshing ? 'Menghitung ulang...' : 'Lihat Ringkasan' }}
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
                                <span class="text-muted-foreground">
                                    Penjualan tunai
                                    <span v-if="reconciliation" class="text-muted-foreground/60">({{ reconciliation.transaction_count }} transaksi)</span>
                                </span>
                                <span class="font-medium text-success font-mono">+{{ formatCurrency(reconciliation?.cash_in ?? 0) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-muted-foreground">Kembalian keluar</span>
                                <span class="font-medium text-destructive font-mono">−{{ formatCurrency(reconciliation?.change_out ?? 0) }}</span>
                            </div>
                            <div class="border-t border-border pt-3 flex justify-between text-sm">
                                <span class="text-muted-foreground font-medium">Seharusnya di laci</span>
                                <span class="font-semibold text-foreground font-mono">{{ formatCurrency(expectedAmount) }}</span>
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
                            <!-- Kas negatif: tagihan terbuka yang lewat 24 jam
                                 dan berhenti bisa ditagih ([BL-031]). Di bawah
                                 garis Selisih dan TIDAK ikut menghitungnya —
                                 uang ini tidak pernah masuk laci, jadi
                                 memasukkannya akan menuduh kasir kurang
                                 sebesar tagihan yang bukan ia pegang. -->
                            <div
                                v-if="Number(reconciliation?.unsettled_cash) > 0"
                                class="border-t border-border pt-3 flex justify-between text-xs"
                            >
                                <span class="text-muted-foreground">
                                    Kas negatif
                                    <span class="text-muted-foreground/60">
                                        — {{ reconciliation.unsettled_count }} tagihan lewat 24 jam, hanya pemilik yang bisa membereskan
                                    </span>
                                </span>
                                <span class="text-destructive font-mono">−{{ formatCurrency(reconciliation.unsettled_cash) }}</span>
                            </div>
                            <!-- Non-tunai ditampilkan terpisah dan ditandai tegas:
                                 kasir tidak boleh mencari uang QRIS di dalam laci. -->
                            <div
                                v-if="Number(reconciliation?.non_cash_in) > 0"
                                class="border-t border-border pt-3 flex justify-between text-xs"
                            >
                                <span class="text-muted-foreground">
                                    Non-tunai (QRIS/transfer)
                                    <span class="text-muted-foreground/60">— tidak masuk laci</span>
                                </span>
                                <span class="text-muted-foreground font-mono">{{ formatCurrency(reconciliation.non_cash_in) }}</span>
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
