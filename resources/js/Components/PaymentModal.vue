<script setup>
import { ref, computed, watch } from 'vue';

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

// Reset ketika modal dibuka
watch(() => props.show, (val) => {
    if (val) {
        // Mulai dengan 1 baris payment (default)
        payments.value = [{
            payment_method_id: null,
            amount: '',
            reference_code: '',
        }];
    }
});

const totalPaid = computed(() => {
    return payments.value.reduce((sum, p) => sum + (Number(p.amount) || 0), 0);
});

const change = computed(() => {
    return Math.max(0, totalPaid.value - props.totalAmount);
});

const isValid = computed(() => {
    if (payments.value.length === 0) return false;

    // Semua payment harus punya method dan amount > 0
    for (const p of payments.value) {
        if (!p.payment_method_id || !p.amount || Number(p.amount) <= 0) return false;
    }

    // Total bayar harus >= total belanja
    return totalPaid.value >= props.totalAmount;
});

const addPaymentRow = () => {
    payments.value.push({
        payment_method_id: null,
        amount: '',
        reference_code: '',
    });
};

const removePaymentRow = (index) => {
    if (payments.value.length > 1) {
        payments.value.splice(index, 1);
    }
};

const getMethodType = (methodId) => {
    const method = props.paymentMethods.find(m => m.id === methodId);
    return method?.type || '';
};

const needsReferenceCode = (methodId) => {
    const type = getMethodType(methodId);
    return type === 'qris_static' || type === 'bank_transfer';
};

const payExact = () => {
    if (payments.value.length === 1) {
        payments.value[0].amount = props.totalAmount;
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

                <!-- Modal -->
                <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[85vh] overflow-y-auto">
                    <!-- Header -->
                    <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Pembayaran</h3>
                        <button @click="close" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <!-- Total yang harus dibayar -->
                        <div class="bg-indigo-50 rounded-lg p-4 text-center">
                            <p class="text-sm text-indigo-600 font-medium">Total Belanja</p>
                            <p class="text-2xl font-bold text-indigo-700">{{ formatCurrency(totalAmount) }}</p>
                        </div>

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
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option :value="null" disabled>Pilih metode pembayaran</option>
                                <option v-for="method in paymentMethods" :key="method.id" :value="method.id">
                                    {{ method.name }}
                                </option>
                            </select>

                            <!-- Amount -->
                            <div class="relative">
                                <input
                                    v-model="payment.amount"
                                    type="number"
                                    min="0"
                                    step="1000"
                                    placeholder="Nominal"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <button
                                    v-if="payments.length === 1 && idx === 0"
                                    @click="payExact"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                >
                                    Uang pas
                                </button>
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
                            <div v-if="change > 0" class="flex justify-between text-sm">
                                <span class="text-gray-500">Kembalian</span>
                                <span class="font-semibold text-green-600">{{ formatCurrency(change) }}</span>
                            </div>
                            <div v-if="totalPaid < totalAmount" class="text-xs text-red-500 text-center">
                                Masih kurang {{ formatCurrency(totalAmount - totalPaid) }}
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="sticky bottom-0 bg-white border-t border-gray-200 px-6 py-4 flex gap-3">
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
