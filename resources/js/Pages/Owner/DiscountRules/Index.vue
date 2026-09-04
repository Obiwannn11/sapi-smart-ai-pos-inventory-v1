<script setup>
/**
 * Aturan Diskon ([BL-018]).
 *
 * Halaman ini ADALAH langkah persetujuannya. Sistem tidak pernah menurunkan
 * harga atas inisiatifnya sendiri — harga yang turun sendiri secara keliru
 * adalah uang yang keluar dan sukar ditarik kembali. Yang berlaku di kasir
 * hanyalah baris yang owner tuliskan di sini.
 *
 * Dua batas tak terlihat — lantai untung, dan barang kedaluwarsa yang tak
 * pernah didiskon — dulu diterangkan lewat paragraf di kepala halaman.
 * Paragrafnya dibaca sekali, oleh owner yang belum punya satu pun aturan,
 * lalu tidak pernah dibaca lagi tepat pada saat batas itu menggigit. Sekarang
 * keduanya DITUNJUK pada barisnya sendiri: sebab yang spesifik di kolom
 * status, dan penanda "Tertahan lantai" pada harga yang benar-benar terjepit.
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
    minMarginPercent: { type: Number, default: 10 },
    // Ditunda ([BL-037]) — null selama datanya masih dimuat.
    rules: { type: Array, default: null },
    variants: { type: Array, default: null },
});

/** Label pendek untuk tabel, tempat lebar kolomnya mahal. */
const triggerLabels = {
    near_expiry: 'Mendekati kedaluwarsa',
    dead_stock: 'Lama tak terjual',
    manual: 'Alasan sendiri',
};

/**
 * Label panjang untuk dropdown, tempat pilihannya harus menjelaskan dirinya.
 *
 * Kata "Tetap" dan "Membesar sendiri" ada di sini supaya perbedaan satu-satunya
 * yang berakibat — hanya `near_expiry` yang potongannya mendalam seiring
 * tanggal — terbaca saat owner memilih, bukan di paragraf bantuan di bawahnya.
 */
const triggerOptions = [
    { value: 'manual', label: 'Tetap — alasan saya sendiri' },
    { value: 'dead_stock', label: 'Tetap — barang lama tak terjual' },
    { value: 'near_expiry', label: 'Membesar sendiri mendekati kedaluwarsa' },
];

/**
 * Sebab sebuah aturan diam hari ini, dikirim server lewat `effective.reason`.
 *
 * Kelimanya dulu dilaporkan sebagai satu kalimat "Tidak berlaku (cek
 * stok/kedaluwarsa)" — yang menyuruh owner memeriksa dua hal, sementara tiga
 * sebab lainnya tidak disebut sama sekali.
 */
const REASON_LABELS = {
    expired: 'Barang sudah kedaluwarsa',
    unknown_cost: 'Modal belum diisi',
    no_cut_today: 'Potongan 0% hari ini',
    floor_absorbed: 'Habis dimakan lantai untung',
    cut_too_small: 'Potongan terlalu kecil',
    no_rule: 'Tidak berlaku',
};

const variantOptions = computed(() =>
    (props.variants ?? []).map((variant) => ({ value: variant.id, label: variant.label }))
);

const formatRupiah = (value) =>
    value === null || value === undefined ? '—' : 'Rp ' + Number(value).toLocaleString('id-ID');

const variantLabel = (variant) => {
    if (!variant) return '—';

    return variant.product ? `${variant.product.name} - ${variant.name}` : variant.name;
};

/** Kenapa sebuah aturan tidak berlaku hari ini, atau null bila berlaku. */
const dormantReason = (rule) => {
    if (!rule.is_active) return 'Dihentikan';

    // Hari toko ([BL-082]): `toISOString()` memberi tanggal UTC, sehingga
    // sepanjang pukul 00.00–08.00 WITA aturan yang mulai hari ini masih
    // dilaporkan "Belum mulai".
    const today = businessToday();

    if (rule.starts_on && rule.starts_on.slice(0, 10) > today) return 'Belum mulai';
    if (rule.ends_on && rule.ends_on.slice(0, 10) < today) return 'Sudah berakhir';

    // `effective.rule === null` berarti server menolak memberlakukannya, dan
    // `effective.reason` menyebut yang mana di antara lima sebabnya.
    if (rule.effective && !rule.effective.rule) {
        return REASON_LABELS[rule.effective.reason] ?? 'Tidak berlaku';
    }

    return null;
};

/** Ringkasan yang menggantikan paragraf pengantar: angka, bukan penjelasan. */
const ruleCounts = computed(() => {
    if (!props.rules) return null;

    const live = props.rules.filter((rule) => dormantReason(rule) === null).length;

    return { live, dormant: props.rules.length - live };
});

// --- Form ---
const showForm = ref(false);
const editingId = ref(null);

const form = useForm({
    product_variant_id: '',
    trigger: 'manual',
    percent: 10,
    max_percent: null,
    reason: '',
    starts_on: '',
    ends_on: '',
    is_active: true,
});

const selectedVariant = computed(() =>
    (props.variants ?? []).find((variant) => variant.id === form.product_variant_id) ?? null
);

