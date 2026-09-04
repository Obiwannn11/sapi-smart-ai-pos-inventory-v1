<script setup>
/**
 * Aturan Saran Jual — tempat owner menuliskan targetnya sendiri ([BL-074]).
 *
 * Halaman ini menjawab satu keluhan yang tepat: seluruh saran jual hari ini
 * DITEMUKAN MESIN — ko-okurensi modifier, barang tertekan stok, naik ukuran
 * berdasarkan selisih harga. Orang yang paling tahu barangnya sendiri belum
 * punya satu pun tempat untuk mengatakan "bulan ini dorong kopi susu botol".
 *
 * **Dua tab, bukan satu gulungan panjang.** Halaman ini mengerjakan dua hal
 * yang berbeda: MENULIS aturan, dan MELIHAT apa yang benar-benar muncul di
 * kasir hari ini. Yang kedua dulu terkubur di dasar halaman, di bawah tabel
 * dan modal — padahal ia jawaban atas pertanyaan pertama owner setiap kali ia
 * selesai menulis aturan. Dua penawar menjaga isi tab yang tertutup tetap
 * terbaca: angka di label tabnya, dan lompatan otomatis ke tab pratinjau tiap
 * kali sebuah aturan disimpan.
 *
 * **Batasnya ditunjukkan, bukan diterangkan.** Penjaga stok, batas tiga slot,
 * dan urutan rebutan dulu ditulis sebagai paragraf di kepala halaman. Paragraf
 * itu dibaca sekali, oleh owner yang belum punya satu pun aturan, lalu tidak
 * pernah dibaca lagi tepat pada saat batasnya menggigit. Sekarang ketiganya
 * diperagakan tab pratinjau, pada barang milik owner sendiri.
 */
import { ref, computed } from 'vue';
import { Deferred, useForm, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import SkeletonTable from '@/Components/Skeleton/SkeletonTable.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Checkbox from '@/Components/Checkbox.vue';
import TabNav from '@/Components/TabNav.vue';
import { businessToday, parseDateOnly } from '@/support/date';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    // Ditunda ([BL-037]) — null selama daftarnya masih dimuat.
    rules: { type: Array, default: null },
    variants: { type: Array, default: null },
    // Pratinjau slot kasir ([BL-092]) — kelompok tunda tersendiri.
    preview: { type: Object, default: null },
});

/**
 * Label sumber tiap saran di pratinjau.
 *
 * KATA DAN WARNANYA disalin dari `UpsellStrip.vue`, bukan dikarang ulang.
 * Tab ini mengaku memperagakan layar kasir; kalau kasir membaca "DORONG"
 * sementara layar ini menulis "Barang tertekan", owner yang membandingkan
 * keduanya sedang memetakan dua kosakata, bukan membaca satu sistem.
 *
 * Tabel di halaman laporan sengaja TIDAK ikut: ia buku besar, bukan cermin,
 * dan di sana "barang tertekan" menyebutkan sebab yang justru sedang dinilai.
 */
const SOURCE_BADGES = {
    manual: { text: 'Pilihan pemilik', class: 'bg-primary/10 text-primary' },
    attach: { text: 'Tambah', class: 'bg-muted text-muted-foreground' },
    pressed_stock: { text: 'Dorong', class: 'bg-warning/15 text-warning-foreground' },
    upsize: { text: 'Naik ukuran', class: 'bg-success/10 text-success' },
};

const sourceBadge = (slot) => SOURCE_BADGES[slot.type] ?? { text: slot.type, class: 'bg-gray-100 text-gray-600' };

const TYPE_NAMES = {
    attach: 'tambah add-on',
    pressed_stock: 'barang tertekan',
    upsize: 'naik ukuran',
    manual: 'aturan Anda sendiri',
};

const disabledTypeNames = computed(() =>
    (props.preview?.disabled_types ?? []).map((type) => TYPE_NAMES[type] ?? type).join(', ')
);

const variantOptions = computed(() =>
    (props.variants ?? []).map((variant) => ({
        value: variant.id,
        label: variant.stock > 0 ? variant.label : `${variant.label} (stok habis)`,
    }))
);

const triggerOptions = computed(() => [
    { value: '', label: 'Setiap penjualan (tanpa pemicu)' },
    ...variantOptions.value,
]);

const formatRupiah = (value) => 'Rp ' + Number(value ?? 0).toLocaleString('id-ID');

