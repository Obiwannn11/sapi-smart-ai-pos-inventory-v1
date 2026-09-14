<script setup>
/**
 * Modal pembayaran, termasuk split bill.
 *
 * Aturan yang menentukan benar-tidaknya angka yang tersimpan, dan ketiganya
 * pernah salah (lihat [BL-021]):
 *
 *   NOMINAL NON-TUNAI ADALAH TURUNAN, BUKAN NILAI YANG DISIMPAN. Versi lama
 *   mengisinya sekali saat metode dipilih lalu membekukannya, sehingga
 *   mengoreksi baris tunai setelahnya meninggalkan nominal QRIS yang basi —
 *   tersimpan apa adanya ke transaction_payments dan merusak rekap per metode.
 *
 *   SATU BARIS PENYEIMBANG SAJA. Kalau dua baris sama-sama "otomatis", tidak
 *   ada jawaban tunggal untuk pembagiannya. Yang menyeimbangkan adalah baris
 *   non-tunai TERAKHIR yang belum disentuh kasir; begitu kasir mengetik di
 *   sana, baris itu jadi manual dan penyeimbangnya berpindah.
 *
 *   NOMINAL TUNAI TIDAK PERNAH DIISI SENDIRI OLEH MODAL. Yang otomatis hanya
 *   baris non-tunai. Uang tunai harus dihitung dulu di tangan kasir; angka
 *   "pas" yang muncul begitu metode tunai dipilih membuat kasir menekan Bayar
 *   atas nominal yang belum pernah ia terima — dan laci pun selisih. Tombol
 *   "Uang Pas" tetap ada untuk yang memang membayar pas, ditekan sesudah
 *   uangnya dicek.
 *
 *   KEMBALIAN HANYA DARI PORSI TUNAI. QRIS tidak mengenal kembalian, jadi
 *   kelebihan yang berasal dari baris non-tunai tidak boleh ditawarkan sebagai
 *   uang keluar dari laci.
 */
import { ref, computed, watch, nextTick } from 'vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { useImageCompressor } from '@/composables/useImageCompressor';

const props = defineProps({
    show: { type: Boolean, default: false },
    totalAmount: { type: Number, default: 0 },
    paymentMethods: { type: Array, default: () => [] },
    /**
     * Saklar foto bukti bayar milik toko ([BL-075]). Mati bawaannya.
     *
     * Tidak perlu dipasangkan dengan pemeriksaan online: saat perangkat offline
     * POS sudah menyaring metode bayar jadi tunai saja, jadi baris non-tunai —
     * satu-satunya yang bisa meminta foto — tidak pernah ada di sana.
     */
    proofRequired: { type: Boolean, default: false },
    /**
     * Metode yang boleh lewat tanpa foto meski saklarnya menyala ([BL-075]).
     *
     * Kosong di kasir — penjualan baru selalu wajib. Diisi hanya oleh modal
     * EDIT, dengan metode yang SUDAH ada pada transaksinya: sebuah QRIS yang
     * terjual minggu lalu tidak bisa difoto ulang hari ini, dan mewajibkannya
     * hanya akan mengunci orang yang datang untuk mengoreksi qty. Kamera tetap
     * ditawarkan di sana, sebagai pelengkap, bukan sebagai gerbang.
     */
    proofExemptMethodIds: { type: Array, default: () => [] },
    /**
     * Baris yang sudah ada saat modal dibuka ([BL-075] sisa).
     *
     * KOSONG di kasir, dan itu yang membuat penambahan ini aman: penjualan
     * baru tetap membuka modal yang bersih seperti sebelumnya. Yang mengisinya
     * hanya modal EDIT, dengan pembayaran yang benar-benar tersimpan — supaya
     * kasir melihat nominal, kode referensi, dan FOTO yang sudah melekat, bukan
     * formulir kosong yang memaksanya mengarang ulang apa yang sudah terjadi.
     *
     * Bentuk tiap baris: `{ payment_method_id, amount, reference_code,
     * proof_payment_id }`. `proof_payment_id` adalah id baris
     * `transaction_payments` yang memegang fotonya — bukan path berkasnya,
     * yang tidak pernah perlu diketahui peramban.
     */
    initialPayments: { type: Array, default: () => [] },
});

const paymentMethodOptions = computed(() =>
    props.paymentMethods.map(method => ({ value: method.id, label: method.name }))
);

