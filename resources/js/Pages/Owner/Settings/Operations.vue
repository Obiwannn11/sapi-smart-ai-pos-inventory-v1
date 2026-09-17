<script setup>
import { computed } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import Checkbox from '@/Components/Checkbox.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import Button from '@/Components/Button.vue';
import { BUSINESS_TZ } from '@/support/date';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    features: Object,
    orderIdentityModes: { type: Object, default: () => ({}) },
    featureWarnings: Object,
    tax: { type: Object, default: () => ({}) },
    taxModes: { type: Object, default: () => ({}) },
    serviceCharge: { type: Object, default: () => ({}) },
    // Jenis saran yang dimatikan untuk SELURUH toko ([BL-099]). Saklarnya
    // tetap tampil, tapi terkunci — menyembunyikannya membuat owner mengira
    // jenis itu tidak pernah ada.
    upsellTypesLockedGlobally: { type: Array, default: () => [] },
    // Paket setelan awal ([BL-035]).
    sellingStyles: { type: Array, default: () => [] },
    stylePresets: { type: Object, default: () => ({}) },
    settingCatalog: { type: Object, default: () => ({}) },
    currentStyle: { type: String, default: null },
});

const form = useForm({
    kitchen_queue_enabled: props.features.kitchen_queue_enabled,
    self_order_enabled:    props.features.self_order_enabled,
    ai_enabled:            props.features.ai_enabled,
    payment_proof_enabled: props.features.payment_proof_enabled,
    upsell_mandatory:      props.features.upsell_mandatory,
    upsell_attach_enabled:        props.features.upsell_attach_enabled ?? true,
    upsell_pressed_stock_enabled: props.features.upsell_pressed_stock_enabled ?? true,
    upsell_upsize_enabled:        props.features.upsell_upsize_enabled ?? true,
    upsell_manual_enabled:        props.features.upsell_manual_enabled ?? true,
    order_identity_mode:   props.features.order_identity_mode ?? 'none',
    min_margin_percent:    props.features.min_margin_percent ?? 10,
    cash_payout_approval_threshold: props.features.cash_payout_approval_threshold ?? 50000,
});

// --- Paket setelan awal ([BL-035]) ---

/**
 * Paket yang sedang disorot untuk diterapkan. `null` berarti belum ada yang
 * dipilih, dan selama itu tidak ada tombol terapkan sama sekali.
 */
const presetForm = useForm({ selling_style: null });

/**
 * Apa yang akan BERUBAH kalau paket yang disorot diterapkan.
 *
 * Dihitung di layar, bukan diminta ke server, supaya daftarnya berganti
 * seketika saat paketnya diganti. Isinya cuma selisih: setelan yang nilainya
 * sudah sama tidak disebut, karena daftar yang memuat "tidak berubah" membuat
 * yang benar-benar berubah tenggelam.
 *
 * Menampilkan ini bukan hiasan — ia salah satu dari tiga syarat yang membuat
 * tombol terapkan boleh ada sama sekali (`[BL-035]`): diminta pengguna,
 * memperlihatkan akibatnya lebih dulu, dan tidak pernah terpicu perubahan
 * jenis usaha.
 */
const presetChanges = computed(() => {
    const style = presetForm.selling_style;
    const preset = props.stylePresets[style];

    if (!preset) {
        return [];
    }

    return Object.entries(props.settingCatalog).flatMap(([name, definition]) => {
        const now = form[definition.column];
        const next = definition.type === 'boolean'
            ? preset.features.includes(name)
            : (preset.settings[name] ?? now);

        if (now === next) {
            return [];
        }

        const describe = (value) => definition.type === 'boolean'
            ? (value ? 'Menyala' : 'Mati')
            : (props.orderIdentityModes[value] ?? value);

        return [{
            name,
            label: definition.label,
            from: describe(now),
            to: describe(next),
        }];
    });
});

const applyPreset = () => {
    presetForm.post('/owner/settings/operations/preset', { preserveScroll: true });
};

