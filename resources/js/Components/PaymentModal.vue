<script setup>
import { ref, computed, watch, nextTick } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    totalAmount: { type: Number, default: 0 },
    paymentMethods: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'confirm']);

const payments = ref([]);

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatNumber = (value) => {
    const num = Number(String(value).replace(/\D/g, ''));
    if (!num) return '';
    return num.toLocaleString('id-ID');
};

// Reset ketika modal dibuka
watch(() => props.show, (val) => {
    if (val) {
        payments.value = [{
            payment_method_id: null,
            amount: '',
            displayAmount: '',
            reference_code: '',
        }];
    }
});

const getMethodType = (methodId) => {
    const method = props.paymentMethods.find(m => m.id === methodId);
    return method?.type || '';
};

const isCash = (methodId) => getMethodType(methodId) === 'cash';
const isNonCash = (methodId) => methodId && !isCash(methodId);

const needsReferenceCode = (methodId) => {
    const type = getMethodType(methodId);
    return type === 'qris_static' || type === 'bank_transfer';
};

// Ketika metode pembayaran dipilih, auto-set amount jika non-cash
const onMethodChange = (idx) => {
    const p = payments.value[idx];
    if (isNonCash(p.payment_method_id)) {
        // Remaining = total - amount from other rows
        const otherPaid = payments.value
            .filter((_, i) => i !== idx)
            .reduce((sum, row) => sum + (Number(row.amount) || 0), 0);
        const autoAmount = Math.max(0, props.totalAmount - otherPaid);
        p.amount = autoAmount;
        p.displayAmount = formatNumber(autoAmount);
    }
};

// Handle amount input (hanya untuk cash)
const onAmountInput = (idx, event) => {
    const raw = event.target.value.replace(/\D/g, '');
    const num = Number(raw) || 0;
    payments.value[idx].amount = num;
    payments.value[idx].displayAmount = num > 0 ? formatNumber(num) : '';
    nextTick(() => {
        event.target.value = payments.value[idx].displayAmount;
    });
};

const onAmountFocus = (idx, event) => {
    const num = payments.value[idx].amount;
    if (num > 0) {
        event.target.value = String(num);
    }
};

const onAmountBlur = (idx, event) => {
    const num = payments.value[idx].amount;
    event.target.value = num > 0 ? formatNumber(num) : '';
    payments.value[idx].displayAmount = num > 0 ? formatNumber(num) : '';
};

const totalPaid = computed(() => {
    return payments.value.reduce((sum, p) => sum + (Number(p.amount) || 0), 0);
});

const change = computed(() => {
    return Math.max(0, totalPaid.value - props.totalAmount);
});

const isValid = computed(() => {
    if (payments.value.length === 0) return false;
    for (const p of payments.value) {
        if (!p.payment_method_id || !p.amount || Number(p.amount) <= 0) return false;
    }
    return totalPaid.value >= props.totalAmount;
});

const addPaymentRow = () => {
    payments.value.push({
        payment_method_id: null,
        amount: '',
        displayAmount: '',
        reference_code: '',
    });
};

const removePaymentRow = (index) => {
    if (payments.value.length > 1) {
        payments.value.splice(index, 1);
    }
};

// Tombol uang pas
const payExact = () => {
    if (payments.value.length === 1) {
        payments.value[0].amount = props.totalAmount;
        payments.value[0].displayAmount = formatNumber(props.totalAmount);
    }
};

// Tombol denominasi cepat
const quickCash = (denomination) => {
    if (payments.value.length >= 1) {
        const current = Number(payments.value[0].amount) || 0;
        const newAmount = current + denomination;
        payments.value[0].amount = newAmount;
        payments.value[0].displayAmount = formatNumber(newAmount);
    }
};