const emit = defineEmits(['close', 'confirm']);

/** `touched` = kasir sudah menentukan nominal baris ini sendiri. */
const blankRow = () => ({
    payment_method_id: null,
    amount: 0,
    touched: false,
    reference_code: '',
    // Foto bukti bayar ([BL-075]). `proof_token` adalah UUID yang dikembalikan
    // server setelah fotonya terunggah; yang menyeberang saat checkout hanya
    // token itu, bukan berkasnya — lihat PaymentProofService.
    proof_token: null,
    proof_preview: null,
    proof_uploading: false,
    proof_error: null,
    // Foto yang SUDAH melekat pada pembayaran tersimpan — hanya diisi modal
    // edit. Ia tidak ikut dikirim ke server: buktinya diselamatkan di sana
    // lewat `payment_method_id`, dan yang dibawa client hanya foto baru.
    proof_payment_id: null,
});

/** Satu baris dari pembayaran yang sudah tersimpan (modal edit). */
const rowFromInitial = (payment) => ({
    ...blankRow(),
    payment_method_id: payment.payment_method_id ?? null,
    amount: Number(payment.amount) || 0,
    // Nominal yang sudah terjadi dihormati apa adanya. Membiarkan salah satu
    // baris jadi penyeimbang berarti diam-diam mengubah nominal QRIS yang
    // sudah tercatat; bila total belanjanya berubah karena itemnya diedit,
    // kekurangannya DITAMPILKAN dan kasir yang memutuskan ke mana ia jatuh.
    touched: true,
    reference_code: payment.reference_code || '',
    // Foto baru yang sudah terunggah pada pembukaan sebelumnya ikut dibawa
    // kembali. Tanpa ini, kasir yang membuka ulang modal untuk mengoreksi
    // nominal akan kehilangan foto yang baru saja ia ambil — tanpa galat,
    // tanpa jejak, dan baru ketahuan saat penyimpanannya ditolak.
    proof_token: payment.proof_token ?? null,
    proof_preview: payment.proof_preview ?? null,
    proof_payment_id: payment.proof_payment_id ?? null,
});

const rows = ref([blankRow()]);

const formatCurrency = (value) => 'Rp ' + Number(value).toLocaleString('id-ID');

const formatNumber = (value) => {
    const num = Number(String(value).replace(/\D/g, ''));
    if (!num) return '';
    return num.toLocaleString('id-ID');
};

watch(() => props.show, (val) => {
    if (! val) return;

    rows.value = props.initialPayments.length > 0
        ? props.initialPayments.map(rowFromInitial)
        : [blankRow()];
});

const getMethodType = (methodId) => {
    return props.paymentMethods.find(m => m.id === methodId)?.type || '';
};

const isCash = (methodId) => getMethodType(methodId) === 'cash';
const isNonCash = (methodId) => Boolean(methodId) && !isCash(methodId);

const needsReferenceCode = (methodId) => {
    const type = getMethodType(methodId);
    return type === 'qris_static' || type === 'bank_transfer';
};

// --- Nominal ---

/**
 * Baris yang menyerap sisa tagihan: non-tunai terakhir yang belum disentuh.
 * -1 bila tidak ada — semua baris manual, dan kasir yang menanggung jumlahnya.
 */
const balancerIndex = computed(() => {
    for (let i = rows.value.length - 1; i >= 0; i--) {
        const row = rows.value[i];
        if (isNonCash(row.payment_method_id) && !row.touched) return i;
    }
    return -1;
});

/** Jumlah seluruh baris yang nominalnya ditentukan sendiri. */
const pinnedTotal = computed(() =>
    rows.value.reduce(
        (sum, row, idx) => (idx === balancerIndex.value ? sum : sum + (Number(row.amount) || 0)),
        0,
    )
);

const balancerAmount = computed(() => Math.max(0, props.totalAmount - pinnedTotal.value));

const amountAt = (idx) =>
    idx === balancerIndex.value ? balancerAmount.value : Number(rows.value[idx].amount) || 0;

const amounts = computed(() => rows.value.map((_, idx) => amountAt(idx)));

const totalPaid = computed(() => amounts.value.reduce((sum, amount) => sum + amount, 0));

