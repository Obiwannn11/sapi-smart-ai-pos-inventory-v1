<script setup>
import { router, Head, Link } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import CashierTopbar from '@/Components/CashierTopbar.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { ref, computed, nextTick } from 'vue';

/**
 * Alur tutup kas, terpisah dari halaman sesi ([BL-086] butir 2).
 *
 * Halaman ini hanya ada saat kasir memang berniat menutup kasnya. Sebelumnya
 * ia tinggal di bagian bawah halaman sesi, sehingga kasir melewatinya setiap
 * kali sekadar memeriksa laci — dan menutup kas terlihat seperti hal yang bisa
 * dilakukan sambil lalu.
 */
const props = defineProps({
    openDrawer: Object,
    reconciliation: { type: Object, default: null },
});

const closingAmount = ref(0);
const closingAmountDisplay = ref('');
const notes = ref('');
const processing = ref(false);
const refreshing = ref(false);
const step = ref(1); // 1 = hitungan fisik, 2 = ringkasan
const confirming = ref(false);

/**
 * Penghitungan buta tidak boleh bisa dibatalkan dengan tombol "Kembali"
 * ([BL-086]).
 *
 * Begitu ringkasan pernah dibuka, kasir sudah membaca selisihnya. Kalau kolom
 * uang fisik tetap bisa disunting sesudah itu, seluruh penjagaan di halaman
 * sesi runtuh dalam dua klik: lihat selisih, kembali, samakan angkanya.
 *
 * Yang dipasang di sini bukan larangan — salah ketik itu nyata, dan kasir yang
 * terkunci pada angka salah akan menutup kas dengan selisih karangan. Yang
 * dipasang adalah **friksi yang terlihat**: kolomnya terkunci, dan mengubahnya
 * menuntut menekan "Hitung ulang" yang MENGOSONGKAN kolomnya. Revisi jadi
 * tindakan yang disengaja dan dimulai dari nol, bukan koreksi diam-diam
 * terhadap angka yang sudah terbaca jawabannya.
 */
const hasSeenSummary = ref(false);
const amountLocked = computed(() => hasSeenSummary.value);

const expectedAmount = computed(() => Number(props.reconciliation?.expected_amount ?? 0));
const selisih = computed(() => closingAmount.value - expectedAmount.value);

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
        onSuccess: () => {
            step.value = 2;
            hasSeenSummary.value = true;
        },
        onFinish: () => { refreshing.value = false; },
    });
};

const backToForm = () => {
    step.value = 1;
};

/** Buka kunci kolom hitungan dengan mengosongkannya. Lihat `hasSeenSummary`. */
const recount = () => {
    closingAmount.value = 0;
    closingAmountDisplay.value = '';
    hasSeenSummary.value = false;
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

const closeCashDrawer = () => {
    if (processing.value) return;
    confirming.value = false;
    processing.value = true;
    router.post('/cashier/cash-drawer/close', {
        closing_amount: closingAmount.value,
        notes: notes.value || null,
    }, {
        onFinish: () => processing.value = false,
    });
};
</script>

<template>
    <Head title="Tutup Kas" />
    <div class="min-h-screen bg-background">
        <FlashMessage />

        <CashierTopbar title="Tutup Kas" />

        <main class="max-w-lg mx-auto py-12 px-6">
            <div class="bg-white rounded-xl shadow-lg p-8">
                <div class="flex items-center gap-2 mb-6">
                    <Link
                        href="/cashier/cash-drawer"
                        class="text-muted-foreground hover:text-foreground transition"
                        aria-label="Kembali ke halaman kas"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h2 class="text-xl font-semibold text-gray-800">
                        {{ step === 1 ? 'Tutup Kas' : 'Ringkasan Tutup Kas' }}
                    </h2>
                </div>

                <p class="text-sm text-muted-foreground mb-6">
                    Sesi dibuka {{ new Date(openDrawer.opened_at).toLocaleString('id-ID') }} dengan modal
                    {{ formatCurrency(openDrawer.opening_amount) }}.
                </p>

                <!-- Langkah 1: hitungan fisik -->
                <template v-if="step === 1">
                    <form @submit.prevent="previewClose" class="space-y-4">
                        <div>
                            <label for="closing_amount" class="block text-sm font-medium text-foreground mb-1">
                                Uang fisik di laci (Rp)
                            </label>
                            <input
                                v-if="!amountLocked"
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
                            <!-- Terkunci karena ringkasan sudah pernah dibuka.
                                 Lihat `hasSeenSummary` di script. -->
                            <div v-else class="space-y-2">
                                <div class="w-full px-4 py-3 border border-border rounded-lg bg-muted text-foreground font-mono">
                                    {{ formatCurrency(closingAmount) }}
                                </div>
                                <p class="text-xs text-muted-foreground leading-relaxed">
                                    Angka ini terkunci karena Anda sudah melihat ringkasannya. Kalau hitungannya salah, hitung ulang isi laci dari awal — kolomnya akan dikosongkan.
                                </p>
                                <button
                                    type="button"
                                    @click="recount"
                                    class="text-xs font-medium text-primary hover:underline"
                                >
                                    Hitung ulang
                                </button>
                            </div>
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

                <!-- Langkah 2: ringkasan rekonsiliasi -->
                <template v-else>
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
                                <template v-else>−{{ formatCurrency(Math.abs(selisih)) }}</template>
                            </span>
                        </div>
                        <!-- Kas negatif: tagihan terbuka yang lewat 24 jam dan
                             berhenti bisa ditagih ([BL-031]). Di bawah garis
                             Selisih dan TIDAK ikut menghitungnya — uang ini
                             tidak pernah masuk laci, jadi memasukkannya akan
                             menuduh kasir kurang sebesar tagihan yang bukan ia
                             pegang. -->
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

                    <div class="flex gap-3">
                        <button
                            type="button"
                            @click="backToForm"
                            class="flex-1 py-3 border border-border text-foreground font-semibold rounded-lg hover:bg-muted transition text-sm"
                        >
                            Kembali
                        </button>
                        <button
                            type="button"
                            @click="confirming = true"
                            :disabled="processing"
                            class="flex-1 py-3 bg-destructive text-destructive-foreground font-semibold rounded-lg hover:bg-destructive/90 transition disabled:opacity-50 text-sm"
                        >
                            {{ processing ? 'Memproses...' : 'Konfirmasi & Tutup Kas' }}
                        </button>
                    </div>
                </template>
            </div>
        </main>

        <!-- Menutup kas tidak bisa dibatalkan: sesinya berhenti menerima
             penjualan dan selisihnya tercatat apa adanya. -->
        <ConfirmDialog
            :show="confirming"
            title="Tutup sesi kas?"
            :message="`Sesi ini akan ditutup dengan uang fisik ${formatCurrency(closingAmount)}. Selisihnya tercatat dan tidak bisa diubah lagi setelah ini.`"
            confirm-text="Ya, Tutup Kas"
            cancel-text="Batal"
            variant="danger"
            @confirm="closeCashDrawer"
            @cancel="confirming = false"
        />
    </div>
</template>
