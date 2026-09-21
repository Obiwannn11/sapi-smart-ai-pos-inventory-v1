<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import BrandMark from '@/Components/BrandMark.vue';
import { useThermalPrinter } from '@/composables/useThermalPrinter';
import PrinterSetupModal from '@/Components/PrinterSetupModal.vue';
import { BUSINESS_TZ } from '@/support/date';
import { itemDiscount, receiptSavings } from '@/support/discount';
import { receiptTotals, serviceChargeLine, taxLine } from '@/support/tax';

const props = defineProps({
    show: { type: Boolean, default: false },
    transaction: Object,
    // Dua-duanya BOLEH dititipkan pemanggil, tapi tak satu pun wajib: dari tiga
    // pemanggil, hanya CashierTopbar yang meneruskan namanya. Cadangan `null`
    // di sini, bukan 'SAPI POS', supaya keduanya bisa jatuh ke prop bersama di
    // bawah — dengan default lama, struk yang dibuka dari Riwayat mencetak
    // "SAPI POS" di atas logo Kopi Nusantara, nama produk di kepala struk orang.
    tenantName: { type: String, default: null },
    tenantLogo: { type: String, default: null },
});

const emit = defineEmits(['close']);

const page = usePage();
const brandName = computed(() => props.tenantName || page.props.auth?.tenant?.name || 'SAPI POS');
const logoSrc = computed(() => props.tenantLogo ?? page.props.auth?.tenant?.logo_url ?? null);

const printer = useThermalPrinter();
const showPrinterSetup = ref(false);
const thermalBusy = ref(false);
const thermalError = ref('');

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('id-ID', {
        timeZone: BUSINESS_TZ,
        day: '2-digit',
        month: 'long',
        year: 'numeric',
    });
};

