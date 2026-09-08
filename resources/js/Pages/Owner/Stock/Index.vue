<script setup>
/**
 * Manajemen Stok — satu baris per varian.
 *
 * Bentuk sebelumnya adalah accordion per produk yang seluruhnya dibuka saat
 * halaman dimuat. Pada katalog kecil itu terlihat rapi; pada katalog besar ia
 * tiga kali salah: seluruh varian dikirim dalam satu prop, seluruhnya dirender
 * sekaligus, dan varian yang perlu ditindak tetap harus dicari dengan mata di
 * antara ratusan baris yang baik-baik saja.
 *
 * Yang menggantikannya adalah tabel rata: penyaringan, pengurutan, dan
 * paginasinya di server, dengan kartu status di atasnya sebagai penyaring
 * sekali-klik. Kartu itu bukan hiasan — ia yang menjawab pertanyaan yang
 * membawa pemilik ke halaman ini ("apa yang habis?"), tanpa menggulir.
 */
import { ref, reactive, computed, watch, nextTick } from 'vue';
import { Deferred, useForm, Head, Link, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Pagination from '@/Components/Pagination.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import SkeletonTable from '@/Components/Skeleton/SkeletonTable.vue';
import Skeleton from '@/Components/Skeleton/Skeleton.vue';
import { BUSINESS_TZ, businessToday, parseDateOnly } from '@/support/date';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    // Ditunda ([BL-037]) — null selama satu halaman variannya masih dimuat.
    variants: { type: Object, default: null },
    summary: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
});

/**
 * Batas "kritis". Kembarannya ada di `StockController::LOW_STOCK_THRESHOLD`,
 * yang menyaring dan mengurutkan barisnya; yang di sini hanya mewarnai lencana
 * baris yang sudah dipilih server. Keduanya harus bergerak bersama.
 */
const LOW_STOCK_THRESHOLD = 5;

const NEAR_EXPIRY_DAYS = 7;

// --- Penyaring ---

// Salinan lokal supaya kotak cari bisa diketik tanpa menunggu pulang-pergi
// permintaan; `props.filters` yang menang setiap server menjawab.
const form = reactive({ ...props.filters });

const isDefault = computed(() =>
    !form.q && !form.category && !form.status && form.sort === 'urgency'
);

const categoryOptions = computed(() => [
    { value: '', label: 'Semua Kategori' },
    { value: 'none', label: 'Tanpa Kategori' },
    ...props.categories.map((c) => ({ value: String(c.id), label: c.name })),
]);

/**
 * Pilihan jumlah baris per halaman. Kembarannya ada di
 * `StockController::PER_PAGE_OPTIONS`, yang menolak angka di luar daftar ini.
 */
const PER_PAGE_OPTIONS = [10, 20, 25, 30, 50];

const DEFAULT_PER_PAGE = 25;

/** Penunda ketikan pencarian, dan penanda "props sedang disalin ke form". */
let searchTimer = null;
let syncing = false;

/**
 * Kirim keadaan penyaring sekarang ke server.
 *
 * Yang masih bernilai bawaan tidak ikut ditulis ke query string: URL halaman
 * ini kerap dibagikan dan disimpan, dan `?status=&dir=asc&per_page=25` yang
 * tidak menyaring apa pun hanya membuatnya sulit dibaca.
 */
