<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import ModifierModal from '@/Components/ModifierModal.vue';
import PaymentModal from '@/Components/PaymentModal.vue';
import SkeletonText from '@/Components/Skeleton/SkeletonText.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    transaction: { type: Object, default: null },
    products: { type: Array, default: null },
    paymentMethods: { type: Array, default: () => [] },
    // Owner mengedit transaksi di luar shift/hari berjalan → tampilkan peringatan laci kas.
    isPastRecord: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const formatCurrency = (value) => 'Rp ' + Number(value).toLocaleString('id-ID');

// --- Cart state (pre-fill dari transaksi) ---
const cart = ref([]);
const payments = ref([]);
const notes = ref('');
const reason = ref('');
const processing = ref(false);
const errorMessage = ref('');

let lineSeq = 0;

const prefill = () => {
    errorMessage.value = '';
    reason.value = '';
    notes.value = props.transaction?.notes || '';

    cart.value = (props.transaction?.items || []).map((item) => ({
        key: ++lineSeq,
        variant_id: item.product_variant_id,
        variant_name: item.variant_name,
        unit_price: Number(item.unit_price),
        qty: item.qty,
        notes: item.notes || '',
        modifiers: (item.modifiers || []).map((m) => ({
            id: m.modifier_id,
            name: m.modifier_name,
            extra_price: Number(m.extra_price),
        })),
    }));

    payments.value = (props.transaction?.payments || []).map((p) => ({
        payment_method_id: p.payment_method_id,
        amount: Number(p.amount),
        reference_code: p.reference_code || null,
    }));
};

watch(() => props.show, (val) => {
    if (val) {
        prefill();
    }
});

// --- Totals ---
const lineSubtotal = (line) => {
    const modExtra = line.modifiers.reduce((sum, m) => sum + Number(m.extra_price), 0);
    return (line.unit_price + modExtra) * line.qty;
};

const cartTotal = computed(() => cart.value.reduce((sum, l) => sum + lineSubtotal(l), 0));
const totalPaid = computed(() => payments.value.reduce((sum, p) => sum + Number(p.amount || 0), 0));
const changeAmount = computed(() => Math.max(0, totalPaid.value - cartTotal.value));

const originalTotal = computed(() => Number(props.transaction?.total_amount || 0));

// --- Cart mutation ---
const incQty = (line) => { line.qty++; };
const decQty = (line) => { if (line.qty > 1) line.qty--; };
const removeLine = (idx) => { cart.value.splice(idx, 1); };

const modifierKey = (line) =>
    line.variant_id + ':' + line.modifiers.map((m) => m.id).sort((a, b) => a - b).join(',');

const addFromModifier = (payload) => {
    // payload dari ModifierModal: { variant_id, variant_name, unit_price, qty, modifiers }
    const newLine = {
        key: ++lineSeq,
        variant_id: payload.variant_id,
        variant_name: payload.variant_name,
        unit_price: Number(payload.unit_price),
        qty: payload.qty || 1,
        notes: '',
        modifiers: payload.modifiers.map((m) => ({ id: m.id, name: m.name, extra_price: Number(m.extra_price) })),
    };
    // Gabung baris identik (varian + modifier sama)
    const existing = cart.value.find((l) => modifierKey(l) === modifierKey(newLine));
    if (existing) {
        existing.qty += newLine.qty;
    } else {
        cart.value.push(newLine);
    }
};

// --- Product picker + ModifierModal ---
const search = ref('');
const showModifier = ref(false);
const pickedProduct = ref(null);

const filteredProducts = computed(() => {
    if (!props.products) return [];
    const q = search.value.trim().toLowerCase();
    if (!q) return props.products;
    return props.products.filter((p) => p.name.toLowerCase().includes(q));
});

const pickProduct = (product) => {
    pickedProduct.value = product;
    showModifier.value = true;
};

const onModifierConfirm = (payload) => {
    addFromModifier(payload);
};

// --- PaymentModal ---
const showPayment = ref(false);
const onPaymentConfirm = (data) => {
    payments.value = data;
};

const paymentSummary = computed(() => {
    if (payments.value.length === 0) return 'Belum diatur';
    return payments.value
        .map((p) => {
            const method = props.paymentMethods.find((m) => m.id === p.payment_method_id);
            return `${method?.name || 'Metode'} ${formatCurrency(p.amount)}`;
        })
        .join(', ');
});

// --- Validation & submit ---
const canSubmit = computed(() =>
    cart.value.length > 0 &&
    payments.value.length > 0 &&
    totalPaid.value >= cartTotal.value &&
    !processing.value
);

const submit = () => {
    if (!canSubmit.value) return;
    errorMessage.value = '';
    processing.value = true;

    const p = {
        items: cart.value.map((l) => ({
            variant_id: l.variant_id,
            qty: l.qty,
            notes: l.notes || null,
            modifiers: l.modifiers.map((m) => ({ id: m.id })),
        })),
        payments: payments.value.map((pay) => ({
            payment_method_id: pay.payment_method_id,
            amount: Number(pay.amount),
            reference_code: pay.reference_code || null,
        })),
        notes: notes.value || null,
        reason: reason.value || null,
    };

    router.put(`/cashier/transactions/${props.transaction.id}`, p, {
        preserveScroll: true,
        onSuccess: () => {
            // Controller mengembalikan back() dengan flash error untuk kegagalan bisnis (stok, dsb).
            const flashError = usePage().props.flash?.error;
            if (flashError) {
                errorMessage.value = flashError;
            } else {
                emit('close');
            }
        },
        onError: (errors) => {
            errorMessage.value = Object.values(errors)[0] || 'Gagal menyimpan perubahan.';
        },
        onFinish: () => {
            processing.value = false;
        },
    });
};
</script>