const formatTime = (date) => {
    return new Date(date).toLocaleTimeString('id-ID', {
        timeZone: BUSINESS_TZ,
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Subtotal datang dari kolom yang dibekukan server, BUKAN dari penjumlahan
// baris item ([BL-065]). Di mode pajak inclusive `unit_price` sudah
// mengandung pajak, sehingga jumlah baris adalah TOTAL — menjumlahkannya
// sebagai subtotal akan mencetak struk yang tidak bisa dijumlahkan ulang.
const totals = computed(() => receiptTotals(props.transaction));
const subtotal = computed(() => totals.value.subtotal);
const tax = computed(() => taxLine(totals.value));
const serviceCharge = computed(() => serviceChargeLine(totals.value));

// Potongan dibaca dari helper yang sama dengan struk termal ([BL-103] butir
// 2a), supaya layar dan kertas tidak pernah menulisnya dengan cara berbeda.
const receiptLines = computed(() => (props.transaction?.items || []).map((item) => ({
    item,
    discount: itemDiscount(item),
})));
const savings = computed(() => receiptSavings(props.transaction));

const totalPaid = computed(() => {
    if (!props.transaction?.payments) return 0;
    return props.transaction.payments.reduce((sum, p) => sum + Number(p.amount), 0);
});

const close = () => emit('close');

const printReceipt = () => window.print();

// Saat printer thermal tersedia, tombol ini jadi jalan cadangan — jadi ia
// menyebut JALANNYA. Saat tidak ada, ia satu-satunya cara mencetak, dan yang
// perlu disebut adalah hasilnya.
const browserPrintLabel = computed(() => (canThermal.value ? 'Cetak lewat Browser' : 'Cetak Struk'));

// Direct thermal printing (Web Bluetooth/USB). Falls back to the setup modal
// when no printer is configured yet, and to window.print() on unsupported browsers.
const canThermal = computed(() => printer.supports.bluetooth || printer.supports.usb);

const printThermal = async () => {
    if (!printer.isConfigured.value) {
        showPrinterSetup.value = true;
        return;
    }
    thermalBusy.value = true;
    thermalError.value = '';
    try {
        await printer.printReceipt(props.transaction, { tenantName: brandName.value });
    } catch (err) {
        thermalError.value = err?.message || 'Gagal mencetak. Buka pengaturan printer.';
    } finally {
        thermalBusy.value = false;
    }
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

                <!-- Modal wrapper -->
                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-xs max-h-[92vh] flex flex-col print:max-w-none print:shadow-none print:rounded-none print:fixed print:inset-0">

                    <!-- Scrollable receipt content -->
                    <div class="flex-1 overflow-y-auto print:overflow-visible" id="receipt-content">
                        <div class="p-5 font-mono text-xs text-gray-800 space-y-0">

                            <!-- ===== HEADER ===== -->
                            <div class="text-center pb-3 border-b border-dashed border-gray-400">
                                <!--
                                    Logo hanya dirender bila ada. Kotak
                                    berlatar warna merek milik BrandMark tidak
                                    punya tempat di kepala struk — kertas
                                    thermal hitam-putih akan mencetaknya sebagai
                                    balok gelap dengan satu huruf di tengah,
                                    yang justru lebih buruk daripada tanpa
                                    lambang sama sekali. Jadi di sini cadangan
                                    inisialnya sengaja dilewati: nama tokonya
                                    sudah tercetak tepat di bawah.
                                -->
                                <BrandMark
                                    v-if="logoSrc"
                                    :src="logoSrc"
                                    :name="brandName"
                                    class="w-14 h-14 mx-auto mb-2"
                                />
                                <p class="text-base font-bold uppercase tracking-widest">{{ brandName }}</p>

                                <!--
                                    Nomor antrian hanya ada saat papan dapur
                                    hidup ATAU mode identitas "kode panggil"
                                    dipilih ([BL-026]); outlet yang tidak
                                    memakai keduanya tidak punya nilainya, jadi
                                    struknya tidak berubah.
                                -->
                                <div v-if="transaction.queue_number" class="mt-2 pt-2 border-t border-dashed border-gray-400">
                                    <p class="text-[10px] uppercase tracking-widest text-gray-500">No. Antrian</p>
                                    <p class="text-3xl font-bold leading-tight">{{ transaction.queue_number }}</p>
                                </div>
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
                                <div v-if="transaction.table_number" class="flex justify-between">
                                    <span class="text-gray-500">Meja</span>
                                    <span class="font-medium">{{ transaction.table_number }}</span>
                                </div>
                            </div>

                            <!-- ===== ITEMS ===== -->
                            <div class="py-2.5 border-b border-dashed border-gray-400 space-y-2">
                                <div v-for="{ item, discount } in receiptLines" :key="item.id">
                                    <!-- Nama + jumlah baris. Baris berdiskon memakai
                                         jumlah SEBELUM dipotong, lalu potongannya di
                                         bawah — kolom kanan dibaca menurun. -->
                                    <div class="flex justify-between items-start gap-2">
                                        <span class="flex-1 font-medium leading-tight">{{ item.variant_name }}</span>
                                        <span class="shrink-0 font-semibold">{{ formatCurrency(discount ? discount.grossSubtotal : item.subtotal) }}</span>
                                    </div>
                                    <!-- Qty × harga normal (berdiskon) atau harga yang dibayar -->
                                    <div class="flex justify-between text-gray-500 pl-2">
                                        <span>{{ item.qty }} × {{ formatCurrency(discount ? discount.originalUnitPrice : item.unit_price) }}</span>
                                    </div>
                                    <div v-if="discount" class="flex justify-between pl-2 text-gray-700">
                                        <span>{{ discount.label }}</span>
                                        <span>-{{ formatCurrency(discount.amount) }}</span>
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
                                <!-- Biaya layanan selalu baris yang menaikkan
                                     total ([BL-097]): ia tidak punya mode
                                     inclusive. Letaknya sebelum pajak karena
                                     pajak dipungut ATAS jumlah keduanya. -->
                                <div v-if="serviceCharge" class="flex justify-between">
                                    <span class="text-gray-500">{{ serviceCharge.text }}</span>
                                    <span>{{ formatCurrency(serviceCharge.amount) }}</span>
                                </div>
                                <!-- Mode exclusive: pajak baris tersendiri yang menaikkan total -->
                                <div v-if="tax && tax.inline" class="flex justify-between">
                                    <span class="text-gray-500">{{ tax.text }}</span>
                                    <span>{{ formatCurrency(tax.amount) }}</span>
                                </div>
                                <div class="flex justify-between font-bold text-sm border-t border-gray-300 pt-1 mt-1">
                                    <span>TOTAL</span>
                                    <span>{{ formatCurrency(transaction.total_amount) }}</span>
                                </div>
                                <!-- Mode inclusive: total tidak berubah, jadi yang perlu
                                     dinyatakan adalah bahwa ia sudah mengandung pajak -->
                                <div v-if="tax && !tax.inline" class="flex justify-between text-gray-500">
                                    <span>{{ tax.text }}</span>
                                    <span>{{ formatCurrency(tax.amount) }}</span>
                                </div>
                                <!-- Keterangan, bukan baris hitungan: subtotal
                                     sudah bersih dari potongan. -->
                                <div v-if="savings > 0" class="flex justify-between text-gray-500">
                                    <span>Anda hemat</span>
                                    <span>{{ formatCurrency(savings) }}</span>
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
                                    <span>Kembalian</span>
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
                                <p class="text-gray-400 text-[10px]">Simpan struk sebagai bukti pembayaran</p>
                            </div>

                        </div>
                    </div>

                    <!-- Action buttons (hidden on print) -->
                    <div class="px-5 py-4 border-t border-gray-200 print:hidden shrink-0 space-y-3">
                        <!-- Thermal error -->
                        <p v-if="thermalError" class="text-xs text-destructive bg-destructive/10 rounded-lg px-3 py-2">
                            {{ thermalError }}
                        </p>

                        <!-- Primary: direct thermal print (Chromium only) -->
                        <div v-if="canThermal" class="flex gap-3 items-center">
                            <button
                                @click="printThermal"
                                :disabled="thermalBusy"
                                class="flex-1 py-2.5 bg-primary text-primary-foreground font-semibold rounded-lg hover:bg-primary/90 transition flex items-center justify-center gap-2 text-sm disabled:opacity-60"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                {{ thermalBusy ? 'Mencetak…' : 'Cetak Struk' }}
                            </button>
                            <button
                                @click="showPrinterSetup = true"
                                class="shrink-0 p-2.5 bg-white border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition"
                                title="Pengaturan printer"
                                aria-label="Pengaturan printer"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </button>
                        </div>

                        <!-- Secondary row: browser print (fallback) + close -->
                        <div class="flex gap-3">
                            <button
                                @click="close"
                                class="flex-1 py-2.5 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition text-sm"
                            >
                                Tutup
                            </button>
                            <button
                                @click="printReceipt"
                                :class="[
                                    'flex-1 py-2.5 font-semibold rounded-lg transition flex items-center justify-center gap-2 text-sm',
                                    canThermal ? 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50' : 'bg-primary text-primary-foreground hover:bg-primary/90',
                                ]"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                                </svg>
                                {{ browserPrintLabel }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>

        <PrinterSetupModal :show="showPrinterSetup" @close="showPrinterSetup = false" />
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
