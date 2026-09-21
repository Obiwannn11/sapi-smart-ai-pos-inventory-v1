<script setup>
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import TransactionEditModal from '@/Components/TransactionEditModal.vue';
import { BUSINESS_TZ, businessDateString, businessToday } from '@/support/date';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    transaction: Object,
    products: { type: Array, default: null },
    paymentMethods: { type: Array, default: () => [] },
});

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatDateTime = (datetime) => {
    return new Date(datetime).toLocaleString('id-ID', {
        timeZone: BUSINESS_TZ,
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const statusBadge = (status) => {
    switch (status) {
        case 'completed': return 'bg-success/10 text-success';
        case 'voided': return 'bg-destructive/10 text-destructive';
        case 'pending': return 'bg-warning/10 text-warning-foreground';
        case 'unsettled': return 'bg-destructive/10 text-destructive ring-1 ring-destructive/30';
        default: return 'bg-gray-100 text-gray-800';
    }
};

const statusLabel = (status) => {
    switch (status) {
        case 'completed': return 'Selesai';
        case 'voided': return 'Void';
        case 'pending': return 'Pending';
        case 'unsettled': return 'Kas Negatif';
        default: return status;
    }
};

// Void logic
const showVoidDialog = ref(false);
const voidForm = useForm({});

const isToday = () => businessDateString(props.transaction.created_at) === businessToday();

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

// Edit logic — owner boleh edit transaksi completed kapan saja.
const showEditModal = ref(false);
const canEdit = () => props.transaction.status === 'completed';

// --- Kas negatif ([BL-031]) ---
//
// Tagihan terbuka yang lewat 24 jam berhenti jadi tagihan hidup. Halaman ini
// adalah SATU-SATUNYA tempat ia bisa dibereskan, dan dua jalannya menentukan
// stok: pelunasan membiarkan stok apa adanya (barangnya memang terjual),
// penghapusan mengembalikannya (penjualannya dianggap tidak pernah terjadi).
const isUnsettled = () => props.transaction.status === 'unsettled';

const settleMethodId = ref(null);
const settleForm = useForm({ payments: [] });
const showWriteOffDialog = ref(false);
const writeOffForm = useForm({});

const doSettle = () => {
    if (!settleMethodId.value) return;

    settleForm.transform(() => ({
        payments: [{
            payment_method_id: settleMethodId.value,
            // Persis sebesar tagihan: pelunasan terlambat bukan tempat
            // menawar, dan kurang-bayar sudah ditolak server.
            amount: Number(props.transaction.total_amount),
        }],
    })).post(`/owner/transactions/${props.transaction.id}/settle-late`, {
        preserveScroll: true,
    });
};

const doWriteOff = () => {
    writeOffForm.post(`/owner/transactions/${props.transaction.id}/write-off`, {
        preserveScroll: true,
        onSuccess: () => {
            showWriteOffDialog.value = false;
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

            <div class="flex items-center gap-2">
                <button
                    v-if="canEdit()"
                    @click="showEditModal = true"
                    class="px-4 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors"
                >
                    Edit Transaksi
                </button>
                <button
                    v-if="canVoid()"
                    @click="showVoidDialog = true"
                    class="px-4 py-2 bg-destructive text-destructive-foreground text-sm font-medium rounded-lg hover:bg-destructive/90 transition-colors"
                    :disabled="voidForm.processing"
                >
                    Void Transaksi
                </button>
            </div>
        </div>

        <!-- Kas negatif: tagihan terbuka yang lewat 24 jam ([BL-031]).
             Ditaruh di atas segalanya karena inilah satu-satunya hal yang
             menuntut keputusan di halaman ini. -->
        <div v-if="isUnsettled()" class="bg-destructive/5 border border-destructive/30 rounded-xl p-5">
            <h3 class="text-sm font-semibold text-destructive">Kas Negatif</h3>
            <p class="mt-1 text-sm text-gray-700">
                Tagihan ini lewat 24 jam sejak penjualannya dan berhenti bisa ditagih kasir. Barangnya sudah keluar, jadi stoknya
                <strong>tidak</strong> dipulihkan sampai Anda memutuskan tagihan ini memang tak akan pernah dibayar.
            </p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <!-- Jalan pertama: pelanggannya akhirnya membayar. -->
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <p class="text-sm font-medium text-gray-800">Tagihan dilunasi</p>
                    <p class="mt-1 text-xs text-gray-500">
                        Sebesar {{ formatCurrency(transaction.total_amount) }}. Stok tetap seperti adanya — penjualannya memang terjadi.
                        Uangnya tidak masuk laci kasir mana pun.
                    </p>
                    <select
                        v-model="settleMethodId"
                        class="mt-3 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm"
                    >
                        <option :value="null" disabled>Pilih metode pembayaran</option>
                        <option v-for="pm in paymentMethods" :key="pm.id" :value="pm.id">{{ pm.name }}</option>
                    </select>
                    <button
                        @click="doSettle"
                        :disabled="!settleMethodId || settleForm.processing"
                        class="mt-3 w-full px-4 py-2 bg-success text-success-foreground text-sm font-medium rounded-lg hover:bg-success/90 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                    >
                        Catat Pelunasan
                    </button>
                </div>

                <!-- Jalan kedua: dan HANYA di sini stok kembali. -->
                <div class="rounded-lg border border-gray-200 bg-white p-4">
                    <p class="text-sm font-medium text-gray-800">Tak akan pernah dibayar</p>
                    <p class="mt-1 text-xs text-gray-500">
                        Tagihan dihapuskan dan stoknya dikembalikan. Pakai ini hanya bila barangnya kembali atau penjualannya
                        dianggap tidak pernah terjadi.
                    </p>
                    <button
                        @click="showWriteOffDialog = true"
                        :disabled="writeOffForm.processing"
                        class="mt-3 w-full px-4 py-2 bg-destructive text-destructive-foreground text-sm font-medium rounded-lg hover:bg-destructive/90 transition-colors disabled:opacity-40"
                    >
                        Hapuskan Tagihan
                    </button>
                </div>
            </div>
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
                                        'bg-success/10 text-success': pay.payment_method.type === 'cash',
                                        'bg-primary/10 text-primary': pay.payment_method.type === 'qris_static',
                                        'bg-secondary text-secondary-foreground': pay.payment_method.type === 'bank_transfer',
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
        <!-- Riwayat Edit -->
        <div v-if="transaction.edits && transaction.edits.length" class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">Riwayat Edit</h3>
            </div>
            <ul class="divide-y divide-gray-50">
                <li v-for="edit in transaction.edits" :key="edit.id" class="px-5 py-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="font-medium text-gray-800">{{ edit.user?.name || 'Pengguna' }}</span>
                        <span class="text-xs text-gray-400">{{ formatDateTime(edit.created_at) }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Total {{ formatCurrency(edit.before?.total_amount || 0) }} → {{ formatCurrency(edit.after?.total_amount || 0) }}
                        <span v-if="edit.reason"> — {{ edit.reason }}</span>
                    </p>
                </li>
            </ul>
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

    <ConfirmDialog
        :show="showWriteOffDialog"
        title="Hapuskan Tagihan"
        :message="`Hapuskan tagihan ${transaction.code}? Stok akan dikembalikan dan kas negatifnya ditutup. Tindakan ini tidak bisa dibatalkan.`"
        confirm-text="Ya, Hapuskan"
        variant="danger"
        @confirm="doWriteOff"
        @cancel="showWriteOffDialog = false"
    />

    <!-- Edit Transaksi Modal -->
    <TransactionEditModal
        :show="showEditModal"
        :transaction="transaction"
        :products="products"
        :payment-methods="paymentMethods"
        :is-past-record="!isToday()"
        @close="showEditModal = false"
    />
</template>
