<script setup>
import { router, Head, Link } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import CashierTopbar from '@/Components/CashierTopbar.vue';
import Modal from '@/Components/Modal.vue';
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    openDrawer: Object,
    // Rekonsiliasi sesi berjalan dari CashDrawerReconciliation — null saat
    // belum ada sesi terbuka.
    reconciliation: { type: Object, default: null },
    // Umur sesi ([BL-088]) — null saat belum ada sesi terbuka.
    sessionLimit: { type: Object, default: null },
    // Mutasi kas sesi ini ([BL-087]).
    movements: { type: Array, default: () => [] },
    payoutThreshold: { type: Number, default: 0 },
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

/* ── Mutasi kas ([BL-087]) ──────────────────────────────────────────────── */

const movementOpen = ref(false);
const movementType = ref('payout');
const movementAmount = ref(0);
const movementAmountDisplay = ref('');
const movementReason = ref('');
const movementErrors = ref({});
const savingMovement = ref(false);

const openMovement = (type) => {
    movementType.value = type;
    movementAmount.value = 0;
    movementAmountDisplay.value = '';
    movementReason.value = '';
    movementErrors.value = {};
    movementOpen.value = true;
};

const onMovementInput = (event) => {
    const raw = event.target.value.replace(/\D/g, '');
    const num = Number(raw) || 0;
    movementAmount.value = num;
    movementAmountDisplay.value = num > 0 ? formatNumber(num) : '';
    nextTick(() => { event.target.value = movementAmountDisplay.value; });
};

/**
 * Apakah nominal yang sedang diketik akan menunggu persetujuan.
 *
 * Ditampilkan SELAGI mengetik, bukan sesudah menyimpan: aturan yang baru
 * diketahui setelah tombol ditekan terbaca sebagai penolakan, bukan sebagai
 * aturan — dan kasir akan menyimpulkan fiturnya rusak.
 */
const willWaitApproval = computed(() =>
    movementType.value === 'payout' && movementAmount.value > props.payoutThreshold
);

const submitMovement = () => {
    if (savingMovement.value) return;
    savingMovement.value = true;
    movementErrors.value = {};

    router.post('/cashier/cash-drawer/movements', {
        type: movementType.value,
        amount: movementAmount.value,
        reason: movementReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => { movementOpen.value = false; },
        onError: (errors) => { movementErrors.value = errors; },
        onFinish: () => { savingMovement.value = false; },
    });
};

const movementLabel = (movement) => movement.type === 'payout' ? 'Uang keluar' : 'Setoran masuk';