/**
 * Keempat jenis saran jual, dengan kalimat yang menyebut apa yang HILANG kalau
 * saklarnya dimatikan — bukan definisi jenisnya ([BL-099]).
 *
 * Owner sampai ke layar ini dari tabel "Per Jenis Saran" di laporan, jadi ia
 * sudah tahu jenisnya apa; yang belum ia tahu adalah apa yang berhenti muncul.
 */
const UPSELL_TYPES = [
    {
        key: 'attach',
        field: 'upsell_attach_enabled',
        title: 'Tambah add-on',
        detail: 'Topping atau pelengkap yang sering menyertai barang di keranjang. Jenis yang paling sering muncul, dan paling kecil nilainya per saran.',
    },
    {
        key: 'pressed_stock',
        field: 'upsell_pressed_stock_enabled',
        title: 'Barang tertekan',
        detail: 'Barang yang mendekati kedaluwarsa atau lama tidak bergerak. Mematikannya berarti stok yang terdesak waktu tidak lagi ditawarkan lebih dulu.',
    },
    {
        key: 'upsize',
        field: 'upsell_upsize_enabled',
        title: 'Naik ukuran',
        detail: 'Varian lain dari produk yang sama dengan harga sedikit lebih tinggi.',
    },
    {
        key: 'manual',
        field: 'upsell_manual_enabled',
        title: 'Aturan Anda',
        detail: 'Mematikannya membungkam SELURUH aturan di halaman Aturan Saran Jual sekaligus — saklar darurat, bukan cara mengatur satu per satu.',
    },
];

const isTypeLocked = (key) => props.upsellTypesLockedGlobally.includes(key);

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

// Daftar mode datang dari server sebagai peta nilai→label; SelectDropdown
// bekerja dengan daftar, jadi bentuknya disamakan di satu tempat.
const identityModeOptions = computed(() =>
    Object.entries(props.orderIdentityModes).map(([value, label]) => ({ value, label })),
);

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

// Terkunci secara struktur — ada penjualan berpajak yang tercatat.
const taxStructurallyLocked = computed(() => props.tax.locked === true);

// Jendela yang sedang dibukakan operator platform ([BL-065] butir 4).
const taxUnlockUntil = computed(() => props.tax.lock_opened_until ?? null);

// Yang menentukan kendalinya mati atau hidup adalah KEDUANYA, dan sengaja
// dirakit di sini alih-alih dikirim jadi satu boolean: "terkunci" dan "sedang
// dibukakan" dua fakta berbeda, dan layarnya perlu keduanya untuk bisa
// mengatakan sampai kapan kesempatannya berlaku.
const taxLocked = computed(() => taxStructurallyLocked.value && taxUnlockUntil.value === null);