/** Pratinjau harga, dihitung kasar hanya untuk memandu — server yang berkuasa. */
const preview = computed(() => {
    if (!selectedVariant.value || !form.percent) return null;

    const catalog = Number(selectedVariant.value.price);
    const floor = selectedVariant.value.floor;

    const raw = catalog * (1 - Number(form.percent) / 100);
    const rounded = Math.ceil(raw / 500) * 500;

    return {
        catalog,
        floor,
        // Tanpa lantai, server MENOLAK menurunkan harga sama sekali. Pratinjau
        // yang tetap memamerkan harga diskon di sini akan berselisih dengan
        // kenyataan begitu aturannya disimpan.
        blocked: floor === null,
        price: floor === null ? catalog : Math.max(rounded, Number(floor)),
        clamped: floor !== null && rounded < Number(floor),
    };
});

const openCreate = () => {
    form.reset();
    form.clearErrors();
    editingId.value = null;
    showForm.value = true;
};

const openEdit = (rule) => {
    form.product_variant_id = rule.product_variant_id;
    form.trigger = rule.trigger;
    form.percent = Number(rule.percent);
    form.max_percent = rule.max_percent === null ? null : Number(rule.max_percent);
    form.reason = rule.reason;
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
    const options = { preserveScroll: true, onSuccess: () => closeForm() };

    if (editingId.value) {
        form.put(`/owner/discount-rules/${editingId.value}`, options);
    } else {
        form.post('/owner/discount-rules', options);
    }
};

const toggleForm = useForm({});
const toggle = (rule) => toggleForm.post(`/owner/discount-rules/${rule.id}/toggle`, { preserveScroll: true });

const deleteTarget = ref(null);
const deleteForm = useForm({});

