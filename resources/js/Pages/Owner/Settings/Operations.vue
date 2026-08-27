<script setup>
import { computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    features: Object,
    orderIdentityModes: { type: Object, default: () => ({}) },
    featureWarnings: Object,
    tax: { type: Object, default: () => ({}) },
    taxModes: { type: Object, default: () => ({}) },
});

const form = useForm({
    kitchen_queue_enabled: props.features.kitchen_queue_enabled,
    self_order_enabled:    props.features.self_order_enabled,
    ai_enabled:            props.features.ai_enabled,
    payment_proof_enabled: props.features.payment_proof_enabled,
    upsell_mandatory:      props.features.upsell_mandatory,
    order_identity_mode:   props.features.order_identity_mode ?? 'none',
    min_margin_percent:    props.features.min_margin_percent ?? 10,
    cash_payout_approval_threshold: props.features.cash_payout_approval_threshold ?? 50000,
});

// Peringatan hanya relevan saat owner sedang MEMATIKAN fitur yang masih punya
// pekerjaan berjalan. Menampilkannya saat fitur sudah mati sejak awal hanya
// jadi kebisingan.
const warnSelfOrderOff = computed(
    () => props.features.self_order_enabled
        && !form.self_order_enabled
        && props.featureWarnings.active_self_orders > 0,
);
const warnAiOff = computed(
    () => props.features.ai_enabled
        && !form.ai_enabled
        && props.featureWarnings.pending_analyses > 0,
);

// Apa yang sebenarnya berubah di layar kasir untuk tiap mode. Ditulis sebagai
// akibat yang terlihat, bukan nama fiturnya — owner memilih dari sini.
const identityModeHint = computed(() => ({
    none: 'Kasir tidak ditanya apa-apa. Tagihan terbuka tetap boleh diberi nama supaya bisa dikenali saat ditagih.',
    name: 'Sebelum menyimpan, kasir mengisi nama pelanggan. Nama ikut tercetak di struk dan tampil di riwayat.',
    table: 'Sebelum menyimpan, kasir mengisi nomor meja lewat papan angka. Nomor ikut tercetak di struk dan tampil di riwayat.',
    code: 'Sistem memberi nomor panggil berurutan tiap hari — kasir tidak mengetik apa pun. Nomor dicetak besar di struk untuk dipanggil saat pesanan siap.',
}[form.order_identity_mode] ?? ''));

const submit = () => {
    form.patch('/owner/settings/operations', { preserveScroll: true });
};

// --- Pajak ([BL-065]) ---
//
// Form dan endpoint TERPISAH dari yang di atas: dua field di bawah terkunci
// setelah penjualan berpajak pertama, dan penguncian yang berbagi request
// dengan sakelar fitur lain akan tergoda dilonggarkan supaya form lain tetap
// bisa menyimpan.
const taxForm = useForm({
    tax_enabled: props.tax.tax_enabled ?? false,
    tax_mode:    props.tax.tax_mode ?? 'exclusive',
    tax_rate:    props.tax.tax_rate ?? 0,
    tax_label:   props.tax.tax_label ?? '',
});

const taxLocked = computed(() => props.tax.locked === true);

// Contoh dihitung dari angka bulat yang mudah dicek ulang di kepala. Yang
// ditunjukkan bukan besar pajaknya, melainkan SIAPA yang menanggungnya —
// itulah satu-satunya beda nyata antara kedua mode.
const taxExample = computed(() => {
    const rate = Number(taxForm.tax_rate) || 0;
    const price = 10000;

    if (rate <= 0) {
        return null;
    }

    if (taxForm.tax_mode === 'inclusive') {
        const tax = Math.round(price * rate / (100 + rate));

        return {
            paid: price,
            tax,
            income: price - tax,
        };
    }

    const tax = Math.round(price * rate / 100);

    return {
        paid: price + tax,
        tax,
        income: price,
    };
});

const rupiah = (value) => Number(value || 0).toLocaleString('id-ID');

const submitTax = () => {
    taxForm.patch('/owner/settings/operations/tax', { preserveScroll: true });
};
</script>