const cashPaid = computed(() =>
    amounts.value.reduce(
        (sum, amount, idx) => (isCash(rows.value[idx].payment_method_id) ? sum + amount : sum),
        0,
    )
);

const nonCashPaid = computed(() => totalPaid.value - cashPaid.value);

/** Bagian tagihan yang masih harus ditutup tunai. */
const cashDue = computed(() => Math.max(0, props.totalAmount - nonCashPaid.value));

const change = computed(() => Math.max(0, cashPaid.value - cashDue.value));

const shortfall = computed(() => Math.max(0, props.totalAmount - totalPaid.value));

/** Menagih non-tunai melebihi total berarti menjanjikan kembalian yang tak bisa diberikan. */
const nonCashOverpaid = computed(() => nonCashPaid.value > props.totalAmount);

const emptyRowIndex = computed(() =>
    rows.value.findIndex((row, idx) => !row.payment_method_id || amountAt(idx) <= 0)
);

// --- Foto bukti bayar ([BL-075]) ---

const { compress } = useImageCompressor();

/** Kamera ditawarkan — belum tentu diwajibkan; lihat `proofRequiredFor`. */
const needsProof = (row) => props.proofRequired && isNonCash(row.payment_method_id);

const proofRequiredFor = (row) =>
    needsProof(row) && !props.proofExemptMethodIds.includes(row.payment_method_id);

/**
 * Foto lama disajikan lewat rute media ber-auth, bukan dari path berkasnya —
 * pemeriksaan tenant ada di MediaController, dan URL-nya disusun sama persis
 * seperti foto struk mutasi kas ([BL-093]).
 */
const existingProofUrl = (row, size) =>
    row.proof_payment_id ? `/media/bukti-bayar/${row.proof_payment_id}/${size}` : null;

const missingProofIndex = computed(() =>
    rows.value.findIndex((row) => proofRequiredFor(row) && !row.proof_token)
);

const uploadingProof = computed(() => rows.value.some((row) => row.proof_uploading));

/**
 * Unggah fotonya SEKARANG, bukan saat tombol Bayar ditekan.
 *
 * Kegagalan unggah yang baru ketahuan di detik terakhir adalah kegagalan yang
 * terjadi di depan pelanggan yang sudah menunggu. Diunggah lebih dulu berarti
 * kasir melihat centangnya sebelum ia menekan apa pun — dan bila jaringannya
 * bermasalah, ia tahu saat masih punya waktu memotret ulang.
 *
 * Dikompresi lebih dulu di perangkat ([BL-077]): yang menyeberang jaringan
 * warung adalah beberapa ratus kilobyte, bukan foto 12 MP apa adanya.
 */
const onProofPicked = async (idx, event) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) return;

    const row = rows.value[idx];
    row.proof_error = null;
    row.proof_uploading = true;

    try {
        const prepared = await compress(file);

        const body = new FormData();
        body.append('proof', prepared);

        const response = await fetch('/cashier/bukti-bayar', {
            method: 'POST',
            body,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('upload gagal');
        }

        const { token } = await response.json();

        row.proof_token = token;
        row.proof_preview = URL.createObjectURL(prepared);
    } catch {
        row.proof_error = 'Foto gagal diunggah. Coba potret ulang.';
    } finally {
        row.proof_uploading = false;
    }
};

/**
 * Buang foto BARU saja. `proof_payment_id` sengaja tidak ikut dihapus: bukti
 * lama masih melekat di server, jadi membatalkan potret ulang harus kembali
 * memperlihatkannya — bukan meninggalkan kotak kosong yang mengesankan
 * pembayaran itu tak pernah berbukti.
 */
const clearProof = (idx) => {
    const row = rows.value[idx];

    if (row.proof_preview) URL.revokeObjectURL(row.proof_preview);

    row.proof_token = null;
    row.proof_preview = null;
    row.proof_error = null;
};

const blockingReason = computed(() => {
    if (emptyRowIndex.value !== -1) {
        return `Lengkapi metode dan nominal pada Pembayaran ${emptyRowIndex.value + 1}.`;
    }
    if (nonCashOverpaid.value) {
        return 'Nominal non-tunai melebihi total. Kembalian hanya bisa dari tunai.';
    }
    if (shortfall.value > 0) {
        return `Masih kurang ${formatCurrency(shortfall.value)}.`;
    }
    if (uploadingProof.value) {
        return 'Foto bukti bayar masih diunggah.';
    }
    if (missingProofIndex.value !== -1) {
        return `Foto bukti bayar wajib pada Pembayaran ${missingProofIndex.value + 1}.`;
    }
    return '';
});