const applyFilters = () => {
    clearTimeout(searchTimer);

    const params = {};
    if (form.q) params.q = form.q;
    if (form.category) params.category = form.category;
    if (form.status) params.status = form.status;
    if (form.sort !== 'urgency') params.sort = form.sort;
    if (form.dir !== 'asc') params.dir = form.dir;
    if (form.per_page !== DEFAULT_PER_PAGE) params.per_page = form.per_page;

    router.get('/owner/stock', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Mengetik memicu permintaan, jadi ia ditahan dulu: satu permintaan per kata,
// bukan satu per huruf.
watch(() => form.q, () => {
    if (syncing) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 350);
});

watch([() => form.category, () => form.per_page], () => {
    if (! syncing) applyFilters();
});

// Tombol maju-mundur peramban mengganti props tanpa melewati `applyFilters`.
// `syncing` menahan pengamat di atas selama penyalinan itu: tanpanya, kembali
// ke halaman sebelumnya justru mengirim permintaan baru untuk keadaan yang
// barusan tiba — dan pencariannya ikut terkirim ulang 350 ms kemudian.
watch(() => props.filters, (next) => {
    syncing = true;
    clearTimeout(searchTimer);
    Object.assign(form, next);
    nextTick(() => { syncing = false; });
});

const setStatus = (status) => {
    form.status = form.status === status ? '' : status;
    applyFilters();
};

const sortBy = (column) => {
    // Kolom yang sama ditekan dua kali membalik arah; kolom baru selalu mulai
    // menaik — untuk stok dan kedaluwarsa, menaik berarti yang paling
    // mendesak lebih dulu.
    form.dir = form.sort === column && form.dir === 'asc' ? 'desc' : 'asc';
    form.sort = column;
    applyFilters();
};

const resetFilters = () => {
    Object.assign(form, { q: '', category: '', status: '', sort: 'urgency', dir: 'asc' });
    applyFilters();
};

// --- Kartu status ---

const STATUS_CARDS = [
    { key: '', label: 'Semua Varian', tone: 'neutral' },
    { key: 'out', label: 'Stok Habis', tone: 'danger' },
    { key: 'low', label: 'Stok Kritis', tone: 'warning' },
    { key: 'near_expiry', label: 'Dekat Expired', tone: 'warning' },
    { key: 'expired', label: 'Kedaluwarsa', tone: 'danger' },
    { key: 'ok', label: 'Aman', tone: 'success' },
];

const cardCount = (key) => (key === '' ? props.summary?.total : props.summary?.[key]) ?? 0;

const TONE_ACTIVE = {
    neutral: 'border-gray-400 bg-gray-50 ring-1 ring-gray-300',
    danger: 'border-destructive bg-destructive/5 ring-1 ring-destructive/30',
    warning: 'border-warning bg-warning/5 ring-1 ring-warning/40',
    success: 'border-success bg-success/5 ring-1 ring-success/30',
};

const TONE_VALUE = {
    neutral: 'text-gray-900',
    danger: 'text-destructive',
    warning: 'text-warning-foreground',
    success: 'text-success',
};

// --- Modal ---
const showRestockModal = ref(false);
const showAdjustModal = ref(false);
const selectedVariant = ref(null);

const restockForm = useForm({
    qty: '',
    notes: '',
    expiry_date: '',
});

const adjustForm = useForm({
    qty: '',
    notes: '',
});

const openRestock = (variant) => {
    selectedVariant.value = variant;
    restockForm.reset();
    restockForm.clearErrors();
    showRestockModal.value = true;
};

const closeRestock = () => {
    showRestockModal.value = false;
    selectedVariant.value = null;
};

const submitRestock = () => {
    restockForm.post(`/owner/stock/${selectedVariant.value.id}/restock`, {
        preserveScroll: true,
        onSuccess: () => closeRestock(),
    });
};

const openAdjust = (variant) => {
    selectedVariant.value = variant;
    adjustForm.reset();
    adjustForm.clearErrors();
    showAdjustModal.value = true;
};

const closeAdjust = () => {
    showAdjustModal.value = false;
    selectedVariant.value = null;
};

const submitAdjust = () => {
    adjustForm.post(`/owner/stock/${selectedVariant.value.id}/adjust`, {
        preserveScroll: true,
        onSuccess: () => closeAdjust(),
    });
};

// --- Helper ---
const isLowStock = (stock) => stock > 0 && stock <= LOW_STOCK_THRESHOLD;
const isOutOfStock = (stock) => stock <= 0;

// Kedaluwarsa adalah tanggal, bukan cap waktu: dibandingkan per HARI TOKO
// ([BL-082]). Sebelumnya `new Date('2026-08-22')` diurai sebagai tengah malam
// UTC lalu diadu dengan jam perangkat, sehingga barang yang kedaluwarsa hari
// ini masih terhitung aman sampai pukul 08.00.
const daysUntilExpiry = (expiryDate) =>
    (parseDateOnly(expiryDate) - parseDateOnly(businessToday())) / 86400000;

const isNearExpiry = (expiryDate) => {
    if (!expiryDate) return false;
    const diff = daysUntilExpiry(expiryDate);

    return diff >= 0 && diff <= NEAR_EXPIRY_DAYS;
};

const isExpired = (expiryDate) => {
    if (!expiryDate) return false;

    return daysUntilExpiry(expiryDate) < 0;
};

const expiryLabel = (expiryDate) => {
    if (!expiryDate) return null;
    const diff = Math.round(daysUntilExpiry(expiryDate));
    if (diff < 0) return `Lewat ${Math.abs(diff)} hari`;
    if (diff === 0) return 'Hari ini';

    return `${diff} hari lagi`;
};

const formatDate = (date) => {
    if (!date) return '-';

    return new Date(date).toLocaleDateString('id-ID', {
        timeZone: BUSINESS_TZ,
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Manajemen Stok" />

    <div class="max-w-7xl mx-auto space-y-5">
        <!-- Header -->
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Manajemen Stok</h1>
                <p class="text-sm text-gray-500 mt-1">Restock, adjustment, dan pantau stok produk</p>
            </div>
            <Link
                href="/owner/stock/movements"
                class="inline-flex items-center gap-2 px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ring transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <span class="hidden sm:inline">Semua Riwayat</span>
            </Link>
        </div>

        <!-- Kartu status — penyaring, bukan papan angka. Menekannya menyaring
             tabel di bawahnya; menekannya lagi melepas penyaringnya. -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 sm:gap-3">
            <button
                v-for="card in STATUS_CARDS"
                :key="card.key || 'all'"
                type="button"
                :aria-pressed="form.status === card.key"
                :aria-label="`${card.label}: ${cardCount(card.key)} varian`"
                :class="[
                    'text-left px-3 py-2.5 rounded-xl border bg-white shadow-sm transition-all',
                    'hover:border-gray-300 hover:shadow focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-ring',
                    form.status === card.key ? TONE_ACTIVE[card.tone] : 'border-gray-200',
                ]"
                @click="setStatus(card.key)"
            >
                <p class="text-xs font-medium text-gray-500 truncate">{{ card.label }}</p>
                <p v-if="summary" class="mt-0.5 text-xl font-bold tabular-nums" :class="TONE_VALUE[card.tone]">
                    {{ cardCount(card.key) }}
                </p>
                <Skeleton v-else class="mt-1.5 h-5 w-8" rounded="sm" />
            </button>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-3">
            <div class="relative flex-1 min-w-[220px] max-w-md">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                </svg>
                <input
                    v-model="form.q"
                    type="text"
                    placeholder="Cari produk, varian, atau SKU..."
                    class="w-full pl-9 pr-9 py-2 bg-white border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                />
                <button
                    v-if="form.q"
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 p-1 text-gray-400 hover:text-gray-600"
                    aria-label="Hapus pencarian"
                    @click="form.q = ''"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <SelectDropdown v-model="form.category" :options="categoryOptions" searchable class="w-52" />

            <button
                v-if="!isDefault"
                type="button"
                class="px-3 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                @click="resetFilters"
            >
                Reset
            </button>

            <p v-if="variants" class="ml-auto text-xs text-gray-500 tabular-nums">
                {{ variants.total }} varian
            </p>
        </div>

        <!-- Tabel. Ditunda ([BL-037]) bersama kartunya — kerangkanya juga yang
             muncul setiap penyaringnya diubah. -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <Deferred :data="['variants', 'summary']">
                <template #fallback>
                    <SkeletonTable :rows="8" :columns="5" label="Memuat daftar stok…" />
                </template>

                <template v-if="variants.data.length > 0">
                    <!-- Layar lebar: tabel. Kepalanya menempel supaya nama
                         kolomnya tetap terbaca pada halaman berisi 100 baris. -->
                    <div class="hidden md:block overflow-x-auto max-h-[calc(100vh-22rem)] overflow-y-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 sticky top-0 z-10">
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-2.5 px-4">
                                        <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 uppercase tracking-wider hover:text-gray-900" @click="sortBy('product')">
                                            Produk
                                            <span v-if="form.sort === 'product'" class="text-primary">{{ form.dir === 'asc' ? '↑' : '↓' }}</span>
                                        </button>
                                    </th>
                                    <th class="text-left py-2.5 px-4">
                                        <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 uppercase tracking-wider hover:text-gray-900" @click="sortBy('variant')">
                                            Varian
                                            <span v-if="form.sort === 'variant'" class="text-primary">{{ form.dir === 'asc' ? '↑' : '↓' }}</span>
                                        </button>
                                    </th>
                                    <th class="text-center py-2.5 px-4">
                                        <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 uppercase tracking-wider hover:text-gray-900" @click="sortBy('stock')">
                                            Stok
                                            <span v-if="form.sort === 'stock'" class="text-primary">{{ form.dir === 'asc' ? '↑' : '↓' }}</span>
                                        </button>
                                    </th>
                                    <th class="text-center py-2.5 px-4">
                                        <button type="button" class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 uppercase tracking-wider hover:text-gray-900" @click="sortBy('expiry')">
                                            Kedaluwarsa
                                            <span v-if="form.sort === 'expiry'" class="text-primary">{{ form.dir === 'asc' ? '↑' : '↓' }}</span>
                                        </button>
                                    </th>
                                    <th class="text-right py-2.5 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr
                                    v-for="variant in variants.data"
                                    :key="variant.id"
                                    class="hover:bg-gray-50 transition-colors"
                                >
                                    <td class="py-3 px-4">
                                        <p class="font-medium text-gray-900">{{ variant.product_name }}</p>
                                        <p class="text-xs text-gray-500">{{ variant.category_name || 'Tanpa Kategori' }}</p>
                                    </td>
                                    <td class="py-3 px-4">
                                        <p class="text-gray-800">{{ variant.name }}</p>
                                        <p class="text-xs text-gray-400 font-mono">{{ variant.sku || '—' }}</p>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <span
                                            v-if="isOutOfStock(variant.stock)"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-destructive/10 text-destructive"
                                        >
                                            Habis
                                        </span>
                                        <span
                                            v-else-if="isLowStock(variant.stock)"
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-warning/10 text-warning-foreground tabular-nums"
                                        >
                                            {{ variant.stock }} — Kritis
                                        </span>
                                        <span v-else class="font-semibold text-gray-800 tabular-nums">
                                            {{ variant.stock }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <template v-if="variant.expiry_date">
                                            <span
                                                v-if="isExpired(variant.expiry_date)"
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-destructive/10 text-destructive"
                                            >
                                                {{ formatDate(variant.expiry_date) }}
                                            </span>
                                            <span
                                                v-else-if="isNearExpiry(variant.expiry_date)"
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-warning/10 text-warning-foreground"
                                            >
                                                {{ formatDate(variant.expiry_date) }}
                                            </span>
                                            <span v-else class="text-gray-600">{{ formatDate(variant.expiry_date) }}</span>
                                            <p class="text-xs text-gray-400 mt-0.5">{{ expiryLabel(variant.expiry_date) }}</p>
                                        </template>
                                        <span v-else class="text-gray-300">—</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-success bg-success/10 border border-success/20 rounded-lg hover:bg-success/20 transition-colors"
                                                title="Restock"
                                                @click="openRestock(variant)"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                                Restock
                                            </button>
                                            <button
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-warning-foreground bg-warning/10 border border-warning/20 rounded-lg hover:bg-warning/20 transition-colors"
                                                title="Adjustment"
                                                @click="openAdjust(variant)"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" />
                                                </svg>
                                                Adjust
                                            </button>
                                            <Link
                                                :href="`/owner/stock/${variant.id}/history`"
                                                class="inline-flex items-center justify-center p-1.5 text-gray-500 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 hover:text-gray-700 transition-colors"
                                                title="Riwayat"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                                </svg>
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Layar sempit: kartu. Tabel lima kolom di tablet berarti
                         menggulir mendatar untuk mencapai tombol aksinya. -->
                    <ul class="md:hidden divide-y divide-gray-100">
                        <li v-for="variant in variants.data" :key="variant.id" class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-gray-900 truncate">{{ variant.product_name }}</p>
                                    <p class="text-sm text-gray-600 truncate">{{ variant.name }}</p>
                                    <p class="text-xs text-gray-400 font-mono">{{ variant.sku || '—' }}</p>
                                </div>
                                <span
                                    v-if="isOutOfStock(variant.stock)"
                                    class="shrink-0 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-destructive/10 text-destructive"
                                >
                                    Habis
                                </span>
                                <span
                                    v-else-if="isLowStock(variant.stock)"
                                    class="shrink-0 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-warning/10 text-warning-foreground tabular-nums"
                                >
                                    {{ variant.stock }} — Kritis
                                </span>
                                <span v-else class="shrink-0 text-sm font-semibold text-gray-800 tabular-nums">
                                    {{ variant.stock }}
                                </span>
                            </div>

                            <p v-if="variant.expiry_date" class="mt-2 text-xs" :class="isExpired(variant.expiry_date) ? 'text-destructive' : isNearExpiry(variant.expiry_date) ? 'text-warning-foreground' : 'text-gray-500'">
                                Kedaluwarsa {{ formatDate(variant.expiry_date) }} · {{ expiryLabel(variant.expiry_date) }}
                            </p>

                            <div class="mt-3 flex items-center gap-2">
                                <button
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-success bg-success/10 border border-success/20 rounded-lg"
                                    @click="openRestock(variant)"
                                >
                                    Restock
                                </button>
                                <button
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-warning-foreground bg-warning/10 border border-warning/20 rounded-lg"
                                    @click="openAdjust(variant)"
                                >
                                    Adjust
                                </button>
                                <Link
                                    :href="`/owner/stock/${variant.id}/history`"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-gray-600 bg-gray-50 border border-gray-200 rounded-lg"
                                >
                                    Riwayat
                                </Link>
                            </div>
                        </li>
                    </ul>
                </template>

                <div v-else class="py-16 text-center">
                    <svg class="mx-auto w-14 h-14 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                    </svg>
                    <p class="mt-3 text-sm text-gray-500">
                        {{ isDefault ? 'Belum ada varian produk' : 'Tidak ada varian yang cocok dengan penyaring ini' }}
                    </p>
                    <button
                        v-if="!isDefault"
                        type="button"
                        class="mt-3 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                        @click="resetFilters"
                    >
                        Hapus penyaring
                    </button>
                </div>

                <!-- Paginasi. Kakinya muncul selama ada baris, bukan hanya
                     saat halamannya lebih dari satu: pemilih jumlah baris ada
                     di sini, dan menyembunyikannya pada halaman tunggal
                     berarti 50 baris tidak pernah bisa dikecilkan lagi. -->
                <div v-if="variants.data.length > 0" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-500 tabular-nums">
                        Menampilkan {{ variants.from }}–{{ variants.to }} dari {{ variants.total }}
                    </p>

                    <div class="flex items-center gap-1">
                        <span class="text-xs text-gray-400 mr-1">Baris</span>
                        <button
                            v-for="size in PER_PAGE_OPTIONS"
                            :key="size"
                            type="button"
                            :aria-pressed="form.per_page === size"
                            :class="[
                                'px-2.5 py-1 text-xs rounded-lg tabular-nums transition-colors',
                                form.per_page === size
                                    ? 'bg-gray-800 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200',
                            ]"
                            @click="form.per_page = size"
                        >
                            {{ size }}
                        </button>
                    </div>

                    <Pagination :paginator="variants" :summary="false" />
                </div>
            </Deferred>
        </div>
    </div>

    <!-- Restock Modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showRestockModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="closeRestock" />
                <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Restock</h3>
                    <p class="text-sm text-gray-500 mb-5">
                        {{ selectedVariant?.product_name }} — {{ selectedVariant?.name }}
                        <span class="text-gray-400">(Stok saat ini: {{ selectedVariant?.stock }})</span>
                    </p>

                    <form class="space-y-4" @submit.prevent="submitRestock">
                        <!-- Qty -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Jumlah Restock <span class="text-red-500">*</span></label>
                            <input
                                v-model="restockForm.qty"
                                type="number"
                                min="1"
                                placeholder="Masukkan jumlah"
                                autofocus
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                                :class="{ 'border-red-300': restockForm.errors.qty }"
                            />
                            <p v-if="restockForm.errors.qty" class="mt-1 text-xs text-red-600">{{ restockForm.errors.qty }}</p>
                        </div>

                        <!-- Expiry Date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Kedaluwarsa</label>
                            <DatePicker v-model="restockForm.expiry_date" block clearable />
                            <p v-if="restockForm.errors.expiry_date" class="mt-1 text-xs text-red-600">{{ restockForm.errors.expiry_date }}</p>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                            <textarea
                                v-model="restockForm.notes"
                                rows="2"
                                placeholder="Contoh: Restock dari supplier A"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent resize-none"
                                :class="{ 'border-red-300': restockForm.errors.notes }"
                            />
                            <p v-if="restockForm.errors.notes" class="mt-1 text-xs text-red-600">{{ restockForm.errors.notes }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                                @click="closeRestock"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="restockForm.processing"
                                class="px-4 py-2 text-sm font-medium text-success-foreground bg-success rounded-lg hover:bg-success/90 disabled:opacity-50 transition-colors"
                            >
                                {{ restockForm.processing ? 'Menyimpan...' : 'Restock' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- Adjustment Modal -->
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showAdjustModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="closeAdjust" />
                <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Adjustment Stok</h3>
                    <p class="text-sm text-gray-500 mb-5">
                        {{ selectedVariant?.product_name }} — {{ selectedVariant?.name }}
                        <span class="text-gray-400">(Stok saat ini: {{ selectedVariant?.stock }})</span>
                    </p>

                    <form class="space-y-4" @submit.prevent="submitAdjust">
                        <!-- Qty -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Jumlah Adjustment <span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="adjustForm.qty"
                                type="number"
                                placeholder="Positif (+) atau negatif (-)"
                                autofocus
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                                :class="{ 'border-red-300': adjustForm.errors.qty }"
                            />
                            <p v-if="adjustForm.errors.qty" class="mt-1 text-xs text-red-600">{{ adjustForm.errors.qty }}</p>
                            <p class="mt-1 text-xs text-gray-400">Masukkan angka positif untuk menambah, negatif untuk mengurangi</p>
                        </div>

                        <!-- Notes (wajib) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Alasan <span class="text-red-500">*</span>
                            </label>
                            <textarea
                                v-model="adjustForm.notes"
                                rows="2"
                                placeholder="Contoh: Bahan expired dibuang, audit fisik, dll"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent resize-none"
                                :class="{ 'border-red-300': adjustForm.errors.notes }"
                            />
                            <p v-if="adjustForm.errors.notes" class="mt-1 text-xs text-red-600">{{ adjustForm.errors.notes }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end gap-3 pt-2">
                            <button
                                type="button"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                                @click="closeAdjust"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="adjustForm.processing"
                                class="px-4 py-2 text-sm font-medium text-warning-foreground bg-warning rounded-lg hover:bg-warning/90 disabled:opacity-50 transition-colors"
                            >
                                {{ adjustForm.processing ? 'Menyimpan...' : 'Simpan Adjustment' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