const doDelete = () => {
    if (!deleteTarget.value) return;

    deleteForm.delete(`/owner/discount-rules/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null; },
    });
};
</script>

<template>
    <Head title="Aturan Diskon" />

    <div class="max-w-5xl mx-auto">
        <div class="flex items-start justify-between mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Aturan Diskon</h1>
                <p v-if="ruleCounts" class="text-sm text-gray-500 mt-1">
                    {{ ruleCounts.live }} berlaku di kasir<template v-if="ruleCounts.dormant"> · {{ ruleCounts.dormant }} diam</template>
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
                            {{ editingId ? 'Edit Aturan Diskon' : 'Tambah Aturan Diskon' }}
                        </h3>

                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Barang *</label>
                                <SelectDropdown
                                    v-model="form.product_variant_id"
                                    :options="variantOptions"
                                    placeholder="Pilih barang"
                                    searchable
                                    :error="form.errors.product_variant_id"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Cara potongan bekerja *</label>
                                <SelectDropdown
                                    v-model="form.trigger"
                                    :options="triggerOptions"
                                    :error="form.errors.trigger"
                                />
                            </div>

                            <!-- Satu kontrol berpasangan, bukan dua field bernama
                                 "awal" dan "terdalam" yang butuh paragraf untuk
                                 menjelaskan hubungan di antara keduanya. -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Potongan *</label>
                                <div class="flex items-center gap-2">
                                    <div class="relative">
                                        <input
                                            v-model.number="form.percent"
                                            type="number" min="1" max="90" step="1"
                                            class="w-24 pl-3 pr-7 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                                        />
                                        <span class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-sm text-gray-400">%</span>
                                    </div>

                                    <template v-if="form.trigger === 'near_expiry'">
                                        <span class="text-gray-400" aria-hidden="true">&rarr;</span>
                                        <div class="relative">
                                            <input
                                                v-model.number="form.max_percent"
                                                type="number" min="1" max="90" step="1"
                                                class="w-24 pl-3 pr-7 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                                            />
                                            <span class="pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-sm text-gray-400">%</span>
                                        </div>
                                        <span class="text-xs text-gray-500">pada hari kedaluwarsa</span>
                                    </template>
                                </div>
                                <p v-if="form.errors.percent" class="mt-1 text-xs text-destructive">{{ form.errors.percent }}</p>
                                <p v-if="form.errors.max_percent" class="mt-1 text-xs text-destructive">{{ form.errors.max_percent }}</p>
                            </div>

                            <!-- Pratinjau. Menunjukkan jepitan lantai SEBELUM
                                 aturannya disimpan, bukan setelah owner bingung
                                 kenapa potongannya tidak sebesar yang ia tulis. -->
                            <div v-if="preview" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2.5 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Harga katalog</span>
                                    <span class="text-gray-700">{{ formatRupiah(preview.catalog) }}</span>
                                </div>
                                <div class="flex items-center justify-between mt-1">
                                    <!-- Rumusnya menempel pada angkanya, bukan di
                                         paragraf terpisah yang harus diingat. -->
                                    <a
                                        href="/owner/settings/operations"
                                        class="text-gray-500 underline decoration-gray-300 hover:decoration-gray-500"
                                    >Lantai untung (modal + {{ minMarginPercent }}%)</a>
                                    <span class="text-gray-700">{{ formatRupiah(preview.floor) }}</span>
                                </div>
                                <div class="flex items-center justify-between mt-1 font-medium">
                                    <span class="text-gray-700">Harga jadi</span>
                                    <span class="flex items-center gap-1.5">
                                        <span
                                            v-if="preview.clamped"
                                            class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800"
                                        >Tertahan lantai</span>
                                        <span :class="preview.blocked ? 'text-gray-500' : 'text-success'">
                                            {{ formatRupiah(preview.price) }}
                                        </span>
                                    </span>
                                </div>
                                <p v-if="preview.blocked" class="mt-1.5 text-amber-700">
                                    Modal barang ini belum diisi — potongan tidak akan berlaku.
                                </p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Catatan untuk laporan *</label>
                                <input
                                    v-model="form.reason"
                                    type="text" maxlength="120"
                                    placeholder="Contoh: Stok menumpuk menjelang akhir bulan"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                                />
                                <p v-if="form.errors.reason" class="mt-1 text-xs text-destructive">{{ form.errors.reason }}</p>
                                <p v-else class="mt-1 text-xs text-gray-500">
                                    Ikut tercatat pada setiap penjualan yang memakainya.
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mulai</label>
                                    <input v-model="form.starts_on" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Berakhir</label>
                                    <input v-model="form.ends_on" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring" />
                                    <p v-if="form.errors.ends_on" class="mt-1 text-xs text-destructive">{{ form.errors.ends_on }}</p>
                                </div>
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

        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <Deferred data="rules">
                <template #fallback>
                    <SkeletonTable :rows="5" :columns="5" label="Memuat aturan diskon…" />
                </template>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Barang</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Potongan</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Harga jadi</th>
                                <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="rule in rules" :key="rule.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-4">
                                    <span class="text-sm font-medium text-gray-900 block">{{ variantLabel(rule.variant) }}</span>
                                    <span class="text-xs text-gray-400">“{{ rule.reason }}”</span>
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-700 whitespace-nowrap">
                                    {{ Number(rule.percent) }}%<template v-if="rule.max_percent"> → {{ Number(rule.max_percent) }}%</template>
                                    <span class="block text-xs text-gray-400">{{ triggerLabels[rule.trigger] }}</span>
                                </td>
                                <td class="px-5 py-4 text-sm whitespace-nowrap">
                                    <template v-if="rule.effective && rule.effective.rule">
                                        <span class="text-xs text-gray-400 line-through mr-1">{{ formatRupiah(rule.variant?.price) }}</span>
                                        <span class="font-medium text-success">{{ formatRupiah(rule.effective.price) }}</span>
                                    </template>
                                    <span v-else class="text-gray-400">{{ formatRupiah(rule.variant?.price) }}</span>

                                    <!-- Lantai untung berhenti diterangkan di kepala
                                         halaman dan mulai ditunjuk di sini, pada baris
                                         yang potongannya benar-benar terjepit. -->
                                    <span v-if="rule.effective?.clamped" class="mt-0.5 block">
                                        <!-- Angka lantainya sengaja TIDAK diulang: baris yang
                                             terjepit punya harga jadi yang sama persis dengan
                                             lantainya, dan angka kembar berdampingan membuat
                                             pembacanya mencari beda yang tidak ada. -->
                                        <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">
                                            Tertahan lantai
                                        </span>
                                    </span>
                                    <span v-else class="block text-xs text-gray-400">lantai {{ formatRupiah(rule.effective?.floor) }}</span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                            dormantReason(rule) ? 'bg-muted text-muted-foreground' : 'bg-success/10 text-success',
                                        ]"
                                    >
                                        {{ dormantReason(rule) ?? 'Berlaku di kasir' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <!-- Tumpukan, bukan baris: tiga tombol berjajar
                                         memaksa kolomnya selebar tiga tombol, dan
                                         di layar sempit merekalah yang pertama
                                         terdorong keluar batas tabel. -->
                                    <div class="flex flex-col items-end gap-1.5">
                                        <button @click="toggle(rule)" class="w-24 px-2.5 py-1.5 text-center text-xs font-medium text-gray-700 bg-gray-100 border border-gray-200 rounded-lg hover:bg-gray-200 transition-colors">
                                            {{ rule.is_active ? 'Hentikan' : 'Jalankan' }}
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
                                <td colspan="5" class="px-5 py-12 text-center">
                                    <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 10V5a2 2 0 012-2z" />
                                    </svg>
                                    <p class="mt-2 text-sm text-gray-500">Belum ada aturan diskon</p>
                                    <p class="mt-1 text-xs text-gray-400">Kasir menjual pada harga katalog sampai Anda menambahkan aturan di sini.</p>
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
    </div>

    <ConfirmDialog
        :show="!!deleteTarget"
        title="Hapus aturan diskon ini?"
        message="Penjualan yang sudah memakainya tidak berubah — harga dan alasannya sudah tercatat pada tiap barisnya. Kalau hanya ingin menghentikan sementara, pakai Hentikan."
        confirm-text="Hapus"
        variant="danger"
        @confirm="doDelete"
        @cancel="deleteTarget = null"
    />
</template>