const confirm = () => {
    if (!isValid.value) return;
    const data = payments.value.map(p => ({
        payment_method_id: p.payment_method_id,
        amount: Number(p.amount),
        reference_code: p.reference_code || null,
    }));
    emit('confirm', data);
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
                        <div class="bg-indigo-50 px-6 py-3 flex items-center justify-between">
                            <span class="text-sm text-indigo-600 font-medium">Total Belanja</span>
                            <span class="text-2xl font-bold text-indigo-700">{{ formatCurrency(totalAmount) }}</span>
                        </div>
                    </div>

                    <!-- Scrollable content -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-4">
                        <!-- Payment rows -->
                        <div v-for="(payment, idx) in payments" :key="idx" class="border border-gray-200 rounded-lg p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-600">Pembayaran {{ idx + 1 }}</span>
                                <button
                                    v-if="payments.length > 1"
                                    @click="removePaymentRow(idx)"
                                    class="text-xs text-red-500 hover:text-red-700"
                                >
                                    Hapus
                                </button>
                            </div>

                            <!-- Payment method -->
                            <select
                                v-model="payment.payment_method_id"
                                @change="onMethodChange(idx)"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option :value="null" disabled>Pilih metode pembayaran</option>
                                <option v-for="method in paymentMethods" :key="method.id" :value="method.id">
                                    {{ method.name }}
                                </option>
                            </select>

                            <!-- CASH: input nominal + quick buttons -->
                            <div v-if="isCash(payment.payment_method_id)">
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">Rp</span>
                                    <input
                                        :value="payment.displayAmount"
                                        @input="onAmountInput(idx, $event)"
                                        @focus="onAmountFocus(idx, $event)"
                                        @blur="onAmountBlur(idx, $event)"
                                        type="text"
                                        inputmode="numeric"
                                        placeholder="0"
                                        class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <!-- Quick denomination buttons -->
                                <div v-if="idx === 0" class="flex flex-wrap gap-2 mt-2">
                                    <button
                                        @click="payExact"
                                        class="px-3 py-1.5 text-xs font-medium bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition border border-indigo-200"
                                    >
                                        Uang Pas
                                    </button>
                                    <button
                                        @click="quickCash(20000)"
                                        class="px-3 py-1.5 text-xs font-medium bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition border border-green-200"
                                    >
                                        +20rb
                                    </button>
                                    <button
                                        @click="quickCash(50000)"
                                        class="px-3 py-1.5 text-xs font-medium bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition border border-green-200"
                                    >
                                        +50rb
                                    </button>
                                    <button
                                        @click="quickCash(100000)"
                                        class="px-3 py-1.5 text-xs font-medium bg-green-50 text-green-700 rounded-lg hover:bg-green-100 transition border border-green-200"
                                    >
                                        +100rb
                                    </button>
                                </div>
                            </div>

                            <!-- NON-CASH: nominal otomatis sesuai total -->
                            <div
                                v-else-if="isNonCash(payment.payment_method_id)"
                                class="flex items-center gap-2 px-3 py-2.5 bg-blue-50 border border-blue-200 rounded-lg"
                            >
                                <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-sm text-blue-700">
                                    Nominal otomatis: <strong>{{ formatCurrency(payment.amount) }}</strong>
                                </span>
                            </div>

                            <!-- Reference code (for QRIS/Transfer) -->
                            <input
                                v-if="needsReferenceCode(payment.payment_method_id)"
                                v-model="payment.reference_code"
                                type="text"
                                placeholder="Kode referensi (opsional)"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>

                        <!-- Add payment row -->
                        <button
                            @click="addPaymentRow"
                            class="w-full py-2 border-2 border-dashed border-gray-300 rounded-lg text-sm text-gray-500 hover:border-indigo-400 hover:text-indigo-600 transition"
                        >
                            + Split Pembayaran
                        </button>

                        <!-- Summary -->
                        <div class="border-t border-gray-200 pt-4 space-y-2">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-500">Total Bayar</span>
                                <span class="font-medium" :class="totalPaid >= totalAmount ? 'text-green-600' : 'text-red-600'">
                                    {{ formatCurrency(totalPaid) }}
                                </span>
                            </div>
                            <div v-if="change > 0 && payments.some(p => isCash(p.payment_method_id))" class="flex justify-between text-sm">
                                <span class="text-gray-500">Kembalian</span>
                                <span class="font-semibold text-green-600">{{ formatCurrency(change) }}</span>
                            </div>
                            <div v-if="totalPaid < totalAmount" class="text-xs text-red-500 text-center">
                                Masih kurang {{ formatCurrency(totalAmount - totalPaid) }}
                            </div>
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
                            class="flex-1 py-2.5 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            Bayar
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
