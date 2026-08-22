<script setup>
/**
 * Aturan Saran Jual — tempat owner menuliskan targetnya sendiri ([BL-074]).
 *
 * Halaman ini menjawab satu keluhan yang tepat: seluruh saran jual hari ini
 * DITEMUKAN MESIN — ko-okurensi modifier, barang tertekan stok, naik ukuran
 * berdasarkan selisih harga. Orang yang paling tahu barangnya sendiri belum
 * punya satu pun tempat untuk mengatakan "bulan ini dorong kopi susu botol".
 *
 * Dua bentuk aturan, dan bedanya sengaja dijelaskan di layar, bukan hanya di
 * kode: aturan BERPEMICU muncul saat barang tertentu masuk keranjang; aturan
 * TANPA PEMICU muncul di setiap penjualan. Owner memilih di antara keduanya
 * dengan satu dropdown, bukan dengan memahami dua konsep.
 */
import { ref, computed } from 'vue';
import { Deferred, useForm, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import SkeletonTable from '@/Components/Skeleton/SkeletonTable.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import { businessToday } from '@/support/date';

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
 * Warnanya sengaja sama dengan strip di layar kasir: owner yang membandingkan
 * layar ini dengan layar kasirnya tidak sedang membaca dua sistem berbeda.
 */
const SOURCE_BADGES = {
    manual: { text: 'Aturan Anda', class: 'bg-emerald-100 text-emerald-700' },
    attach: { text: 'Tambah add-on', class: 'bg-sky-100 text-sky-700' },
    pressed_stock: { text: 'Barang tertekan', class: 'bg-amber-100 text-amber-800' },
    upsize: { text: 'Naik ukuran', class: 'bg-violet-100 text-violet-700' },
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

const variantLabel = (variant) => {
    if (!variant) return '—';

    return variant.product ? `${variant.product.name} - ${variant.name}` : variant.name;
};

/** Kenapa sebuah aturan tidak muncul di kasir hari ini, atau null bila muncul. */
const dormantReason = (rule) => {
    if (!rule.is_active) return 'Dimatikan';

    // Hari toko ([BL-082]): `toISOString()` memberi tanggal UTC, sehingga
    // sepanjang pukul 00.00–08.00 WITA aturan yang mulai hari ini masih
    // dilaporkan "Belum mulai".
    const today = businessToday();

    if (rule.starts_on && rule.starts_on.slice(0, 10) > today) {
        return 'Belum mulai';
    }
    if (rule.ends_on && rule.ends_on.slice(0, 10) < today) {
        return 'Sudah berakhir';
    }
    // Penjaga kandidat berlaku tanpa pengecualian, termasuk untuk aturan yang
    // owner tulis sendiri. Menampilkannya di sini mencegah kesimpulan "fiturnya
    // rusak" saat yang sebenarnya terjadi adalah stoknya nol.
    if (rule.suggested_variant && rule.suggested_variant.stock <= 0) {
        return 'Stok barangnya habis';
    }

    return null;
};

// --- Form ---
const showForm = ref(false);
const editingId = ref(null);

const form = useForm({
    trigger_variant_id: '',
    suggested_variant_id: '',
    note: '',
    starts_on: '',
    ends_on: '',
    priority: 0,
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
    form.priority = rule.priority;
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
    const options = { preserveScroll: true, onSuccess: () => closeForm() };

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
        <div class="flex items-start justify-between mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Aturan Saran Jual</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Saran yang <strong>Anda</strong> tentukan sendiri, di samping saran yang ditemukan sistem dari data penjualan.
                    Aturan di sini selalu tampil lebih dulu.
                </p>
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

        <!-- Batas yang tidak bisa ditembus aturan manual. Ditulis di sini supaya
             owner tidak menyimpulkan fiturnya rusak saat sarannya tidak muncul. -->
        <div class="mb-5 flex gap-2.5 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-900">
            <svg class="mt-px h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>
                Aturan tetap tunduk pada penjaga barang: barang yang <strong>stoknya habis, sudah kedaluwarsa, atau produknya nonaktif</strong>
                tidak akan disarankan walaupun tertulis di sini. Kasir juga menampilkan paling banyak
                <strong>3 saran</strong> per penjualan, dan aturan Anda mengisi slotnya lebih dulu.
            </span>
        </div>

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
                                <p class="mt-1 text-xs text-gray-500">
                                    Pilih satu barang agar saran hanya muncul saat barang itu masuk keranjang,
                                    atau biarkan "setiap penjualan" untuk mendorong sesuatu sepanjang periode.
                                </p>
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
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                                />
                                <p v-if="form.errors.note" class="mt-1 text-xs text-destructive">{{ form.errors.note }}</p>
                                <p v-else class="mt-1 text-xs text-gray-500">
                                    Tampil apa adanya di layar kasir. Kosongkan bila tidak perlu.
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mulai</label>
                                    <input
                                        v-model="form.starts_on"
                                        type="date"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Berakhir</label>
                                    <input
                                        v-model="form.ends_on"
                                        type="date"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                                    />
                                    <p v-if="form.errors.ends_on" class="mt-1 text-xs text-destructive">{{ form.errors.ends_on }}</p>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 -mt-2">
                                Boleh disetel dari jauh hari — aturan baru muncul di kasir pada tanggal mulainya.
                                Kosongkan keduanya agar berlaku terus sampai dimatikan.
                            </p>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Urutan</label>
                                <input
                                    v-model.number="form.priority"
                                    type="number"
                                    min="0"
                                    max="999"
                                    class="w-32 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                                />
                                <p class="mt-1 text-xs text-gray-500">
                                    Angka lebih besar tampil lebih dulu — hanya berpengaruh sesama aturan Anda sendiri.
                                </p>
                            </div>

                            <label class="flex items-center gap-3">
                                <input v-model="form.is_active" type="checkbox" class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-ring" />
                                <span class="text-sm font-medium text-gray-700">Aktif</span>
                            </label>

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

        <!-- Tabel. Ditunda ([BL-037]) — kerangkanya memakai jumlah kolom yang
             sama supaya lebar kolom tidak berubah saat barisnya tiba. -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <Deferred data="rules">
                <template #fallback>
                    <SkeletonTable :rows="5" :columns="5" label="Memuat aturan saran jual…" />
                </template>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Pemicu</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Disarankan</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Berlaku</th>
                                <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="rule in rules" :key="rule.id" class="hover:bg-gray-50 transition-colors">
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
                                <td class="px-5 py-4 text-sm text-gray-600 whitespace-nowrap">
                                    <template v-if="rule.starts_on || rule.ends_on">
                                        {{ rule.starts_on ? rule.starts_on.slice(0, 10) : '…' }}
                                        &ndash;
                                        {{ rule.ends_on ? rule.ends_on.slice(0, 10) : '…' }}
                                    </template>
                                    <span v-else class="text-gray-400">Selamanya</span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                            dormantReason(rule) ? 'bg-muted text-muted-foreground' : 'bg-success/10 text-success',
                                        ]"
                                    >
                                        {{ dormantReason(rule) ?? 'Tampil di kasir' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button @click="toggle(rule)" class="px-2.5 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 border border-gray-200 rounded-lg hover:bg-gray-200 transition-colors">
                                            {{ rule.is_active ? 'Matikan' : 'Nyalakan' }}
                                        </button>
                                        <button @click="openEdit(rule)" class="px-2.5 py-1.5 text-xs font-medium text-primary bg-primary/10 border border-primary/20 rounded-lg hover:bg-primary/20 transition-colors">
                                            Edit
                                        </button>
                                        <button @click="deleteTarget = rule" class="px-2.5 py-1.5 text-xs font-medium text-destructive bg-destructive/10 border border-destructive/20 rounded-lg hover:bg-destructive/20 transition-colors">
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="rules && rules.length === 0">
                                <td colspan="5" class="px-5 py-12 text-center">
                                    <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500">Belum ada aturan buatan Anda</p>
                                    <p class="mt-1 text-xs text-gray-400">
                                        Kasir tetap menerima saran dari sistem. Aturan di sini menambahkan saran yang Anda pilih sendiri.
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

        <!-- Pratinjau slot kasir ([BL-092]).
             Tabel di atas hanya memperlihatkan separuh kenyataan — aturan yang
             Anda tulis. Bagian ini memperlihatkan separuh lainnya: saran yang
             ditemukan sistem dari stok, dan siapa yang sebenarnya mengisi
             ketiga slot kasir hari ini. -->
        <div class="mt-8">
            <h2 class="text-lg font-semibold text-gray-900">Yang Muncul di Kasir Hari Ini</h2>
            <p class="text-sm text-gray-500 mt-1 mb-4">
                Aturan Anda dan saran otomatis sistem berebut slot yang sama. Daftar ini dihitung dengan cara
                yang sama persis seperti layar kasir, memakai stok dan tanggal hari ini.
            </p>

            <Deferred data="preview">
                <template #fallback>
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <SkeletonTable :rows="4" :columns="3" label="Menghitung saran yang muncul hari ini…" />
                    </div>
                </template>

                <div class="space-y-4">
                    <!-- Saklar mati adalah penjelasan pertama yang owner butuhkan,
                         bukan daftar kosong tanpa sebab. -->
                    <div v-if="!preview.enabled" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Saran jual sedang <strong>dimatikan seluruhnya</strong> di setelan sistem. Kasir tidak menerima
                        saran apa pun, termasuk aturan yang Anda tulis di atas.
                    </div>

                    <div
                        v-else-if="disabledTypeNames"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900"
                    >
                        Jenis saran berikut sedang dimatikan di setelan sistem: <strong>{{ disabledTypeNames }}</strong>.
                    </div>

                    <!-- Tanpa pemicu: inilah yang dilihat kasir pada penjualan apa pun. -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">Pada setiap penjualan</h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Saran yang tidak menunggu barang pemicu — aturan tanpa pemicu, dan barang tertekan
                                stok yang ditemukan sistem.
                            </p>
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
                            Tidak ada saran tanpa pemicu hari ini. Kasir hanya melihat saran saat barang pemicunya
                            masuk keranjang.
                        </p>
                    </div>

                    <!-- Berpemicu: satu baris per barang pemicu, isinya hasil
                         perebutan slot untuk keranjang berisi barang itu saja. -->
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100">
                            <h3 class="text-sm font-semibold text-gray-800">Saat barang tertentu masuk keranjang</h3>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Isi kolom kanan adalah maksimal {{ preview.max_per_transaction }} saran yang menang slot
                                bila keranjang hanya berisi barang di kolom kiri.
                            </p>
                        </div>

                        <div v-if="preview.triggers.length > 0" class="overflow-x-auto">
                            <table class="w-full">
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="trigger in preview.triggers" :key="trigger.variant_id" class="align-top">
                                        <td class="px-5 py-3 text-sm text-gray-900 whitespace-nowrap w-1/3">
                                            {{ trigger.label }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="flex flex-wrap gap-1.5">
                                                <span
                                                    v-for="slot in trigger.slots.filter((s) => s.wins_slot)"
                                                    :key="slot.key"
                                                    :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs', sourceBadge(slot).class]"
                                                    :title="slot.note"
                                                >
                                                    {{ slot.label }}
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