const movementStatusLabel = (movement) => {
    if (movement.status === 'pending') return 'Menunggu persetujuan';
    if (movement.status === 'rejected') return 'Ditolak pemilik';

    return movement.reviewed_by ? 'Disetujui pemilik' : 'Berlaku';
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

                <!-- Uang keluar-masuk laci ([BL-087]) -->
                <div class="border-t border-border pt-4 mt-4 mb-3">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-semibold text-foreground">Uang Keluar / Masuk Laci</h3>
                        <span class="text-xs text-muted-foreground">
                            Di atas {{ formatCurrency(payoutThreshold) }} perlu persetujuan
                        </span>
                    </div>

                    <div class="flex gap-2 mb-3">
                        <button
                            type="button"
                            @click="openMovement('payout')"
                            class="flex-1 py-2 border border-border rounded-lg text-sm font-medium text-foreground hover:bg-muted transition"
                        >
                            Catat Uang Keluar
                        </button>
                        <button
                            type="button"
                            @click="openMovement('deposit')"
                            class="flex-1 py-2 border border-border rounded-lg text-sm font-medium text-foreground hover:bg-muted transition"
                        >
                            Catat Setoran Masuk
                        </button>
                    </div>

                    <!-- Termasuk yang ditolak: kasir harus melihat penolakannya
                         di layar tempat ia mencatat, bukan menemukannya sebagai
                         selisih tak terjelaskan saat tutup kas. -->
                    <ul v-if="movements.length" class="space-y-1.5">
                        <li
                            v-for="movement in movements"
                            :key="movement.id"
                            class="flex items-start justify-between gap-3 text-xs"
                        >
                            <span class="text-muted-foreground">
                                {{ movementLabel(movement) }} — {{ movement.reason }}
                                <span
                                    class="block"
                                    :class="{
                                        'text-warning-foreground': movement.status === 'pending',
                                        'text-destructive': movement.status === 'rejected',
                                        'text-muted-foreground/60': movement.status === 'approved',
                                    }"
                                >{{ movementStatusLabel(movement) }}</span>
                            </span>
                            <span
                                class="font-mono shrink-0"
                                :class="[
                                    movement.status === 'rejected' ? 'line-through text-muted-foreground/50' : '',
                                    movement.type === 'payout' ? 'text-destructive' : 'text-success',
                                ]"
                            >{{ movement.type === 'payout' ? '−' : '+' }}{{ formatCurrency(movement.amount) }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-xs text-muted-foreground">
                        Belum ada uang keluar atau masuk di luar penjualan pada sesi ini.
                    </p>
                </div>

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

        <!-- Pencatatan mutasi kas ([BL-087]) -->
        <Modal
            :show="movementOpen"
            :title="movementType === 'payout' ? 'Catat Uang Keluar' : 'Catat Setoran Masuk'"
            :description="movementType === 'payout'
                ? 'Uang yang keluar dari laci dan bukan kembalian — setoran ke pemilik, beli galon, tukar uang kecil.'
                : 'Uang yang masuk ke laci di luar penjualan — misalnya tambahan uang kecil.'"
            @close="movementOpen = false"
        >
                <form @submit.prevent="submitMovement" class="space-y-4">
                    <div>
                        <label for="movement_amount" class="block text-sm font-medium text-foreground mb-1">Nominal (Rp)</label>
                        <input
                            id="movement_amount"
                            type="text"
                            inputmode="numeric"
                            :value="movementAmountDisplay"
                            @input="onMovementInput"
                            placeholder="0"
                            class="w-full px-4 py-3 border border-border rounded-lg focus:ring-2 focus:ring-ring focus:border-ring bg-card text-foreground"
                            required
                        />
                        <p v-if="movementErrors.amount" class="mt-1 text-xs text-destructive">{{ movementErrors.amount }}</p>
                    </div>

                    <div>
                        <label for="movement_reason" class="block text-sm font-medium text-foreground mb-1">Alasan</label>
                        <input
                            id="movement_reason"
                            v-model="movementReason"
                            type="text"
                            maxlength="200"
                            placeholder="Contoh: beli galon air"
                            class="w-full px-4 py-3 border border-border rounded-lg focus:ring-2 focus:ring-ring focus:border-ring bg-card text-foreground placeholder:text-muted-foreground"
                            required
                        />
                        <p v-if="movementErrors.reason" class="mt-1 text-xs text-destructive">{{ movementErrors.reason }}</p>
                    </div>

                    <!-- Diberitahukan SELAGI mengetik. Aturan yang baru diketahui
                         sesudah tombol simpan ditekan terbaca sebagai penolakan. -->
                    <p
                        v-if="willWaitApproval"
                        class="rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-xs leading-relaxed text-foreground"
                    >
                        Nominal ini di atas {{ formatCurrency(payoutThreshold) }}, jadi akan <strong>menunggu persetujuan pemilik</strong>.
                        Catatannya tetap tersimpan dan terlihat, tapi uang yang seharusnya ada di laci belum berubah sampai disetujui.
                    </p>

                    <div class="flex gap-3 pt-1">
                        <button
                            type="button"
                            @click="movementOpen = false"
                            class="flex-1 py-2.5 border border-border rounded-lg text-sm font-medium text-foreground hover:bg-muted transition"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="savingMovement"
                            class="flex-1 py-2.5 bg-primary text-primary-foreground rounded-lg text-sm font-semibold hover:bg-primary/90 transition disabled:opacity-50"
                        >
                            {{ savingMovement ? 'Menyimpan...' : 'Simpan Catatan' }}
                        </button>
                    </div>
                </form>
        </Modal>
    </div>
</template>
