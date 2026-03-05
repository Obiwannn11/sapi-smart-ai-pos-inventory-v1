<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    transaction: Object,
});

const emit = defineEmits(['close']);

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatDate = (date) => {
    return new Date(date).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const close = () => {
    emit('close');
};

const printReceipt = () => {
    window.print();
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
            <div v-if="show && transaction" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50 print:hidden" @click="close" />

                <!-- Modal -->
                <div class="relative bg-white rounded-xl shadow-2xl max-w-sm w-full max-h-[85vh] overflow-y-auto print:max-w-none print:shadow-none print:rounded-none">
                    <!-- Receipt Content -->
                    <div class="p-6" id="receipt-content">
                        <!-- Header -->
                        <div class="text-center border-b border-dashed border-gray-300 pb-4 mb-4">
                            <h3 class="text-lg font-bold text-gray-800">SAPI POS</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ formatDate(transaction.created_at) }}</p>
                            <p class="text-sm font-semibold text-indigo-600 mt-1">{{ transaction.code }}</p>
                        </div>

                        <!-- Items -->
                        <div class="space-y-3 border-b border-dashed border-gray-300 pb-4 mb-4">
                            <div v-for="item in transaction.items" :key="item.id">
                                <div class="flex justify-between text-sm">
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium text-gray-800 truncate">{{ item.variant_name }}</p>
                                        <p class="text-xs text-gray-400">{{ item.qty }} x {{ formatCurrency(item.unit_price) }}</p>
                                    </div>
                                    <span class="text-sm font-medium text-gray-700 ml-3">{{ formatCurrency(item.subtotal) }}</span>
                                </div>

                                <!-- Modifiers -->
                                <div v-if="item.modifiers && item.modifiers.length > 0" class="ml-4 mt-0.5">
                                    <p v-for="mod in item.modifiers" :key="mod.id" class="text-xs text-gray-400">
                                        + {{ mod.modifier_name }}
                                        <span v-if="Number(mod.extra_price) > 0">({{ formatCurrency(mod.extra_price) }})</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Totals -->
                        <div class="space-y-2 border-b border-dashed border-gray-300 pb-4 mb-4">
                            <div class="flex justify-between text-sm font-bold">
                                <span>Total</span>
                                <span>{{ formatCurrency(transaction.total_amount) }}</span>
                            </div>

                            <!-- Payments -->
                            <div v-for="payment in transaction.payments" :key="payment.id" class="flex justify-between text-xs text-gray-500">
                                <span>{{ payment.payment_method?.name || 'Pembayaran' }}</span>
                                <span>{{ formatCurrency(payment.amount) }}</span>
                            </div>

                            <div v-if="Number(transaction.change_amount) > 0" class="flex justify-between text-sm font-semibold text-green-600">
                                <span>Kembalian</span>
                                <span>{{ formatCurrency(transaction.change_amount) }}</span>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div v-if="transaction.notes" class="text-xs text-gray-400 text-center mb-4">
                            {{ transaction.notes }}
                        </div>

                        <!-- Footer -->
                        <div class="text-center">
                            <p class="text-xs text-gray-400">Terima kasih atas pembelian Anda!</p>
                        </div>
                    </div>

                    <!-- Actions (hide on print) -->
                    <div class="px-6 pb-6 flex gap-3 print:hidden">
                        <button
                            @click="close"
                            class="flex-1 py-2.5 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition"
                        >
                            Tutup
                        </button>
                        <button
                            @click="printReceipt"
                            class="flex-1 py-2.5 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                            </svg>
                            Cetak Struk
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #receipt-content, #receipt-content * {
        visibility: visible;
    }
    #receipt-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 80mm;
    }
}
</style>