<template>
    <Modal :show="show" title="Edit Transaksi" max-width="max-w-2xl" @close="$emit('close')">
        <!-- Katalog edit ditunda di server ([BL-037]); kerangkanya memakai
             komponen bersama, bukan blok animate-pulse sendiri. -->
        <div v-if="!products" class="py-10" role="status" aria-busy="true">
            <SkeletonText :lines="2" line-class="h-4" last-width="w-2/3" class="mx-auto max-w-xs" />
            <p class="mt-3 text-center text-sm text-gray-400">Memuat katalog…</p>
        </div>

        <div v-else class="space-y-5">
            <!-- Peringatan transaksi lampau -->
            <div v-if="isPastRecord" class="flex gap-2 p-3 rounded-lg bg-warning/10 border border-warning/30 text-sm text-warning-foreground">
                <svg class="w-5 h-5 shrink-0 text-warning-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Mengedit transaksi lampau akan mengubah laporan &amp; rekap laci kas periode itu.</span>
            </div>

            <!-- Error bisnis -->
            <div v-if="errorMessage" class="p-3 rounded-lg bg-destructive/10 border border-destructive/30 text-sm text-destructive">
                {{ errorMessage }}
            </div>

            <!-- Keranjang -->
            <div>
                <h4 class="text-sm font-semibold text-gray-700 mb-2">Item</h4>
                <div v-if="cart.length === 0" class="text-sm text-gray-400 py-3 text-center border border-dashed border-gray-200 rounded-lg">
                    Belum ada item. Tambahkan minimal 1.
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="(line, idx) in cart"
                        :key="line.key"
                        class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg"
                    >
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ line.variant_name }}</p>
                            <p v-if="line.modifiers.length" class="text-xs text-gray-500 truncate">
                                {{ line.modifiers.map((m) => m.name).join(', ') }}
                            </p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ formatCurrency(lineSubtotal(line)) }}</p>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="decQty(line)" class="w-7 h-7 rounded-md bg-gray-100 text-gray-600 hover:bg-gray-200 font-semibold">−</button>
                            <span class="w-6 text-center text-sm font-medium">{{ line.qty }}</span>
                            <button type="button" @click="incQty(line)" class="w-7 h-7 rounded-md bg-gray-100 text-gray-600 hover:bg-gray-200 font-semibold">+</button>
                        </div>
                        <button type="button" @click="removeLine(idx)" class="text-gray-300 hover:text-destructive mt-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tambah item -->
            <div>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari produk untuk ditambahkan…"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                />
                <div v-if="search.trim()" class="mt-2 max-h-40 overflow-y-auto border border-gray-100 rounded-lg divide-y divide-gray-50">
                    <button
                        v-for="product in filteredProducts"
                        :key="product.id"
                        type="button"
                        @click="pickProduct(product)"
                        class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                    >
                        {{ product.name }}
                    </button>
                    <p v-if="filteredProducts.length === 0" class="px-3 py-2 text-sm text-gray-400">Tidak ada produk.</p>
                </div>
            </div>

            <!-- Pembayaran -->
            <div>
                <h4 class="text-sm font-semibold text-gray-700 mb-2">Pembayaran</h4>
                <button
                    type="button"
                    @click="showPayment = true"
                    class="w-full flex items-center justify-between px-3 py-2.5 border border-gray-300 rounded-lg text-sm hover:border-primary transition"
                >
                    <span class="text-gray-600 truncate">{{ paymentSummary }}</span>
                    <span class="text-primary font-medium shrink-0 ml-2">Atur</span>
                </button>
                <p v-if="totalPaid < cartTotal" class="mt-1 text-xs text-destructive">
                    Total bayar kurang {{ formatCurrency(cartTotal - totalPaid) }}
                </p>
            </div>

            <!-- Alasan -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Alasan edit <span class="text-xs text-gray-400 font-normal">(opsional)</span></label>
                <input
                    v-model="reason"
                    type="text"
                    placeholder="mis. salah input qty"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                />
            </div>

            <!-- Ringkasan perubahan -->
            <div class="border-t border-gray-100 pt-3 space-y-1 text-sm">
                <div class="flex justify-between text-gray-500">
                    <span>Total sebelumnya</span>
                    <span>{{ formatCurrency(originalTotal) }}</span>
                </div>
                <div class="flex justify-between font-semibold text-gray-900">
                    <span>Total baru</span>
                    <span>{{ formatCurrency(cartTotal) }}</span>
                </div>
                <div v-if="changeAmount > 0" class="flex justify-between text-gray-500">
                    <span>Kembalian</span>
                    <span>{{ formatCurrency(changeAmount) }}</span>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex gap-3">
                <button
                    type="button"
                    @click="$emit('close')"
                    class="flex-1 py-2.5 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition"
                >
                    Batal
                </button>
                <button
                    type="button"
                    @click="submit"
                    :disabled="!canSubmit"
                    class="flex-1 py-2.5 bg-success text-success-foreground font-semibold rounded-lg hover:bg-success/90 transition disabled:opacity-40 disabled:cursor-not-allowed"
                >
                    {{ processing ? 'Menyimpan…' : 'Simpan Perubahan' }}
                </button>
            </div>
        </template>
    </Modal>

    <!-- Modal pilih varian + modifier (reuse) -->
    <ModifierModal
        :show="showModifier"
        :product="pickedProduct"
        @confirm="onModifierConfirm"
        @close="showModifier = false"
    />

    <!-- Modal atur pembayaran (reuse) -->
    <PaymentModal
        :show="showPayment"
        :total-amount="cartTotal"
        :payment-methods="paymentMethods"
        @confirm="onPaymentConfirm"
        @close="showPayment = false"
    />
</template>