/**
 * Tanggal jendela aturan, mis. "Kam, 20 Agu 2026".
 *
 * `parseDateOnly`, bukan `new Date(value)`: isinya tanggal tanpa jam, dan
 * `new Date('2026-08-20')` diurai sebagai tengah malam UTC — di WITA ia mundur
 * satu hari, sehingga aturan yang mulai tanggal 20 dilaporkan mulai 19.
 *
 * Bulan PENDEK, mengikuti tabel lain di aplikasi ini (rekap bulanan,
 * DailyChart, daftar tagihan) — bulan panjang disimpan untuk judul dan modal.
 * Di sini ia juga membayar dirinya sendiri: "20 September" menambah ~60px pada
 * lebar minimum tabel, dan tabel inilah yang paling awal harus digulir
 * mendatar di layar ponsel.
 */
const formatRuleDate = (value) => parseDateOnly(value).toLocaleDateString('id-ID', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

const variantLabel = (variant) => {
    if (!variant) return '—';

    return variant.product ? `${variant.product.name} - ${variant.name}` : variant.name;
};

/**
 * Dua nada untuk aturan yang sedang diam, memakai token sistem desain.
 *
 * Sebelumnya SELURUH sebab memakai satu abu-abu yang sama, jadi "owner sendiri
 * yang mematikannya" terlihat persis seperti "stoknya habis" — padahal yang
 * pertama tidak menunggu apa-apa dan yang kedua menunggu owner bertindak.
 *
 * Hanya dua, bukan satu warna per sebab: yang dijawab warnanya cuma "perlu saya
 * apa-apakan atau tidak". Pertanyaan "kenapa" sudah dijawab tulisannya.
 *
 * `text-warning-foreground`, bukan `text-warning`: token `--warning` adalah
 * kuning terang (L 0.78) dan di atas tint 15% ia nyaris tak terbaca.
 */
const STATUS_TONES = {
    /** Disengaja atau sekadar soal waktu — tidak ada yang perlu dikerjakan. */
    quiet: 'bg-muted text-muted-foreground',
    /** Ada yang menghalangi, dan owner bisa membereskannya. */
    blocked: 'bg-warning/15 text-warning-foreground',
    /** Berjalan. */
    live: 'bg-success/10 text-success',
};

/**
 * Kenapa sebuah aturan tidak muncul di kasir hari ini, atau null bila muncul.
 *
 * @return {{label: string, tone: 'quiet'|'blocked'}|null}
 */
const dormantReason = (rule) => {
    if (!rule.is_active) return { label: 'Dimatikan', tone: 'quiet' };

    // Hari toko ([BL-082]): `toISOString()` memberi tanggal UTC, sehingga
    // sepanjang pukul 00.00–08.00 WITA aturan yang mulai hari ini masih
    // dilaporkan "Belum mulai".
    const today = businessToday();

    if (rule.starts_on && rule.starts_on.slice(0, 10) > today) {
        return { label: 'Belum mulai', tone: 'quiet' };
    }
    if (rule.ends_on && rule.ends_on.slice(0, 10) < today) {
        return { label: 'Sudah berakhir', tone: 'quiet' };
    }
    // Penjaga kandidat berlaku tanpa pengecualian, termasuk untuk aturan yang
    // owner tulis sendiri. Menampilkannya di sini mencegah kesimpulan "fiturnya
    // rusak" saat yang sebenarnya terjadi adalah stoknya nol.
    if (rule.suggested_variant && rule.suggested_variant.stock <= 0) {
        return { label: 'Stok barangnya habis', tone: 'blocked' };
    }

    return null;
};

const statusLabel = (rule) => dormantReason(rule)?.label ?? 'Tampil di kasir';

const statusClass = (rule) => STATUS_TONES[dormantReason(rule)?.tone ?? 'live'];

/** Ringkasan yang menggantikan paragraf pengantar: angka, bukan penjelasan. */
const ruleCounts = computed(() => {
    if (!props.rules) return null;

    const live = props.rules.filter((rule) => dormantReason(rule) === null).length;

    return { live, dormant: props.rules.length - live };
});

// --- Tab ---
const activeTab = ref('rules');

/**
 * Angka di label tab pratinjau: berapa slot kasir yang benar-benar terisi pada
 * penjualan apa pun hari ini. Inilah satu-satunya keadaan yang tidak bisa
 * ditebak dari tab sebelah, jadi inilah yang dibawa ke luar.
 */
const previewSlotCount = computed(() => {
    if (!props.preview?.enabled) return null;

    return props.preview.cart_level.filter((slot) => slot.wins_slot).length || null;
});

const tabs = computed(() => [
    { key: 'rules', label: 'Aturan saya' },
    { key: 'preview', label: 'Muncul di kasir', badge: previewSlotCount.value },
]);

// --- Form ---
const showForm = ref(false);
const editingId = ref(null);

const form = useForm({
    trigger_variant_id: '',
    suggested_variant_id: '',
    note: '',
    starts_on: '',
    ends_on: '',
    is_active: true,
});

const openCreate = () => {
    form.reset();
    form.clearErrors();
    editingId.value = null;
    showForm.value = true;
};

const openEdit = (rule) => {
    form.trigger_variant_id = rule.trigger_variant_id ?? '';
    form.suggested_variant_id = rule.suggested_variant_id;
    form.note = rule.note ?? '';
    form.starts_on = rule.starts_on ? rule.starts_on.slice(0, 10) : '';
    form.ends_on = rule.ends_on ? rule.ends_on.slice(0, 10) : '';
    form.is_active = rule.is_active;
    form.clearErrors();
    editingId.value = rule.id;
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            closeForm();
            // Akibat aturan yang baru ditulis DITUNJUKKAN, bukan diserahkan
            // pada owner untuk dicari sendiri di tab sebelah.
            activeTab.value = 'preview';
        },
    };

    if (editingId.value) {
        form.put(`/owner/upsell-rules/${editingId.value}`, options);
    } else {
        form.post('/owner/upsell-rules', options);
    }
};