const isValid = computed(() => rows.value.length > 0 && blockingReason.value === '');

// --- Interaksi ---

const setAmount = (idx, value) => {
    rows.value[idx].amount = Math.max(0, Number(value) || 0);
    rows.value[idx].touched = true;
};

const displayFor = (idx) => {
    const amount = amountAt(idx);
    if (amount > 0) return formatNumber(amount);

    return idx === balancerIndex.value ? '0' : '';
};

const onAmountInput = (idx, event) => {
    setAmount(idx, event.target.value.replace(/\D/g, ''));
    nextTick(() => { event.target.value = displayFor(idx); });
};

const onAmountFocus = (idx, event) => {
    const amount = amountAt(idx);
    event.target.value = amount > 0 ? String(amount) : '';
    event.target.select();
};

const onAmountBlur = (idx, event) => {
    event.target.value = displayFor(idx);
};

/** Isi baris ini dengan sisa yang belum tertutup baris lain. */
const fillRemainder = (idx) => {
    const others = amounts.value.reduce(
        (sum, amount, i) => (i === idx ? sum : sum + amount),
        0,
    );

    setAmount(idx, Math.max(0, props.totalAmount - others));
};

const quickCash = (idx, denomination) => {
    setAmount(idx, amountAt(idx) + denomination);
};

/**
 * Kosongkan nominal baris ini. `touched` ikut dilepas, bukan hanya angkanya
 * dinolkan: baris non-tunai yang dikosongkan kembali menjadi penyeimbang —
 * itulah keadaan awalnya — bukan nol manual yang membekukan sisa tagihan pada
 * baris lain.
 */
const resetAmount = (idx) => {
    rows.value[idx].amount = 0;
    rows.value[idx].touched = false;
};

/** Nol yang otomatis tidak perlu tombol hapus; tidak ada yang dihapus di sana. */
const canResetAmount = (idx) => idx !== balancerIndex.value && amountAt(idx) > 0;

/**
 * Warna tombol cepat meniru warna uang kertas rupiah — hijau 20rb, biru 50rb,
 * merah 100rb — supaya kasir mengenalinya sekilas dan barisnya terlihat rapi.
 */
const denominationButtonClass = (denomination) => ({
    20000: 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100',
    50000: 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100',
    100000: 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100',
}[denomination] ?? 'bg-success/10 text-success border-success/20 hover:bg-success/20');

const addPaymentRow = () => {
    rows.value.push(blankRow());
};

const removePaymentRow = (idx) => {
    if (rows.value.length > 1) rows.value.splice(idx, 1);
};

const confirm = () => {
    if (!isValid.value) return;

    emit('confirm', rows.value.map((row, idx) => ({
        payment_method_id: row.payment_method_id,
        amount: amountAt(idx),
        reference_code: row.reference_code || null,
        proof_token: row.proof_token,
        // Dua yang terakhir hanya berarti bagi modal edit, yang mengoper
        // hasilnya kembali ke sini bila kasir membuka modal ini lagi. Jalur
        // checkout mengabaikan keduanya.
        proof_preview: row.proof_preview,
        proof_payment_id: row.proof_payment_id,
    })));

    emit('close');
};

