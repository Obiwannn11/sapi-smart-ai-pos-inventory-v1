<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    transaction: Object,
});

const emit = defineEmits(['close', 'print']);

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatTime = (date) => {
    return new Date(date).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const totalPaid = computed(() => {
    if (!props.transaction?.payments) return 0;
    return props.transaction.payments.reduce((sum, p) => sum + Number(p.amount), 0);
});

const changeAmount = computed(() => Number(props.transaction?.change_amount || 0));

const itemCount = computed(() => {
    if (!props.transaction?.items) return 0;
    return props.transaction.items.reduce((sum, item) => sum + Number(item.qty), 0);
});

const close = () => emit('close');
const print = () => emit('print');
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show && transaction" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50" @click="close" />

                <!-- Modal -->
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm max-h-[92vh] flex flex-col overflow-hidden">
                    <!-- ===== SUCCESS HEADER ===== -->
                    <div class="px-6 pt-7 pb-5 text-center bg-gradient-to-b from-success/10 to-transparent">
                        <div class="mx-auto mb-3 flex items-center justify-center w-16 h-16 rounded-full bg-success/15">
                            <svg class="w-9 h-9 text-success success-check" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <h2 class="text-lg font-bold text-gray-800">Transaksi Berhasil</h2>
                        <p class="text-xs text-gray-500 mt-1">
                            <span class="font-semibold text-gray-600">{{ transaction.code }}</span>
                            · {{ formatTime(transaction.created_at) }}
                        </p>
                    </div>

                    <!-- ===== CHANGE (most important for cashier) ===== -->
                    <div class="px-6">
                        <div class="rounded-xl bg-primary/5 border border-primary/15 px-4 py-3 flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-600">Kembalian</span>
                            <span class="text-2xl font-bold text-primary">{{ formatCurrency(changeAmount) }}</span>
                        </div>
                    </div>

                    <!-- ===== ORDER DETAIL ===== -->
                    <div class="flex-1 overflow-y-auto px-6 py-4 space-y-3">
                        <div class="flex items-center justify-between text-xs text-gray-400 uppercase tracking-wide font-medium">
                            <span>Detail Pesanan</span>
                            <span>{{ itemCount }} item</span>
                        </div>

                        <div class="space-y-2.5">
                            <div
                                v-for="item in transaction.items"
                                :key="item.id"
                                class="flex justify-between items-start gap-3 text-sm"
                            >
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-800 leading-tight">{{ item.variant_name }}</p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        {{ item.qty }} × {{ formatCurrency(item.unit_price) }}
                                    </p>
                                    <div v-if="item.modifiers && item.modifiers.length > 0" class="mt-0.5 space-y-0.5">
                                        <p
                                            v-for="mod in item.modifiers"
                                            :key="mod.id"
                                            class="text-xs text-gray-400"
                                        >
                                            + {{ mod.modifier_name }}
                                        </p>
                                    </div>
                                    <p v-if="item.notes" class="text-xs text-amber-600 italic mt-0.5">
                                        * {{ item.notes }}
                                    </p>
                                </div>
                                <span class="shrink-0 font-semibold text-gray-700">{{ formatCurrency(item.subtotal) }}</span>
                            </div>
                        </div>

                        <!-- Totals -->
                        <div class="pt-3 mt-1 border-t border-dashed border-gray-200 space-y-1.5">
                            <div class="flex justify-between text-base font-bold text-gray-800">
                                <span>Total</span>
                                <span>{{ formatCurrency(transaction.total_amount) }}</span>
                            </div>
                            <div
                                v-for="payment in transaction.payments"
                                :key="payment.id"
                                class="flex justify-between text-xs text-gray-500"
                            >
                                <span>{{ payment.payment_method?.name || 'Pembayaran' }}</span>
                                <span>{{ formatCurrency(payment.amount) }}</span>
                            </div>
                            <div v-if="transaction.payments && transaction.payments.length > 1" class="flex justify-between text-xs font-medium text-gray-600">
                                <span>Dibayar</span>
                                <span>{{ formatCurrency(totalPaid) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- ===== ACTIONS ===== -->
                    <div class="px-6 py-4 border-t border-gray-100 flex gap-3">
                        <button
                            @click="close"
                            class="flex-1 py-2.5 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition text-sm flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Transaksi Baru
                        </button>
                        <button
                            @click="print"
                            class="flex-[1.2] py-2.5 bg-primary text-primary-foreground font-semibold rounded-lg hover:bg-primary/90 transition text-sm flex items-center justify-center gap-2"
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

<style scoped>
.success-check {
    animation: success-pop 0.35s ease-out;
}

@keyframes success-pop {
    0% {
        transform: scale(0.4);
        opacity: 0;
    }
    60% {
        transform: scale(1.15);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}
</style>
