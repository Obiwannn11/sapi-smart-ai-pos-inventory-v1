<script setup>
import { router, Head, Link } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import CashierTopbar from '@/Components/CashierTopbar.vue';
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    openDrawer: Object,
    // Rekonsiliasi sesi berjalan dari CashDrawerReconciliation — null saat
    // belum ada sesi terbuka.
    reconciliation: { type: Object, default: null },
    // Umur sesi ([BL-088]) — null saat belum ada sesi terbuka.
    sessionLimit: { type: Object, default: null },
});

const openingAmount = ref(0);
const openingAmountDisplay = ref('0');
const processing = ref(false);

// Uang yang seharusnya ada di laci = modal + tunai masuk − kembalian keluar.
// Sebelumnya halaman ini membandingkan uang fisik dengan modal awal saja,
// sehingga seluruh penjualan tunai shift itu terbaca sebagai "kelebihan".
const expectedAmount = computed(() => Number(props.reconciliation?.expected_amount ?? 0));

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
/**
 * Peringatan sebelum sesi ditutup paksa ([BL-088]).
 *
 * Sengaja dihitung dari jam DINDING yang berdetak, bukan sekali saat halaman
 * dirender: tab kasir dibiarkan terbuka semalaman, dan justru tab itulah yang
 * paling butuh peringatan ini. Satu menit sekali sudah cukup halus untuk
 * hitungan berjam-jam, dan tidak menyalakan layar tiap detik.
 */
const now = ref(new Date());
let clock = null;
onMounted(() => { clock = setInterval(() => { now.value = new Date(); }, 60_000); });
onUnmounted(() => { if (clock) clearInterval(clock); });

const sessionWarning = computed(() => {
    if (!props.sessionLimit) return null;

    const expiresAt = new Date(props.sessionLimit.expires_at);
    const warnFrom = new Date(props.sessionLimit.warn_from);

    if (now.value < warnFrom) return null;

    // Sudah lewat batas tapi sapuan per jam belum menyentuhnya. Keadaan ini
    // nyata dan berumur paling lama satu jam — mendiamkannya berarti kasir
    // menghitung uang untuk sesi yang akan ditutup sistem beberapa menit lagi.
    if (now.value >= expiresAt) {
        return { overdue: true, minutes: 0 };
    }

    return { overdue: false, minutes: Math.round((expiresAt - now.value) / 60_000) };
});

const formatCountdown = (minutes) => {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    if (hours > 0) return `${hours} jam ${rest} menit`;

    return `${rest} menit`;
};

const showExpected = ref(false);

/**
 * Membukanya sah, dan ia meninggalkan jejak ([BL-090]).
 *
 * Pemilik memilih mencatat alih-alih mencegah, jadi tombolnya tetap hidup dan
 * yang berubah hanya: pemakaiannya terlihat. Kalimat di layar mengatakannya
 * sebelum tombolnya ditekan — jejak yang baru diketahui belakangan terasa
 * seperti jebakan, dan kasir yang merasa dijebak berhenti mempercayai
 * seluruh layar ini.
 *
 * Dicatat sekali per pemuatan halaman, bukan sekali per klik: menyalakan dan
 * mematikan bergantian akan menumpuk baris yang menjawab hal yang sama, dan
 * yang ditanyakan pemilik adalah "apakah ia sudah melihat angkanya", bukan
 * "berapa kali ia menekan tombolnya".
 */
const revealLogged = ref(false);

const toggleExpected = () => {
    showExpected.value = !showExpected.value;

    if (showExpected.value && !revealLogged.value) {
        revealLogged.value = true;
        // `preserveState` supaya angkanya tidak berkedip tertutup lagi begitu
        // jawabannya sampai, dan `only: []` supaya tidak ada prop yang ditarik
        // ulang untuk sebuah permintaan yang tidak mengubah apa pun di layar.
        router.post('/cashier/cash-drawer/reveal', {}, {
            preserveState: true,
            preserveScroll: true,
            only: [],
        });
    }
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

const openCashDrawer = () => {
    if (processing.value) return;
    processing.value = true;
    router.post('/cashier/cash-drawer/open', {
        opening_amount: openingAmount.value,
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

                <!-- Umur sesi ([BL-088]). Muncul empat jam sebelum batas, bukan
                     sesudahnya: sesi yang terlanjur ditutup sistem tidak bisa
                     lagi dihitung uangnya. -->
                <div
                    v-if="sessionWarning"
                    class="mb-6 rounded-lg border px-4 py-3 text-sm leading-relaxed"
                    :class="sessionWarning.overdue
                        ? 'border-destructive/40 bg-destructive/10 text-foreground'
                        : 'border-warning/40 bg-warning/10 text-foreground'"
                    role="status"
                >
                    <template v-if="sessionWarning.overdue">
                        <span class="font-semibold">Sesi ini sudah lewat {{ sessionLimit.hours }} jam.</span>
                        Sistem akan menutupnya sendiri dalam waktu dekat, dan sesi yang ditutup sistem tercatat
                        <span class="font-medium">tanpa hitungan uang fisik</span>. Tutup kas sekarang selagi masih bisa dihitung.
                    </template>
                    <template v-else>
                        <span class="font-semibold">Sesi kas akan ditutup otomatis dalam {{ formatCountdown(sessionWarning.minutes) }}.</span>
                        Sesi kas hanya berlaku {{ sessionLimit.hours }} jam. Kalau ditutup sistem, uang fisiknya tidak pernah tercatat
                        dan selisihnya tidak bisa dipertanggungjawabkan siapa pun.
                    </template>
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
                            <span class="block mt-1">Boleh dibuka kalau memang perlu — pemilik akan melihat catatan bahwa angkanya dibuka.</span>
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

                <!-- Tutup kas punya halamannya sendiri ([BL-086] butir 2).
                     Sengaja tautan sekunder, bukan tombol sebesar "Lanjut ke
                     POS": memeriksa sesi adalah hal yang dilakukan berkali-kali
                     sehari, menutupnya sekali. -->
                <Link
                    href="/cashier/cash-drawer/close"
                    class="block w-full py-3 text-center border border-border text-foreground font-semibold rounded-lg hover:bg-muted transition text-sm"
                >
                    Tutup Kas
                </Link>

            </div>
        </main>
    </div>
</template>