<template>
    <Head title="Cara Kerja Sistem" />

    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Cara Kerja Sistem</h1>
            <p class="text-sm text-gray-500 mt-1">Modul yang menyala, aturan kerja kasir, dan cara pesanan dikenali</p>
        </div>

        <SettingsNav current="operations" />

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <form @submit.prevent="submit" class="space-y-5">
                <!-- Mode & Fitur Outlet -->
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Mode &amp; Fitur Outlet</h2>
                    <p class="text-xs text-gray-500 mt-0.5 mb-4">
                        Menyalakan atau mematikan kapabilitas untuk outlet ini. Berlaku di semua pintu masuk sekaligus — web, aplikasi kasir, dan integrasi.
                    </p>

                    <div class="space-y-3">
                        <label class="flex gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50">
                            <input v-model="form.kitchen_queue_enabled" type="checkbox" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary focus:ring-ring" />
                            <span class="text-sm">
                                <span class="font-medium text-gray-900 block">Antrian Dapur</span>
                                <span class="text-xs text-gray-500">
                                    Menampilkan papan urutan pesanan untuk operator yang merangkap masak dan kasir, plus nomor antrian di struk.
                                    Mode ini <strong>tidak aktif saat perangkat offline</strong> — kasir kembali ke alur biasa dan penjualan hasil sinkronisasi tidak menyusul masuk papan.
                                </span>
                            </span>
                        </label>

                        <label class="flex gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50">
                            <input v-model="form.self_order_enabled" type="checkbox" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary focus:ring-ring" />
                            <span class="text-sm">
                                <span class="font-medium text-gray-900 block">Self-Order</span>
                                <span class="text-xs text-gray-500">Pemesanan mandiri pelanggan lewat QR/Telegram beserta saran jualnya.</span>
                            </span>
                        </label>

                        <div v-if="warnSelfOrderOff" class="flex gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                            <svg class="w-4 h-4 shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z" />
                            </svg>
                            <span>
                                Masih ada <strong>{{ featureWarnings.active_self_orders }}</strong> pesanan mandiri yang belum selesai.
                                Mematikan fitur ini tidak membatalkannya, tapi pelanggan tidak bisa memesan lagi sampai dinyalakan kembali.
                            </span>
                        </div>

                        <label class="flex gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50">
                            <input v-model="form.ai_enabled" type="checkbox" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary focus:ring-ring" />
                            <span class="text-sm">
                                <span class="font-medium text-gray-900 block">AI Analysis &amp; MCP</span>
                                <span class="text-xs text-gray-500">
                                    Analisis AI di aplikasi sekaligus akses AI client eksternal lewat token MCP.
                                    Kunci API dan tokennya sendiri diatur di
                                    <a href="/owner/settings/integrations" class="text-primary hover:underline">Integrasi &amp; Kredensial</a>.
                                </span>
                            </span>
                        </label>

                        <label class="flex gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50">
                            <input v-model="form.payment_proof_enabled" type="checkbox" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary focus:ring-ring" />
                            <span class="text-sm">
                                <span class="font-medium text-gray-900 block">Foto Bukti Bayar Non-Tunai</span>
                                <span class="text-xs text-gray-500">
                                    Kasir memotret bukti QRIS/transfer sebelum penjualan ditutup, dan fotonya melekat pada pembayarannya.
                                    Berlaku untuk <strong>semua metode selain tunai</strong>; baris tunai tidak pernah diminta foto.
                                    Menyalakannya menambah satu langkah ke tiap penjualan non-tunai, jadi nyalakan hanya bila memang perlu bukti saat ada perselisihan.
                                    Penjualan <strong>offline selalu tunai</strong>, jadi aturan ini tidak pernah menghalangi kasir saat sinyal mati.
                                </span>
                            </span>
                        </label>

                        <div v-if="warnAiOff" class="flex gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                            <svg class="w-4 h-4 shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z" />
                            </svg>
                            <span>
                                Masih ada <strong>{{ featureWarnings.pending_analyses }}</strong> analisis yang mengantre.
                                Analisis itu akan gagal dengan keterangan fitur tidak aktif — kuota harian Anda tidak terpakai.
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Lantai margin ([BL-018]) -->
                <div class="pt-5 border-t border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Batas Untung Minimum</h2>
                    <p class="text-xs text-gray-500 mt-0.5 mb-4">
                        Lantai harga tiap barang, dihitung dari harga modalnya. Aturan diskon tidak akan pernah menurunkan harga di bawah lantai ini.
                    </p>

                    <div class="flex items-center gap-3">
                        <div class="relative w-32">
                            <input
                                v-model.number="form.min_margin_percent"
                                type="number"
                                min="0"
                                max="90"
                                step="0.5"
                                class="w-full pr-8 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                            />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">%</span>
                        </div>
                        <p class="text-xs text-gray-500">
                            Barang bermodal Rp 10.000 punya lantai
                            <strong>Rp {{ Math.ceil(10000 * (1 + (form.min_margin_percent || 0) / 100) / 500) * 500 }}</strong>.
                        </p>
                    </div>

                    <p v-if="form.errors.min_margin_percent" class="mt-1.5 text-xs text-destructive">
                        {{ form.errors.min_margin_percent }}
                    </p>

                    <p class="mt-3 text-xs text-gray-500 leading-relaxed">
                        Hanya <strong>Anda</strong> yang bisa menjual di bawah lantai ini, dan setiap kali wajib menyertakan alasan yang ikut tercatat pada penjualannya.
                        Kasir tidak punya jalan ke sana sama sekali.
                    </p>
                </div>

                <!-- Ambang persetujuan uang keluar laci ([BL-087]) -->
                <div class="pt-5 border-t border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Batas Uang Keluar Tanpa Persetujuan</h2>
                    <p class="text-xs text-gray-500 mt-0.5 mb-4">
                        Kasir selalu boleh mencatat uang yang keluar dari laci — beli galon, setoran, tukar uang kecil.
                        Yang diatur di sini: sampai berapa catatan itu langsung berlaku tanpa menunggu Anda.
                    </p>

                    <div class="flex items-center gap-3">
                        <div class="relative w-44">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">Rp</span>
                            <input
                                v-model.number="form.cash_payout_approval_threshold"
                                type="number"
                                min="0"
                                step="1000"
                                class="w-full pl-9 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                            />
                        </div>
                        <p class="text-xs text-gray-500">
                            Pengeluaran di atas
                            <strong>Rp {{ Number(form.cash_payout_approval_threshold || 0).toLocaleString('id-ID') }}</strong>
                            menunggu persetujuan Anda.
                        </p>
                    </div>

                    <p v-if="form.errors.cash_payout_approval_threshold" class="mt-1.5 text-xs text-destructive">
                        {{ form.errors.cash_payout_approval_threshold }}
                    </p>

                    <p class="mt-3 text-xs text-gray-500 leading-relaxed">
                        Yang menunggu persetujuan <strong>tetap tercatat dan terlihat</strong>, tapi belum mengurangi uang yang seharusnya ada di laci —
                        jadi ia tidak bisa dipakai menutupi selisih. Isi <strong>0</strong> kalau setiap pengeluaran harus lewat Anda.
                        Setoran yang <em>masuk</em> ke laci tidak pernah menunggu persetujuan.
                    </p>
                </div>

                <!-- Aturan Kerja Kasir -->
                <div class="pt-5 border-t border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Aturan Kerja Kasir</h2>
                    <p class="text-xs text-gray-500 mt-0.5 mb-4">
                        Bukan modul yang menyala atau mati, melainkan apa yang wajib dilakukan kasir sebelum sebuah penjualan bisa ditutup.
                    </p>

                    <div class="space-y-3">
                        <label class="flex gap-3 p-3 rounded-lg border border-gray-200 cursor-pointer hover:bg-gray-50">
                            <input v-model="form.upsell_mandatory" type="checkbox" class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary focus:ring-ring" />
                            <span class="text-sm">
                                <span class="font-medium text-gray-900 block">Penawaran wajib diselesaikan</span>
                                <span class="text-xs text-gray-500">
                                    Kasir tidak bisa menekan BAYAR sampai tiap saran dijawab — ditandai
                                    <strong>diterima</strong> atau <strong>ditolak pelanggan</strong>. Keduanya sah;
                                    yang tidak bisa hanyalah melewatinya tanpa menjawab.
                                </span>
                            </span>
                        </label>

                        <div v-if="form.upsell_mandatory && !features.upsell_mandatory" class="flex gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                            <svg class="w-4 h-4 shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z" />
                            </svg>
                            <span>
                                Ini satu-satunya pengaturan yang bisa <strong>menahan penjualan</strong>.
                                Kasir yang antre panjang akan menekan "ditolak" tanpa menawarkan kalau merasa terburu —
                                angka penolakan yang melonjak adalah tanda pertama itu terjadi.
                            </span>
                        </div>

                        <!-- Identitas pesanan: satu mode, bukan tiga saklar -->
                        <div class="p-3 rounded-lg border border-gray-200">
                            <label class="block text-sm font-medium text-gray-900 mb-1">Identitas Pesanan</label>
                            <p class="text-xs text-gray-500 mb-2">
                                Cara mengenali pesanan saat dipanggil atau diantar.
                                <strong>Pilih satu</strong> — outlet yang memakai ketiganya sekaligus biasanya berakhir tidak mengisi satu pun.
                            </p>
                            <select
                                v-model="form.order_identity_mode"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                :class="{ 'border-red-300': form.errors.order_identity_mode }"
                            >
                                <option v-for="(label, value) in orderIdentityModes" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                            <p v-if="form.errors.order_identity_mode" class="mt-1 text-xs text-red-600">
                                {{ form.errors.order_identity_mode }}
                            </p>
                            <p v-else class="mt-1.5 text-xs text-gray-500 leading-relaxed">{{ identityModeHint }}</p>
                        </div>

                        <div
                            v-if="form.order_identity_mode === 'code' && form.kitchen_queue_enabled"
                            class="flex gap-2 rounded-lg bg-blue-50 border border-blue-100 px-3 py-2 text-xs text-blue-700"
                        >
                            <svg class="w-4 h-4 shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>
                                Antrian Dapur juga memberi nomor panggil, jadi mode ini tidak menambah apa pun selama papan menyala.
                                Gunanya baru terasa kalau papan dimatikan — nomornya tetap ada.
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 disabled:opacity-50 transition-colors"
                    >
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Cara Kerja' }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Pajak ([BL-065]) — kartu & endpoint tersendiri -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
            <form @submit.prevent="submitTax" class="space-y-5">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Pajak</h2>
                    <p class="text-xs text-gray-500 mt-0.5 mb-4">
                        Bawaannya <strong>mati</strong>, dan untuk sebagian besar usaha memang itu yang benar.
                        Kewajiban memungut PPN baru muncul setelah omzet setahun melewati <strong>Rp 4,8 miliar</strong>;
                        di bawah itu Anda berstatus pengusaha kecil dan tidak wajib memungut apa pun.
                        Rumah makan dan kafe memungut <strong>PBJT</strong> daerah, bukan PPN — tarif dan batasnya ditetapkan Perda setempat.
                    </p>

                    <label
                        class="flex gap-3 p-3 rounded-lg border border-gray-200"
                        :class="taxLocked ? 'bg-gray-50 cursor-not-allowed' : 'cursor-pointer hover:bg-gray-50'"
                    >
                        <input
                            v-model="taxForm.tax_enabled"
                            type="checkbox"
                            :disabled="taxLocked"
                            class="mt-0.5 w-4 h-4 rounded border-gray-300 text-primary focus:ring-ring disabled:opacity-50"
                        />
                        <span class="text-sm">
                            <span class="font-medium text-gray-900 block">Pungut pajak pada setiap penjualan</span>
                            <span class="text-xs text-gray-500">
                                Struk akan menampilkan pajaknya sebagai baris tersendiri, dan laporan memisahkan
                                omzet dari pajak yang harus Anda setorkan.
                            </span>
                        </span>
                    </label>

                    <p v-if="taxForm.errors.tax_enabled" class="mt-1.5 text-xs text-destructive">
                        {{ taxForm.errors.tax_enabled }}
                    </p>
                </div>

                <div v-if="taxForm.tax_enabled" class="space-y-4 pt-1">
                    <!-- Jenis pajak: ditanyakan, tidak pernah ditebak -->
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1">Jenis Pajak</label>
                        <p class="text-xs text-gray-500 mb-2">
                            Kata ini yang <strong>tercetak di struk pelanggan</strong>, dan keduanya menyebut dasar hukum yang berbeda.
                        </p>
                        <select
                            v-model="taxForm.tax_label"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            :class="{ 'border-red-300': taxForm.errors.tax_label }"
                        >
                            <option value="">— pilih —</option>
                            <option value="PPN">PPN — pajak pusat, untuk usaha yang sudah dikukuhkan PKP</option>
                            <option value="PB1">PB1 / PBJT — pajak daerah, untuk rumah makan dan kafe</option>
                        </select>
                        <p v-if="taxForm.errors.tax_label" class="mt-1 text-xs text-red-600">
                            {{ taxForm.errors.tax_label }}
                        </p>
                    </div>

                    <!-- Mode: yang menentukan siapa menanggung -->
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1">Cara Membebankan</label>
                        <select
                            v-model="taxForm.tax_mode"
                            :disabled="taxLocked"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring disabled:bg-gray-50 disabled:text-gray-500"
                            :class="{ 'border-red-300': taxForm.errors.tax_mode }"
                        >
                            <option v-for="(label, value) in taxModes" :key="value" :value="value">
                                {{ label }}
                            </option>
                        </select>
                        <p v-if="taxForm.errors.tax_mode" class="mt-1 text-xs text-red-600">
                            {{ taxForm.errors.tax_mode }}
                        </p>
                    </div>

                    <!-- Tarif: tidak pernah terkunci -->
                    <div>
                        <label class="block text-sm font-medium text-gray-900 mb-1">Tarif</label>
                        <div class="flex items-center gap-3">
                            <div class="relative w-32">
                                <input
                                    v-model.number="taxForm.tax_rate"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.5"
                                    class="w-full pr-8 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                                />
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">%</span>
                            </div>
                            <p class="text-xs text-gray-500">
                                PPN saat ini <strong>11%</strong>. PBJT paling tinggi <strong>10%</strong>, sesuai Perda daerah Anda.
                            </p>
                        </div>
                        <p v-if="taxForm.errors.tax_rate" class="mt-1.5 text-xs text-destructive">
                            {{ taxForm.errors.tax_rate }}
                        </p>
                    </div>

                    <!-- Akibatnya, dalam angka -->
                    <div v-if="taxExample" class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2.5 text-xs text-gray-600 leading-relaxed">
                        Barang berharga <strong>Rp {{ rupiah(10000) }}</strong>:
                        pelanggan membayar <strong>Rp {{ rupiah(taxExample.paid) }}</strong>,
                        pajak yang Anda setorkan <strong>Rp {{ rupiah(taxExample.tax) }}</strong>,
                        pendapatan Anda <strong>Rp {{ rupiah(taxExample.income) }}</strong>.
                    </div>
                </div>

                <!-- Penguncian: dijelaskan sebagai sebab, bukan sebagai larangan -->
                <div v-if="taxLocked" class="flex gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                    <svg class="w-4 h-4 shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <span>
                        Sakelar dan cara membebankan <strong>terkunci</strong> karena sudah ada penjualan yang memungut pajak.
                        Mengubahnya sekarang membuat omzet sebelum dan sesudahnya tidak bisa dibandingkan.
                        <strong>Tarif dan jenis pajak tetap bisa diubah</strong> dan berlaku untuk penjualan berikutnya.
                        Butuh membuka yang terkunci — misalnya usaha Anda berhenti wajib memungut? Hubungi operator.
                    </span>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        :disabled="taxForm.processing"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 disabled:opacity-50 transition-colors"
                    >
                        <svg v-if="taxForm.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        {{ taxForm.processing ? 'Menyimpan...' : 'Simpan Pajak' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
