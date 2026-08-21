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
    </div>
</template>
