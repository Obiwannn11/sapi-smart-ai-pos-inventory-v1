<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    transaction: Object,
    tenantName: { type: String, default: 'SAPI POS' },
});

const emit = defineEmits(['close']);

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
};

const formatTime = (date) => {
    return new Date(date).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const subtotal = computed(() => {
    if (!props.transaction?.items) return 0;
    return props.transaction.items.reduce((sum, item) => sum + Number(item.subtotal), 0);
});

const totalPaid = computed(() => {
    if (!props.transaction?.payments) return 0;
    return props.transaction.payments.reduce((sum, p) => sum + Number(p.amount), 0);
});

const close = () => emit('close');

const printReceipt = () => window.print();
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

                <!-- Modal wrapper -->
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-xs max-h-[92vh] flex flex-col print:max-w-none print:shadow-none print:rounded-none print:fixed print:inset-0">

                    <!-- Scrollable receipt content -->
                    <div class="flex-1 overflow-y-auto print:overflow-visible" id="receipt-content">
                        <div class="p-5 font-mono text-xs text-gray-800 space-y-0">

                            <!-- ===== HEADER ===== -->
                            <div class="text-center pb-3 border-b border-dashed border-gray-400">
                                <p class="text-base font-bold uppercase tracking-widest">{{ tenantName }}</p>
                                <p class="text-[10px] text-gray-500 mt-0.5">Point of Sale</p>
                            </div>

                            <!-- ===== TRANSACTION INFO ===== -->
                            <div class="py-2.5 border-b border-dashed border-gray-400 space-y-0.5">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">No.</span>
                                    <span class="font-semibold">{{ transaction.code }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Tanggal</span>
                                    <span>{{ formatDate(transaction.created_at) }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Waktu</span>
                                    <span>{{ formatTime(transaction.created_at) }}</span>
                                </div>
                                <div v-if="transaction.user?.name" class="flex justify-between">
                                    <span class="text-gray-500">Kasir</span>
                                    <span>{{ transaction.user.name }}</span>
                                </div>
                                <div v-if="transaction.customer_name" class="flex justify-between">
                                    <span class="text-gray-500">Pelanggan</span>
                                    <span class="font-medium">{{ transaction.customer_name }}</span>
                                </div>
                            </div>

                            <!-- ===== ITEMS ===== -->
                            <div class="py-2.5 border-b border-dashed border-gray-400 space-y-2">
                                <div v-for="item in transaction.items" :key="item.id">
                                    <!-- Item name + subtotal -->
                                    <div class="flex justify-between items-start gap-2">
                                        <span class="flex-1 font-medium leading-tight">{{ item.variant_name }}</span>
                                        <span class="shrink-0 font-semibold">{{ formatCurrency(item.subtotal) }}</span>
                                    </div>
                                    <!-- Qty × unit price -->
                                    <div class="flex justify-between text-gray-500 pl-2">
                                        <span>{{ item.qty }} × {{ formatCurrency(item.unit_price) }}</span>
                                    </div>
                                    <!-- Modifiers -->
                                    <div v-if="item.modifiers && item.modifiers.length > 0" class="pl-2 space-y-0.5">
                                        <div
                                            v-for="mod in item.modifiers"
                                            :key="mod.id"
                                            class="flex justify-between text-gray-400"
                                        >
                                            <span>+ {{ mod.modifier_name }}</span>
                                            <span v-if="Number(mod.extra_price) > 0">{{ formatCurrency(mod.extra_price) }}</span>
                                        </div>
                                    </div>
                                    <!-- Item Notes -->
                                    <p v-if="item.notes" class="pl-2 text-amber-600 italic">
                                        * {{ item.notes }}
                                    </p>
                                </div>
                            </div>

                            <!-- ===== TOTALS ===== -->
                            <div class="py-2.5 border-b border-dashed border-gray-400 space-y-1">
                                <div class="flex justify-between">
                                    <span class="text-gray-500">Subtotal</span>
                                    <span>{{ formatCurrency(subtotal) }}</span>
                                </div>
                                <div class="flex justify-between font-bold text-sm border-t border-gray-300 pt-1 mt-1">
                                    <span>TOTAL</span>
                                    <span>{{ formatCurrency(transaction.total_amount) }}</span>
                                </div>
                            </div>

                            <!-- ===== PAYMENT ===== -->
                            <div class="py-2.5 border-b border-dashed border-gray-400 space-y-1">
                                <div
                                    v-for="payment in transaction.payments"
                                    :key="payment.id"
                                    class="flex justify-between"
                                >
                                    <span class="text-gray-500">{{ payment.payment_method?.name || 'Pembayaran' }}</span>
                                    <span>{{ formatCurrency(payment.amount) }}</span>
                                </div>
                                <div v-if="Number(transaction.change_amount) > 0" class="flex justify-between font-semibold">
                                    <span>Kembali</span>
                                    <span>{{ formatCurrency(transaction.change_amount) }}</span>
                                </div>
                            </div>

                            <!-- ===== NOTES ===== -->
                            <div v-if="transaction.notes" class="py-2 border-b border-dashed border-gray-400 text-center text-gray-500 italic">
                                {{ transaction.notes }}
                            </div>

                            <!-- ===== FOOTER ===== -->
                            <div class="pt-3 text-center space-y-0.5">
                                <p class="font-semibold">*** Terima Kasih ***</p>
                                <p class="text-gray-400 text-[10px]">Simpan struk ini sebagai bukti pembayaran</p>
                            </div>

                        </div>
                    </div>

                    <!-- Action buttons (hidden on print) -->
                    <div class="px-5 py-4 flex gap-3 border-t border-gray-200 print:hidden shrink-0">
                        <button
                            @click="close"
                            class="flex-1 py-2.5 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition text-sm"
                        >
                            Tutup
                        </button>
                        <button
                            @click="printReceipt"
                            class="flex-1 py-2.5 bg-indigo-600 text-white font-semibold rounded-lg hover:bg-indigo-700 transition flex items-center justify-center gap-2 text-sm"
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
        font-size: 11px;
    }
}
</style>