const taxUnlockDeadline = computed(() => {
    if (taxUnlockUntil.value === null) return null;

    return new Date(taxUnlockUntil.value).toLocaleString('id-ID', {
        timeZone: BUSINESS_TZ,
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});

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

const taxModeOptions = computed(() =>
    Object.entries(props.taxModes).map(([value, label]) => ({ value, label })),
);

// Kata yang tercetak di struk. Keterangannya ikut di dalam label karena
// justru itulah yang membedakan keduanya bagi owner.
const taxLabelOptions = [
    { value: 'PPN', label: 'PPN — pajak pusat, untuk usaha yang sudah dikukuhkan PKP' },
    { value: 'PB1', label: 'PB1 / PBJT — pajak daerah, untuk rumah makan dan kafe' },
];

const rupiah = (value) => Number(value || 0).toLocaleString('id-ID');

const submitTax = () => {
    taxForm.patch('/owner/settings/operations/tax', { preserveScroll: true });
};

// Biaya layanan ([BL-097]) — form dan endpoint tersendiri lagi.
//
// Tidak ada padanan `taxLocked` di sini, dan itu bukan yang terlewat: biaya
// layanan sengaja tidak pernah dikunci. Ia pilihan komersial pemilik toko,
// bukan kewajiban hukum, jadi boleh dinyalakan dan dimatikan kapan pun. Yang
// menjaga angka lama tetap benar adalah pembekuan per transaksi.
const serviceChargeForm = useForm({
    service_charge_enabled: props.serviceCharge.service_charge_enabled ?? false,
    service_charge_rate:    props.serviceCharge.service_charge_rate ?? 0,
    service_charge_label:   props.serviceCharge.service_charge_label ?? '',
});

// Contohnya menunjukkan hal yang berbeda dari contoh pajak di atas: bukan
// siapa yang menanggung, melainkan bahwa pajak dipungut ATAS jumlah harga
// plus biaya layanan. Itu satu-satunya bagian yang tidak bisa ditebak owner
// sendiri, dan satu-satunya yang berubah kalau urutannya salah.
const serviceChargeExample = computed(() => {
    const serviceRate = Number(serviceChargeForm.service_charge_rate) || 0;
    const price = 10000;

    if (serviceRate <= 0) {
        return null;
    }

    const service = Math.round(price * serviceRate / 100);
    const taxRate = taxForm.tax_enabled ? (Number(taxForm.tax_rate) || 0) : 0;

    if (taxRate <= 0) {
        return { service, tax: 0, paid: price + service, taxed: false };
    }

    if (taxForm.tax_mode === 'inclusive') {
        const paid = price + service;

        return { service, tax: Math.round(paid * taxRate / (100 + taxRate)), paid, taxed: true };
    }

    const tax = Math.round((price + service) * taxRate / 100);

    return { service, tax, paid: price + service + tax, taxed: true };
});

const serviceChargeLabelOptions = [
    { value: 'Biaya Layanan', label: 'Biaya Layanan' },
    { value: 'Service Charge', label: 'Service Charge' },
    { value: 'Biaya Pelayanan', label: 'Biaya Pelayanan' },
];

const submitServiceCharge = () => {
    serviceChargeForm.patch('/owner/settings/operations/service-charge', { preserveScroll: true });
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

        <!--
            Paket setelan awal ([BL-035]). Kartu TERSENDIRI di atas formulir
            utama, dan bukan cuma demi tata letak: ia mengirim ke endpoint lain,
            dan menyarangkan dua form tidak sah. Pemisahan itu juga yang menjaga
            batas `[BL-034]` — menyimpan setelan di bawah tidak akan pernah
            ikut menerapkan paket.
        -->
        <div
            v-if="sellingStyles.length"
            class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-4"
        >
            <h2 class="text-base font-semibold text-gray-900">Paket Setelan Awal</h2>
            <p class="text-xs text-gray-500 mt-0.5 mb-4">
                Menyetel beberapa pilihan sekaligus agar cocok dengan cara Anda berjualan.
                Tidak ada yang terkunci — setelah diterapkan, semuanya masih bisa Anda ubah satu per satu di bawah.
            </p>

            <div class="space-y-2">
                <label
                    v-for="style in sellingStyles"
                    :key="style.name"
                    class="flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors"
                    :class="presetForm.selling_style === style.name
                        ? 'border-blue-400 bg-blue-50'
                        : 'border-gray-200 hover:bg-gray-50'"
                >
                    <input
                        type="radio"
                        name="selling-style"
                        :value="style.name"
                        v-model="presetForm.selling_style"
                        class="mt-0.5 h-4 w-4 shrink-0"
                    />
                    <span class="min-w-0">
                        <span class="block text-sm font-medium text-gray-900">
                            {{ style.label }}
                            <span
                                v-if="currentStyle === style.name"
                                class="ml-1 text-xs font-normal text-gray-500"
                            >&mdash; titik berangkat Anda</span>
                        </span>
                        <span class="block text-xs text-gray-500 mt-0.5">{{ style.description }}</span>
                    </span>
                </label>
            </div>

            <!--
                Pratinjau perubahan. Tombol terapkan TIDAK ada sebelum blok ini
                bisa tampil — itu syaratnya, bukan kenyamanan.
            -->
            <div v-if="presetForm.selling_style" class="mt-4">
                <div v-if="presetChanges.length" class="rounded-lg bg-gray-50 border border-gray-200 p-3">
                    <p class="text-xs font-medium text-gray-900 mb-2">Yang akan berubah</p>
                    <ul class="space-y-1">
                        <li
                            v-for="change in presetChanges"
                            :key="change.name"
                            class="flex items-baseline justify-between gap-3 text-xs"
                        >
                            <span class="text-gray-600">{{ change.label }}</span>
                            <span class="whitespace-nowrap text-gray-900">
                                <span class="text-gray-400 line-through">{{ change.from }}</span>
                                <span class="mx-1 text-gray-400">&rarr;</span>
                                <span class="font-medium">{{ change.to }}</span>
                            </span>
                        </li>
                    </ul>
                </div>

                <p v-else class="rounded-lg bg-gray-50 border border-gray-200 p-3 text-xs text-gray-600">
                    Setelan Anda sekarang sudah sama persis dengan paket ini. Menerapkannya tidak mengubah apa pun.
                </p>

                <Button
                    type="button"
                    class="mt-3"
                    :disabled="presetForm.processing || !presetChanges.length"
                    @click="applyPreset"
                >
                    {{ presetForm.processing ? 'Menerapkan…' : 'Terapkan paket ini' }}
                </Button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <form @submit.prevent="submit" class="space-y-5">
                <!-- Mode & Fitur Outlet -->
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Mode &amp; Fitur Outlet</h2>
                    <p class="text-xs text-gray-500 mt-0.5 mb-4">
                        Menyalakan atau mematikan kapabilitas untuk outlet ini. Berlaku di semua pintu masuk sekaligus — web, aplikasi kasir, dan integrasi.
                    </p>

                    <div class="space-y-3">
                        <Checkbox v-model="form.kitchen_queue_enabled" variant="card" align="start">
                            <span class="text-sm">
                                <span class="font-medium text-gray-900 block">Antrian Dapur</span>
                                <span class="text-xs text-gray-500">
                                    Menampilkan papan urutan pesanan untuk operator yang merangkap masak dan kasir, plus nomor antrian di struk.
                                    Mode ini <strong>tidak aktif saat perangkat offline</strong> — kasir kembali ke alur biasa dan penjualan hasil sinkronisasi tidak menyusul masuk papan.
                                </span>
                            </span>
                        </Checkbox>

                        <Checkbox v-model="form.self_order_enabled" variant="card" align="start">
                            <span class="text-sm">
                                <span class="font-medium text-gray-900 block">Self-Order</span>
                                <span class="text-xs text-gray-500">Pemesanan mandiri pelanggan lewat QR/Telegram beserta saran jualnya.</span>
                            </span>
                        </Checkbox>

                        <div v-if="warnSelfOrderOff" class="flex gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                            <svg class="w-4 h-4 shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.74-3L13.74 4a2 2 0 00-3.48 0L3.33 16a2 2 0 001.74 3z" />
                            </svg>
                            <span>
                                Masih ada <strong>{{ featureWarnings.active_self_orders }}</strong> pesanan mandiri yang belum selesai.
                                Mematikan fitur ini tidak membatalkannya, tapi pelanggan tidak bisa memesan lagi sampai dinyalakan kembali.
                            </span>
                        </div>

                        <Checkbox v-model="form.ai_enabled" variant="card" align="start">
                            <span class="text-sm">
                                <span class="font-medium text-gray-900 block">AI Analysis &amp; MCP</span>
                                <span class="text-xs text-gray-500">
                                    Analisis AI di aplikasi sekaligus akses AI client eksternal lewat token MCP.
                                    Kunci API dan tokennya sendiri diatur di
                                    <a href="/owner/settings/integrations" class="text-primary hover:underline" @click.stop>Integrasi &amp; Kredensial</a>.
                                </span>
                            </span>
                        </Checkbox>

                        <Checkbox v-model="form.payment_proof_enabled" variant="card" align="start">
                            <span class="text-sm">
                                <span class="font-medium text-gray-900 block">Foto Bukti Bayar Non-Tunai</span>
                                <span class="text-xs text-gray-500">
                                    Kasir memotret bukti QRIS/transfer sebelum penjualan ditutup, dan fotonya melekat pada pembayarannya.
                                    Berlaku untuk <strong>semua metode selain tunai</strong>; baris tunai tidak pernah diminta foto.
                                    Menyalakannya menambah satu langkah ke tiap penjualan non-tunai, jadi nyalakan hanya bila memang perlu bukti saat ada perselisihan.
                                    Penjualan <strong>offline selalu tunai</strong>, jadi aturan ini tidak pernah menghalangi kasir saat sinyal mati.
                                </span>
                            </span>
                        </Checkbox>

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
                        Harga terendah tiap barang, dihitung dari harga modalnya. Aturan diskon tidak akan pernah menurunkan harga di bawah batas ini.
                    </p>

                    <div class="flex items-center gap-3">
                        <div class="relative w-32">
                            <input
                                v-model.number="form.min_margin_percent"
                                type="number"
                                min="0"
                                max="90"
                                step="0.5"
                                class="w-full pr-8 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">%</span>
                        </div>
                        <p class="text-xs text-gray-500">
                            Barang bermodal Rp 10.000 punya batas
                            <strong>Rp {{ rupiah(Math.ceil(10000 * (1 + (form.min_margin_percent || 0) / 100) / 500) * 500) }}</strong>.
                        </p>
                    </div>

                    <p v-if="form.errors.min_margin_percent" class="mt-1.5 text-xs text-destructive">
                        {{ form.errors.min_margin_percent }}
                    </p>

                    <p class="mt-3 text-xs text-gray-500 leading-relaxed">
                        Hanya <strong>Anda</strong> yang bisa menjual di bawah batas ini, dan setiap kali wajib menyertakan alasan yang ikut tercatat pada penjualannya.
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
                                class="w-full pl-9 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
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
                        <Checkbox v-model="form.upsell_mandatory" variant="card" align="start">
                            <span class="text-sm">
                                <span class="font-medium text-gray-900 block">Penawaran wajib diselesaikan</span>
                                <span class="text-xs text-gray-500">
                                    Kasir tidak bisa menekan BAYAR sampai tiap saran dijawab — ditandai
                                    <strong>diterima</strong> atau <strong>ditolak pelanggan</strong>. Keduanya sah;
                                    yang tidak bisa hanyalah melewatinya tanpa menjawab.
                                </span>
                            </span>
                        </Checkbox>

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
                            <p class="text-sm font-medium text-gray-700 mb-1">Identitas Pesanan</p>
                            <p class="text-xs text-gray-500 mb-2">
                                Cara mengenali pesanan saat dipanggil atau diantar.
                                <strong>Pilih satu</strong> — outlet yang memakai ketiganya sekaligus biasanya berakhir tidak mengisi satu pun.
                            </p>
                            <SelectDropdown
                                v-model="form.order_identity_mode"
                                :options="identityModeOptions"
                                :error="form.errors.order_identity_mode"
                            />
                            <p v-if="!form.errors.order_identity_mode" class="mt-1.5 text-xs text-gray-500 leading-relaxed">
                                {{ identityModeHint }}
                            </p>
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

                <!-- Jenis Saran Jual -->
                <div class="pt-5 border-t border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Jenis Saran Jual</h2>
                    <p class="text-xs text-gray-500 mt-0.5 mb-4">
                        Jenis saran apa saja yang boleh dihasilkan untuk kasir. Angka yang menentukan pilihan ini ada di
                        <Link href="/owner/reports/upsell" class="text-primary hover:underline font-medium">Laporan &rarr; Saran Jual</Link>,
                        tabel <strong>Per Jenis Saran</strong> — jenis yang berkali-kali tampil tapi hampir tidak pernah diterima
                        hanya memakan slot yang bisa dipakai jenis lain.
                    </p>

                    <div class="space-y-3">
                        <div v-for="type in UPSELL_TYPES" :key="type.key">
                            <Checkbox
                                v-model="form[type.field]"
                                variant="card"
                                align="start"
                                :disabled="isTypeLocked(type.key)"
                            >
                                <span class="text-sm">
                                    <span class="font-medium text-gray-900 block">{{ type.title }}</span>
                                    <span class="text-xs text-gray-500">{{ type.detail }}</span>
                                </span>
                            </Checkbox>

                            <!-- Dimatikan untuk semua toko. Disebutkan supaya owner tidak
                                 mengira aturannya sendiri yang rusak, tanpa menunjuk jalan
                                 yang tidak bisa ia tempuh ([BL-099]). -->
                            <p v-if="isTypeLocked(type.key)" class="mt-1 ml-1 text-xs text-gray-400">
                                Sedang dimatikan untuk semua toko, jadi saklar ini belum berpengaruh.
                                Pilihan Anda tetap tersimpan.
                            </p>
                        </div>
                    </div>

                    <p class="mt-3 text-xs text-gray-500 leading-relaxed">
                        Mematikan sebuah jenis <strong>tidak menghapus riwayatnya</strong> — angka lama tetap terbaca di laporan,
                        jadi keputusan ini bisa ditinjau ulang nanti. Kasir tidak melihat perubahan apa pun selain
                        saran jenis itu berhenti muncul.
                    </p>
                </div>

                <!-- Submit -->
                <div class="flex justify-end pt-2">
                    <Button type="submit" size="lg" :loading="form.processing">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Cara Kerja' }}
                    </Button>
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

                    <!-- Kunci yang sedang dibukakan operator ([BL-065] butir 4).
                         Batas waktunya disebutkan: kesempatan yang tidak menyebutkan
                         kapan habisnya akan dikira berlaku selamanya. -->
                    <div
                        v-if="taxUnlockDeadline"
                        class="mb-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2.5 text-xs text-amber-900 leading-relaxed"
                    >
                        <span class="font-semibold block">Kunci dibuka sampai {{ taxUnlockDeadline }}.</span>
                        Anda bisa mengubah sakelar atau mode pajak <strong>satu kali</strong> dalam jendela ini —
                        setelah tersimpan, kuncinya menutup kembali sendiri.
                    </div>

                    <Checkbox
                        v-model="taxForm.tax_enabled"
                        variant="card"
                        align="start"
                        :disabled="taxLocked"
                    >
                        <span class="text-sm">
                            <span class="font-medium text-gray-900 block">Pungut pajak pada setiap penjualan</span>
                            <span class="text-xs text-gray-500">
                                Struk akan menampilkan pajaknya sebagai baris tersendiri, dan laporan memisahkan
                                omzet dari pajak yang harus Anda setorkan.
                            </span>
                        </span>
                    </Checkbox>

                    <p v-if="taxForm.errors.tax_enabled" class="mt-1.5 text-xs text-destructive">
                        {{ taxForm.errors.tax_enabled }}
                    </p>
                </div>

                <div v-if="taxForm.tax_enabled" class="space-y-4 pt-1">
                    <!-- Jenis pajak: ditanyakan, tidak pernah ditebak -->
                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-1">Jenis Pajak</p>
                        <p class="text-xs text-gray-500 mb-2">
                            Kata ini yang <strong>tercetak di struk pelanggan</strong>, dan keduanya menyebut dasar hukum yang berbeda.
                        </p>
                        <SelectDropdown
                            v-model="taxForm.tax_label"
                            :options="taxLabelOptions"
                            placeholder="— pilih —"
                            :error="taxForm.errors.tax_label"
                        />
                    </div>

                    <!-- Mode: yang menentukan siapa menanggung -->
                    <div>
                        <SelectDropdown
                            v-model="taxForm.tax_mode"
                            :options="taxModeOptions"
                            label="Cara Membebankan"
                            :disabled="taxLocked"
                            :error="taxForm.errors.tax_mode"
                        />
                    </div>

                    <!-- Tarif: tidak pernah terkunci -->
                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-1">Tarif</p>
                        <div class="flex items-center gap-3">
                            <div class="relative w-32">
                                <input
                                    v-model.number="taxForm.tax_rate"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.5"
                                    class="w-full pr-8 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
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
                    <Button type="submit" size="lg" :loading="taxForm.processing">
                        {{ taxForm.processing ? 'Menyimpan...' : 'Simpan Pajak' }}
                    </Button>
                </div>
            </form>
        </div>

        <!-- Biaya layanan ([BL-097]) - kartu & endpoint tersendiri.
             Tidak ada blok penguncian di sini; lihat komentar formnya. -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mt-6">
            <form @submit.prevent="submitServiceCharge" class="space-y-5">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">Biaya Layanan</h2>
                    <p class="text-xs text-gray-500 mt-0.5 mb-4">
                        Bawaannya <strong>mati</strong>. Berbeda dari pajak, ini <strong>pilihan Anda sendiri</strong> —
                        tidak ada aturan yang mewajibkannya, dan Anda bebas menyalakan atau mematikannya kapan saja.
                        Perubahannya berlaku untuk penjualan berikutnya; struk yang sudah tercetak tidak ikut berubah.
                    </p>

                    <Checkbox
                        v-model="serviceChargeForm.service_charge_enabled"
                        variant="card"
                        align="start"
                    >
                        <span class="text-sm">
                            <span class="font-medium text-gray-900 block">Pungut biaya layanan pada setiap penjualan</span>
                            <span class="text-xs text-gray-500">
                                Struk menampilkannya sebagai baris tersendiri di atas pajak, dan laporan
                                memisahkannya dari omzet toko.
                            </span>
                        </span>
                    </Checkbox>

                    <p v-if="serviceChargeForm.errors.service_charge_enabled" class="mt-1.5 text-xs text-destructive">
                        {{ serviceChargeForm.errors.service_charge_enabled }}
                    </p>
                </div>

                <div v-if="serviceChargeForm.service_charge_enabled" class="space-y-4 pt-1">
                    <!-- Namanya: ditanyakan, tidak pernah ditebak -->
                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-1">Nama di Struk</p>
                        <p class="text-xs text-gray-500 mb-2">
                            Kata ini yang <strong>tercetak di struk pelanggan</strong>.
                        </p>
                        <SelectDropdown
                            v-model="serviceChargeForm.service_charge_label"
                            :options="serviceChargeLabelOptions"
                            placeholder="— pilih —"
                            :error="serviceChargeForm.errors.service_charge_label"
                        />
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-700 mb-1">Tarif</p>
                        <div class="flex items-center gap-3">
                            <div class="relative w-32">
                                <input
                                    v-model.number="serviceChargeForm.service_charge_rate"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.5"
                                    class="w-full pr-8 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                />
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">%</span>
                            </div>
                            <p class="text-xs text-gray-500">
                                Umumnya <strong>5%</strong>. Tidak ada batas yang ditetapkan aturan — ini keputusan usaha Anda.
                            </p>
                        </div>
                        <p v-if="serviceChargeForm.errors.service_charge_rate" class="mt-1.5 text-xs text-destructive">
                            {{ serviceChargeForm.errors.service_charge_rate }}
                        </p>
                    </div>

                    <!-- Akibatnya, dalam angka. Yang ditunjukkan terutama:
                         pajak dipungut ATAS harga + biaya layanan. -->
                    <div v-if="serviceChargeExample" class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2.5 text-xs text-gray-600 leading-relaxed">
                        Barang berharga <strong>Rp {{ rupiah(10000) }}</strong>:
                        biaya layanan <strong>Rp {{ rupiah(serviceChargeExample.service) }}</strong>,
                        <template v-if="serviceChargeExample.taxed">
                            pajak <strong>Rp {{ rupiah(serviceChargeExample.tax) }}</strong>
                            (dihitung dari harga <em>ditambah</em> biaya layanan, sesuai ketentuan pajak daerah),
                        </template>
                        pelanggan membayar <strong>Rp {{ rupiah(serviceChargeExample.paid) }}</strong>.
                    </div>

                    <!-- Yang tidak boleh disembunyikan dari pemilik toko -->
                    <div class="flex gap-2 rounded-lg bg-blue-50 border border-blue-200 px-3 py-2 text-xs text-blue-900">
                        <svg class="w-4 h-4 shrink-0 mt-px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>
                            Biaya layanan <strong>tidak dihitung sebagai omzet toko</strong> di laporan laba —
                            di banyak usaha ia dikumpulkan untuk dibagikan ke staf. Uangnya tetap masuk laci
                            dan tetap terhitung di total penjualan; yang memisahkannya hanya perhitungan margin.
                        </span>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <Button type="submit" size="lg" :loading="serviceChargeForm.processing">
                        {{ serviceChargeForm.processing ? 'Menyimpan...' : 'Simpan Biaya Layanan' }}
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
