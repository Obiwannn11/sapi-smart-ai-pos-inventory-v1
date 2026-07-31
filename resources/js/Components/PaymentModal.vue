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
 *   KEMBALIAN HANYA DARI PORSI TUNAI. QRIS tidak mengenal kembalian, jadi
 *   kelebihan yang berasal dari baris non-tunai tidak boleh ditawarkan sebagai
 *   uang keluar dari laci.
 */
import { ref, computed, watch, nextTick } from 'vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    totalAmount: { type: Number, default: 0 },
    paymentMethods: { type: Array, default: () => [] },
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
});

const rows = ref([blankRow()]);

const formatCurrency = (value) => 'Rp ' + Number(value).toLocaleString('id-ID');

const formatNumber = (value) => {
    const num = Number(String(value).replace(/\D/g, ''));
    if (!num) return '';
    return num.toLocaleString('id-ID');
};

watch(() => props.show, (val) => {
    if (val) rows.value = [blankRow()];
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

const blockingReason = computed(() => {
    if (emptyRowIndex.value !== -1) {
        return `Lengkapi metode dan nominal pada Pembayaran ${emptyRowIndex.value + 1}.`;
    }
    if (nonCashOverpaid.value) {
        return 'Nominal non-tunai melebihi total belanja — non-tunai tidak bisa dikembalikan.';
    }
    if (shortfall.value > 0) {
        return `Masih kurang ${formatCurrency(shortfall.value)}.`;
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

/**
 * Metode baru dipilih: baris non-tunai yang belum disentuh otomatis jadi
 * penyeimbang, jadi tidak ada yang perlu diisi di sini. Baris tunai yang masih
 * kosong diisikan sisanya — inilah yang dulu memaksa kasir mengetik manual
 * tiap kali split.
 */
const onMethodChange = (idx) => {
    const row = rows.value[idx];

    if (isCash(row.payment_method_id) && !row.touched) {
        fillRemainder(idx);
    }
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
                                @change="onMethodChange(idx)"
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
                                            class="w-full pl-9 pr-24 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                                        />
                                        <span
                                            v-if="idx === balancerIndex"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
                                        >
                                            Otomatis
                                        </span>
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
                                            class="px-3 py-1.5 text-xs font-medium bg-success/10 text-success rounded-lg hover:bg-success/20 transition border border-success/20"
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
                        </div>

                        <!-- Add payment row -->
                        <button
                            @click="addPaymentRow"
                            class="w-full py-2 border-2 border-dashed border-border rounded-lg text-sm text-muted-foreground hover:border-primary hover:text-primary transition"
                        >
                            + Split Pembayaran
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