// --- Nyalakan / matikan ---
const toggleForm = useForm({});

const toggle = (rule) => {
    toggleForm.post(`/owner/upsell-rules/${rule.id}/toggle`, { preserveScroll: true });
};

// --- Urutan ---
//
// Menggantikan kolom isian berisi angka 0–999. Owner yang ingin sebuah aturan
// tampil lebih dulu sedang menunjuk baris, bukan memikirkan bilangan.
const moveForm = useForm({ direction: 'up' });

const move = (rule, direction) => {
    moveForm.direction = direction;
    moveForm.post(`/owner/upsell-rules/${rule.id}/move`, { preserveScroll: true });
};

// --- Hapus ---
const deleteTarget = ref(null);
const deleteForm = useForm({});

const doDelete = () => {
    if (!deleteTarget.value) return;

    deleteForm.delete(`/owner/upsell-rules/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null; },
    });
};
</script>

<template>
    <Head title="Aturan Saran Jual" />

    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="flex items-start justify-between mb-5 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Aturan Saran Jual</h1>
                <p v-if="ruleCounts" class="text-sm text-gray-500 mt-1">
                    {{ ruleCounts.live }} tampil di kasir<template v-if="ruleCounts.dormant"> · {{ ruleCounts.dormant }} diam</template>
                </p>
                <p v-else class="text-sm text-gray-500 mt-1">Memuat aturan…</p>
            </div>
            <button
                @click="openCreate"
                class="shrink-0 inline-flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ring transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Aturan
            </button>
        </div>

        <!-- Saklar sistem berada DI ATAS tab, bukan di dalam salah satunya:
             kalau saran jual dimatikan seluruhnya, kedua tab sedang berbohong. -->
        <Deferred data="preview">
            <template #fallback><span /></template>

            <div class="space-y-2 mb-4">
                <div v-if="!preview.enabled" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Saran jual sedang <strong>dimatikan seluruhnya</strong> di setelan sistem. Kasir tidak menerima
                    saran apa pun, termasuk aturan yang Anda tulis di sini.
                </div>

                <div
                    v-else-if="disabledTypeNames"
                    class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900"
                >
                    Jenis saran berikut sedang dimatikan di setelan sistem: <strong>{{ disabledTypeNames }}</strong>.
                </div>
            </div>
        </Deferred>

        <TabNav :tabs="tabs" v-model="activeTab" class="mb-5" />

        <!-- Modal Form -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showForm" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="closeForm" />
                    <div class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full max-h-[85vh] overflow-y-auto p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            {{ editingId ? 'Edit Aturan' : 'Tambah Aturan' }}
                        </h3>

                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kapan saran ini muncul</label>
                                <SelectDropdown
                                    v-model="form.trigger_variant_id"
                                    :options="triggerOptions"
                                    placeholder="Setiap penjualan (tanpa pemicu)"
                                    searchable
                                    :error="form.errors.trigger_variant_id"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Barang yang disarankan *</label>
                                <SelectDropdown
                                    v-model="form.suggested_variant_id"
                                    :options="variantOptions"
                                    placeholder="Pilih barang"
                                    searchable
                                    :error="form.errors.suggested_variant_id"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Catatan untuk kasir</label>
                                <input
                                    v-model="form.note"
                                    type="text"
                                    maxlength="120"
                                    placeholder="Contoh: Promo bulan ini, stok baru datang"
                                    class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-ring transition-colors"
                                />
                                <p v-if="form.errors.note" class="mt-1 text-xs text-destructive">{{ form.errors.note }}</p>
                                <p v-else class="mt-1 text-xs text-gray-500">Tampil apa adanya di layar kasir.</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mulai</label>
                                    <DatePicker v-model="form.starts_on" block clearable />
                                    <p class="mt-1 text-xs text-gray-500">Kosong = mulai sekarang</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Berakhir</label>
                                    <DatePicker v-model="form.ends_on" block clearable />
                                    <p v-if="form.errors.ends_on" class="mt-1 text-xs text-destructive">{{ form.errors.ends_on }}</p>
                                    <p v-else class="mt-1 text-xs text-gray-500">Kosong = sampai dimatikan</p>
                                </div>
                            </div>

                            <Checkbox v-model="form.is_active" label="Aktif" />

                            <div class="flex gap-3 pt-2">
                                <button type="button" @click="closeForm" class="flex-1 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50">
                                    Batal
                                </button>
                                <button type="submit" :disabled="form.processing" class="flex-1 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 disabled:opacity-50">
                                    {{ form.processing ? 'Menyimpan…' : 'Simpan' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Tab 1: aturan yang owner tulis ──────────────────────────────
             Ditunda ([BL-037]) — kerangkanya memakai jumlah kolom yang sama
             supaya lebar kolom tidak berubah saat barisnya tiba. -->
        <div v-show="activeTab === 'rules'" class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <Deferred data="rules">
                <template #fallback>
                    <SkeletonTable :rows="5" :columns="5" label="Memuat aturan saran jual…" />
                </template>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="w-14 px-2 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Urutan</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Pemicu</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Disarankan</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Berlaku</th>
                                <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="(rule, index) in rules" :key="rule.id" class="hover:bg-gray-50 transition-colors">
                                <!-- Panahnya diberi bingkai supaya terbaca sebagai
                                     TOMBOL, bukan hiasan, dan `title` menjawab
                                     pertanyaan yang tidak bisa dijawab bentuk panah
                                     sendirian: naik ke mana, dan supaya apa. -->
                                <td class="px-2 py-4 align-middle">
                                    <div class="flex flex-col items-center gap-1">
                                        <button
                                            :disabled="index === 0 || moveForm.processing"
                                            :title="index === 0
                                                ? 'Sudah paling atas'
                                                : 'Naikkan — aturan ini lebih dulu mengisi slot kasir'"
                                            :aria-label="`Naikkan ${variantLabel(rule.suggested_variant)}`"
                                            class="rounded-md border border-gray-200 bg-white p-1 text-gray-500 transition-colors hover:border-gray-300 hover:bg-gray-100 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-gray-200 disabled:hover:bg-white disabled:hover:text-gray-500"
                                            @click="move(rule, 'up')"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7" />
                                            </svg>
                                        </button>
                                        <button
                                            :disabled="index === rules.length - 1 || moveForm.processing"
                                            :title="index === rules.length - 1
                                                ? 'Sudah paling bawah'
                                                : 'Turunkan — aturan lain lebih dulu mengisi slot kasir'"
                                            :aria-label="`Turunkan ${variantLabel(rule.suggested_variant)}`"
                                            class="rounded-md border border-gray-200 bg-white p-1 text-gray-500 transition-colors hover:border-gray-300 hover:bg-gray-100 hover:text-gray-900 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-gray-200 disabled:hover:bg-white disabled:hover:text-gray-500"
                                            @click="move(rule, 'down')"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span v-if="rule.trigger_variant" class="text-sm text-gray-900">
                                        {{ variantLabel(rule.trigger_variant) }}
                                    </span>
                                    <span v-else class="text-sm text-gray-500 italic">Setiap penjualan</span>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="text-sm font-medium text-gray-900 block">{{ variantLabel(rule.suggested_variant) }}</span>
                                    <span class="text-xs text-gray-500">{{ formatRupiah(rule.suggested_variant?.price) }}</span>
                                    <span v-if="rule.note" class="text-xs text-gray-400 block mt-0.5">“{{ rule.note }}”</span>
                                </td>
                                <!-- Bertumpuk dan berlabel, bukan "2026-08-19 – 2026-08-30".
                                     Dua tanggal ISO berdampingan menyuruh pembacanya
                                     menerjemahkan angka DAN menebak mana yang mulai;
                                     labelnya menjawab yang kedua tanpa satu kalimat pun. -->
                                <td class="px-5 py-4 text-sm text-gray-600 whitespace-nowrap">
                                    <!-- Tebalnya jatuh pada NILAI, bukan pada labelnya:
                                         yang dicari mata saat menyapu kolom ini adalah
                                         tanggalnya, dan "Mulai"/"Sampai" cuma penopang
                                         yang menjawab tanggal yang mana. -->
                                    <template v-if="rule.starts_on || rule.ends_on">
                                        <span class="block">
                                            <span class="text-xs text-gray-400">Mulai</span>
                                            <span class="font-medium text-gray-900">
                                                {{ rule.starts_on ? formatRuleDate(rule.starts_on) : 'sejak dibuat' }}
                                            </span>
                                        </span>
                                        <span class="block">
                                            <span class="text-xs text-gray-400">Sampai</span>
                                            <span class="font-medium text-gray-900">
                                                {{ rule.ends_on ? formatRuleDate(rule.ends_on) : 'dimatikan' }}
                                            </span>
                                        </span>
                                    </template>
                                    <span v-else class="font-medium text-gray-900">Selamanya</span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', statusClass(rule)]">
                                        {{ statusLabel(rule) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <!-- Tumpukan, bukan baris: tiga tombol berjajar
                                         memaksa kolomnya selebar tiga tombol, dan
                                         di layar sempit merekalah yang pertama
                                         terdorong keluar batas tabel. -->
                                    <div class="flex flex-col items-end gap-1.5">
                                        <button @click="toggle(rule)" class="w-24 px-2.5 py-1.5 text-center text-xs font-medium text-gray-700 bg-gray-100 border border-gray-200 rounded-lg hover:bg-gray-200 transition-colors">
                                            {{ rule.is_active ? 'Matikan' : 'Nyalakan' }}
                                        </button>
                                        <button @click="openEdit(rule)" class="w-24 px-2.5 py-1.5 text-center text-xs font-medium text-primary bg-primary/10 border border-primary/20 rounded-lg hover:bg-primary/20 transition-colors">
                                            Edit
                                        </button>
                                        <button @click="deleteTarget = rule" class="w-24 px-2.5 py-1.5 text-center text-xs font-medium text-destructive bg-destructive/10 border border-destructive/20 rounded-lg hover:bg-destructive/20 transition-colors">
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="rules && rules.length === 0">
                                <td colspan="6" class="px-5 py-12 text-center">
                                    <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500">Belum ada aturan buatan Anda</p>
                                    <p class="mt-1 text-xs text-gray-400">
                                        Kasir tetap menerima saran dari sistem.
                                    </p>
                                    <button @click="openCreate" class="mt-3 text-sm text-primary hover:text-primary/80 font-medium">
                                        Tambah aturan pertama
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Deferred>
        </div>

        <!-- ── Tab 2: apa yang benar-benar muncul di kasir hari ini ([BL-092])
             Dihitung dengan kode yang sama persis dengan layar kasir, memakai
             stok dan tanggal hari ini. -->
        <div v-show="activeTab === 'preview'">
            <Deferred data="preview">
                <template #fallback>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <SkeletonTable :rows="4" :columns="3" label="Menghitung saran yang muncul hari ini…" />
                    </div>
                </template>

                <div class="space-y-4">
                    <!-- Tanpa pemicu: inilah yang dilihat kasir pada penjualan apa pun. -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">Pada setiap penjualan</h3>
                        </div>

                        <ul v-if="preview.cart_level.length > 0" class="divide-y divide-gray-100">
                            <li
                                v-for="(slot, idx) in preview.cart_level"
                                :key="slot.key"
                                :class="['flex items-start gap-3 px-5 py-3', slot.wins_slot ? '' : 'bg-gray-50/60']"
                            >
                                <span
                                    :class="[
                                        'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold',
                                        slot.wins_slot ? 'bg-success/10 text-success' : 'bg-gray-200 text-gray-500',
                                    ]"
                                >
                                    {{ idx + 1 }}
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span :class="['rounded px-1.5 py-0.5 text-[10px] font-semibold', sourceBadge(slot).class]">
                                            {{ sourceBadge(slot).text }}
                                        </span>
                                        <span :class="['text-sm font-medium', slot.wins_slot ? 'text-gray-900' : 'text-gray-500']">
                                            {{ slot.label }}
                                        </span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ slot.note }}</p>
                                </div>

                                <span
                                    :class="[
                                        'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium',
                                        slot.wins_slot ? 'bg-success/10 text-success' : 'bg-gray-200 text-gray-600',
                                    ]"
                                >
                                    {{ slot.wins_slot ? 'Tampil' : 'Tergeser' }}
                                </span>
                            </li>
                        </ul>

                        <p v-else class="px-5 py-6 text-center text-sm text-gray-500">
                            Tidak ada saran tanpa pemicu hari ini.
                        </p>
                    </div>

                    <!-- Berpemicu: satu baris per barang pemicu, isinya hasil
                         perebutan slot untuk keranjang berisi barang itu saja. -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">Saat barang tertentu masuk keranjang</h3>
                        </div>

                        <div v-if="preview.triggers.length > 0" class="overflow-x-auto">
                            <table class="w-full">
                                <!-- Kepala kolomlah yang menerangkan tabel ini,
                                     bukan paragraf di atasnya: tanpa keduanya,
                                     dua kolom tanpa nama tidak memberi tahu mana
                                     yang sebab dan mana yang akibat. -->
                                <thead>
                                    <tr class="bg-gray-50 text-xs text-gray-500">
                                        <th class="w-1/3 px-5 py-2 text-left font-medium">Kalau ini masuk keranjang</th>
                                        <th class="px-5 py-2 text-left font-medium">Yang dilihat kasir</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="trigger in preview.triggers" :key="trigger.variant_id" class="align-top">
                                        <td class="px-5 py-3 text-sm text-gray-900 whitespace-nowrap w-1/3">
                                            {{ trigger.label }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <!-- Jenisnya ikut sebagai TEKS. Sebelumnya ia
                                                 hanya warna latar pil, dengan namanya
                                                 tersembunyi di tooltip — warna tanpa
                                                 keterangan tidak bisa dibaca siapa pun
                                                 yang belum hafal artinya. Susunannya
                                                 sama dengan daftar "Pada setiap
                                                 penjualan" di atas, supaya satu halaman
                                                 tidak punya dua bahasa visual. -->
                                            <div class="flex flex-wrap gap-1.5">
                                                <span
                                                    v-for="slot in trigger.slots.filter((s) => s.wins_slot)"
                                                    :key="slot.key"
                                                    class="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-white py-0.5 pl-1 pr-2.5"
                                                    :title="slot.note"
                                                >
                                                    <span :class="['whitespace-nowrap rounded px-1.5 py-0.5 text-[10px] font-semibold', sourceBadge(slot).class]">
                                                        {{ sourceBadge(slot).text }}
                                                    </span>
                                                    <span class="text-xs text-gray-800">{{ slot.label }}</span>
                                                </span>
                                            </div>
                                            <p
                                                v-if="trigger.slots.length > preview.max_per_transaction"
                                                class="mt-1 text-[11px] text-gray-400"
                                            >
                                                {{ trigger.slots.length - preview.max_per_transaction }} saran lain tergeser batas
                                                {{ preview.max_per_transaction }} slot.
                                            </p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p v-else class="px-5 py-6 text-center text-sm text-gray-500">
                            Belum ada saran berpemicu hari ini.
                        </p>

                        <p v-if="preview.triggers_truncated > 0" class="border-t border-gray-100 px-5 py-2.5 text-xs text-gray-500">
                            {{ preview.triggers_truncated }} barang pemicu lain tidak ditampilkan di sini.
                        </p>
                    </div>
                </div>
            </Deferred>
        </div>
    </div>

    <ConfirmDialog
        :show="!!deleteTarget"
        title="Hapus aturan ini?"
        message="Kasir tidak akan melihat saran ini lagi. Kalau hanya ingin menghentikannya sementara, pakai Matikan — aturannya tetap tersimpan."
        confirm-text="Hapus"
        variant="danger"
        @confirm="doDelete"
        @cancel="deleteTarget = null"
    />
</template>