const close = () => {
    emit('close');
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50" @click="close" />

                <!-- Modal — wider -->
                <div class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full max-h-[85vh] flex flex-col">
                    <!-- Header + Total (sticky, not scrollable) -->
                    <div class="sticky top-0 bg-white border-b border-gray-200 rounded-t-xl z-10 flex-shrink-0">
                        <div class="px-6 py-4 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-800">Pembayaran</h3>
                            <button @click="close" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <!-- Total headline (always visible) -->
                        <div class="bg-primary/10 px-6 py-3 flex items-center justify-between">
                            <span class="text-sm text-primary font-medium">Total Belanja</span>
                            <span class="text-2xl font-bold text-primary">{{ formatCurrency(totalAmount) }}</span>
                        </div>
                    </div>

                    <!-- Scrollable content -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-4">
                        <!-- Payment rows -->
                        <div v-for="(row, idx) in rows" :key="idx" class="border border-gray-200 rounded-lg p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-600">Pembayaran {{ idx + 1 }}</span>
                                <button
                                    v-if="rows.length > 1"
                                    @click="removePaymentRow(idx)"
                                    class="text-xs text-red-500 hover:text-red-700"
                                >
                                    Hapus
                                </button>
                            </div>

                            <!-- Payment method -->
                            <SelectDropdown
                                v-model="row.payment_method_id"
                                :options="paymentMethodOptions"
                                placeholder="Pilih metode pembayaran"
                            />

                            <!-- Nominal: satu input untuk semua metode. Baris
                                 penyeimbang menampilkan angka turunan sampai
                                 kasir mengetik sendiri di sana. -->
                            <template v-if="row.payment_method_id">
                                <div>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">Rp</span>
                                        <input
                                            :value="displayFor(idx)"
                                            @input="onAmountInput(idx, $event)"
                                            @focus="onAmountFocus(idx, $event)"
                                            @blur="onAmountBlur(idx, $event)"
                                            type="text"
                                            inputmode="numeric"
                                            placeholder="0"
                                            class="w-full pl-9 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                                            :class="idx === balancerIndex ? 'pr-24' : 'pr-10'"
                                        />
                                        <span
                                            v-if="idx === balancerIndex"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
                                        >
                                            Otomatis
                                        </span>

                                        <!-- Hapus nominal. `mousedown.prevent`
                                             menahan blur input supaya satu tap
                                             cukup: tanpa itu tap pertama hanya
                                             memindahkan fokus. -->
                                        <button
                                            v-else-if="canResetAmount(idx)"
                                            type="button"
                                            title="Hapus nominal"
                                            aria-label="Hapus nominal"
                                            @mousedown.prevent
                                            @click="resetAmount(idx)"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 flex h-6 w-6 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-red-600"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Tombol cepat untuk SETIAP baris tunai.
                                         Dulu hanya dirender untuk baris pertama
                                         dan diam-diam mati begitu ada baris
                                         kedua. -->
                                    <div v-if="isCash(row.payment_method_id)" class="flex flex-wrap gap-2 mt-2">
                                        <button
                                            @click="fillRemainder(idx)"
                                            class="px-3 py-1.5 text-xs font-medium bg-primary/10 text-primary rounded-lg hover:bg-primary/20 transition border border-primary/20"
                                        >
                                            {{ rows.length > 1 ? 'Sisa' : 'Uang Pas' }}
                                        </button>
                                        <button
                                            v-for="denomination in [20000, 50000, 100000]"
                                            :key="denomination"
                                            @click="quickCash(idx, denomination)"
                                            class="px-3 py-1.5 text-xs font-medium rounded-lg transition border"
                                            :class="denominationButtonClass(denomination)"
                                        >
                                            +{{ denomination / 1000 }}rb
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- Reference code (for QRIS/Transfer) -->
                            <input
                                v-if="needsReferenceCode(row.payment_method_id)"
                                v-model="row.reference_code"
                                type="text"
                                placeholder="Kode referensi (opsional)"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                            />

                            <!-- Foto bukti bayar ([BL-075]). Hanya untuk baris
                                 non-tunai, dan hanya bila tokonya menyalakan.
                                 `capture="environment"` membuka kamera belakang
                                 langsung di ponsel, tanpa mampir ke galeri. -->
                            <div v-if="needsProof(row)" class="space-y-2">
                                <input
                                    :id="'proof-' + idx"
                                    type="file"
                                    accept="image/*"
                                    capture="environment"
                                    class="hidden"
                                    @change="onProofPicked(idx, $event)"
                                />

                                <div v-if="row.proof_preview" class="flex items-center gap-3 rounded-lg border border-success/30 bg-success/5 p-2">
                                    <img :src="row.proof_preview" alt="Pratinjau bukti bayar" class="h-14 w-14 rounded object-cover border border-gray-200 bg-white" />
                                    <span class="flex-1 text-xs font-medium text-success">Bukti bayar terlampir</span>
                                    <button type="button" class="text-xs text-red-500 hover:text-red-700" @click="clearProof(idx)">
                                        Ganti
                                    </button>
                                </div>

                                <!-- Bukti yang SUDAH melekat pada pembayaran
                                     tersimpan ([BL-075] sisa). Tanpa blok ini
                                     kasir hanya bisa menyimpulkan fotonya ada
                                     dari fakta barisnya tidak diwajibkan — dan
                                     kesimpulan bukan pemeriksaan. -->
                                <div
                                    v-else-if="row.proof_payment_id"
                                    class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 p-2"
                                >
                                    <a
                                        :href="existingProofUrl(row, 'full')"
                                        target="_blank"
                                        rel="noopener"
                                        title="Lihat bukti bayar"
                                    >
                                        <img
                                            :src="existingProofUrl(row, 'thumb')"
                                            alt="Bukti bayar tersimpan"
                                            class="h-14 w-14 rounded object-cover border border-gray-200 bg-white"
                                        />
                                    </a>
                                    <span class="flex-1 text-xs font-medium text-gray-600">Bukti bayar tersimpan</span>
                                    <label
                                        :for="'proof-' + idx"
                                        class="cursor-pointer text-xs font-medium text-primary hover:underline"
                                    >
                                        {{ row.proof_uploading ? 'Mengunggah…' : 'Potret ulang' }}
                                    </label>
                                </div>

                                <label
                                    v-else
                                    :for="'proof-' + idx"
                                    class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border-2 border-dashed px-3 py-2.5 text-xs font-medium"
                                    :class="proofRequiredFor(row)
                                        ? 'border-amber-300 bg-amber-50/60 text-amber-800 hover:border-amber-400'
                                        : 'border-gray-300 bg-gray-50 text-gray-600 hover:border-gray-400'"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    {{ row.proof_uploading
                                        ? 'Mengunggah foto...'
                                        : (proofRequiredFor(row) ? 'Foto bukti bayar (wajib)' : 'Foto bukti bayar (opsional)') }}
                                </label>

                                <p v-if="row.proof_error" role="alert" class="text-xs text-destructive">{{ row.proof_error }}</p>
                            </div>
                        </div>

                        <!-- Add payment row -->
                        <button
                            @click="addPaymentRow"
                            class="w-full py-2 border-2 border-dashed border-border rounded-lg text-sm text-muted-foreground hover:border-primary hover:text-primary transition"
                        >
                            + Tambah Metode Bayar
                        </button>

                        <!-- Summary -->
                        <div class="border-t border-gray-200 pt-4 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Total Bayar</span>
                                <span class="font-medium" :class="totalPaid >= totalAmount ? 'text-success' : 'text-destructive'">
                                    {{ formatCurrency(totalPaid) }}
                                </span>
                            </div>
                            <div v-if="rows.length > 1 && nonCashPaid > 0" class="flex justify-between text-xs text-gray-400">
                                <span>Tunai {{ formatCurrency(cashPaid) }}</span>
                                <span>Non-tunai {{ formatCurrency(nonCashPaid) }}</span>
                            </div>
                            <!-- Kembalian dihitung dari porsi TUNAI saja: kelebihan
                                 pada baris QRIS/transfer tidak bisa dikembalikan. -->
                            <div v-if="change > 0" class="flex justify-between text-sm">
                                <span class="text-gray-500">Kembalian</span>
                                <span class="font-semibold text-success">{{ formatCurrency(change) }}</span>
                            </div>
                            <div v-if="shortfall > 0" class="flex justify-between items-center text-sm font-semibold text-destructive">
                                <span>Masih kurang</span>
                                <span>{{ formatCurrency(shortfall) }}</span>
                            </div>
                        </div>

                        <!-- Kenapa tombol Bayar mati. Tombol kelabu tanpa
                             penjelasan adalah jalan buntu, bukan validasi. -->
                        <div
                            v-if="blockingReason"
                            class="flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5"
                            role="status"
                        >
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                            </svg>
                            <span class="text-xs text-amber-800">{{ blockingReason }}</span>
                        </div>
                    </div>

                    <!-- Footer (sticky) -->
                    <div class="sticky bottom-0 bg-white border-t border-gray-200 px-6 py-4 flex gap-3 rounded-b-xl flex-shrink-0">
                        <button
                            @click="close"
                            class="flex-1 py-2.5 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition"
                        >
                            Batal
                        </button>
                        <button
                            @click="confirm"
                            :disabled="!isValid"
                            class="flex-1 py-2.5 bg-success text-success-foreground font-semibold rounded-lg hover:bg-success/90 transition disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            Bayar
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
