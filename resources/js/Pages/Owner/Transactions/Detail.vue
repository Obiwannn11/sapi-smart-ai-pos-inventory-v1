<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    transaction: Object,
});

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatDateTime = (datetime) => {
    return new Date(datetime).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const statusBadge = (status) => {
    switch (status) {
        case 'completed': return 'bg-green-100 text-green-800';
        case 'voided': return 'bg-red-100 text-red-800';
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const statusLabel = (status) => {
    switch (status) {
        case 'completed': return 'Selesai';
        case 'voided': return 'Void';
        case 'pending': return 'Pending';
        default: return status;
    }
};

// Void logic
const showVoidDialog = ref(false);
const voidForm = useForm({});

const isToday = () => {
    const txDate = new Date(props.transaction.created_at).toDateString();
    const today = new Date().toDateString();
    return txDate === today;
};

const canVoid = () => {
    return props.transaction.status === 'completed' && isToday();
};

const doVoid = () => {
    voidForm.post(`/owner/transactions/${props.transaction.id}/void`, {
        preserveScroll: true,
        onSuccess: () => {
            showVoidDialog.value = false;
        },
    });
};
</script>

<template>
    <Head :title="`Transaksi ${transaction.code}`" />

    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <Link href="/owner/transactions" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </Link>
                    <h1 class="text-2xl font-bold text-gray-900">{{ transaction.code }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="statusBadge(transaction.status)">
                        {{ statusLabel(transaction.status) }}
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-1 ml-8">{{ formatDateTime(transaction.created_at) }}</p>
            </div>

            <button
                v-if="canVoid()"
                @click="showVoidDialog = true"
                class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 transition-colors"
                :disabled="voidForm.processing"
            >
                Void Transaksi
            </button>
        </div>

        <!-- Info -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <p class="text-xs text-gray-500">Kasir</p>
                    <p class="text-sm font-medium text-gray-800 mt-0.5">{{ transaction.user?.name || '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Status</p>
                    <p class="text-sm font-medium text-gray-800 mt-0.5">{{ statusLabel(transaction.status) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Total</p>
                    <p class="text-sm font-bold text-gray-900 mt-0.5">{{ formatCurrency(transaction.total_amount) }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Kembalian</p>
                    <p class="text-sm font-medium text-gray-800 mt-0.5">{{ formatCurrency(transaction.change_amount) }}</p>
                </div>
            </div>
            <div v-if="transaction.notes" class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-xs text-gray-500">Catatan</p>
                <p class="text-sm text-gray-700 mt-0.5">{{ transaction.notes }}</p>
            </div>
        </div>

        <!-- Items -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Item Transaksi</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left py-2.5 px-4 text-gray-500 font-medium">Varian</th>
                            <th class="text-right py-2.5 px-4 text-gray-500 font-medium">Harga Satuan</th>
                            <th class="text-right py-2.5 px-4 text-gray-500 font-medium">Qty</th>
                            <th class="text-right py-2.5 px-4 text-gray-500 font-medium">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="item in transaction.items" :key="item.id">
                            <tr class="border-b border-gray-50">
                                <td class="py-2.5 px-4">
                                    <span class="font-medium text-gray-800">{{ item.variant_name }}</span>
                                </td>
                                <td class="py-2.5 px-4 text-right text-gray-600">{{ formatCurrency(item.unit_price) }}</td>
                                <td class="py-2.5 px-4 text-right text-gray-600">{{ item.qty }}</td>
                                <td class="py-2.5 px-4 text-right font-semibold text-gray-900">{{ formatCurrency(item.subtotal) }}</td>
                            </tr>
                            <!-- Modifiers -->
                            <tr v-for="mod in item.modifiers" :key="mod.id" class="bg-gray-50/50">
                                <td class="py-1.5 px-4 pl-8 text-xs text-gray-500">
                                    + {{ mod.modifier_name }}
                                </td>
                                <td class="py-1.5 px-4 text-right text-xs text-gray-400">
                                    {{ mod.extra_price > 0 ? formatCurrency(mod.extra_price) : 'Gratis' }}
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 bg-gray-50">
                            <td colspan="3" class="py-3 px-4 text-right font-semibold text-gray-700">Total</td>
                            <td class="py-3 px-4 text-right font-bold text-gray-900">{{ formatCurrency(transaction.total_amount) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Payments -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Pembayaran</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left py-2.5 px-4 text-gray-500 font-medium">Metode</th>
                            <th class="text-right py-2.5 px-4 text-gray-500 font-medium">Nominal</th>
                            <th class="text-left py-2.5 px-4 text-gray-500 font-medium">Referensi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="pay in transaction.payments" :key="pay.id" class="border-b border-gray-50">
                            <td class="py-2.5 px-4">
                                <span class="font-medium text-gray-800">{{ pay.payment_method?.name || '-' }}</span>
                                <span
                                    v-if="pay.payment_method?.type"
                                    class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium"
                                    :class="{
                                        'bg-green-100 text-green-800': pay.payment_method.type === 'cash',
                                        'bg-blue-100 text-blue-800': pay.payment_method.type === 'qris_static',
                                        'bg-purple-100 text-purple-800': pay.payment_method.type === 'bank_transfer',
                                    }"
                                >
                                    {{ pay.payment_method.type === 'cash' ? 'Tunai' : pay.payment_method.type === 'qris_static' ? 'QRIS' : 'Transfer' }}
                                </span>
                            </td>
                            <td class="py-2.5 px-4 text-right font-semibold text-gray-900">{{ formatCurrency(pay.amount) }}</td>
                            <td class="py-2.5 px-4 text-gray-500">{{ pay.reference_code || '-' }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 bg-gray-50">
                            <td class="py-2.5 px-4 text-right font-medium text-gray-600">Kembalian</td>
                            <td class="py-2.5 px-4 text-right font-bold text-gray-900">{{ formatCurrency(transaction.change_amount) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Void Confirmation Dialog -->
    <ConfirmDialog
        :show="showVoidDialog"
        title="Void Transaksi"
        :message="`Apakah Anda yakin ingin membatalkan transaksi ${transaction.code}? Stok akan dikembalikan.`"
        confirm-text="Ya, Void"
        variant="danger"
        @confirm="doVoid"
        @cancel="showVoidDialog = false"
    />
</template>
