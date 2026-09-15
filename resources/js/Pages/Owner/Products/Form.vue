<script setup>
import { ref, computed, nextTick } from 'vue';
import { useForm, Head, Link, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import ImageUpload from '@/Components/ImageUpload.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import Button from '@/Components/Button.vue';
import Checkbox from '@/Components/Checkbox.vue';
import DatePicker from '@/Components/DatePicker.vue';
import VariantFormModal from '@/Components/VariantFormModal.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    product: { type: Object, default: null },
    categories: Array,
    modifierGroups: Array,
});

const isEditing = computed(() => !!props.product);

const categoryOptions = computed(() => [
    { value: '', label: 'Tanpa Kategori' },
    ...props.categories.map(cat => ({ value: cat.id, label: cat.name })),
]);

// --- Product Form ---
const form = useForm({
    name: props.product?.name ?? '',
    category_id: props.product?.category_id ?? '',
    image: null,
    is_active: props.product?.is_active ?? true,
    modifier_group_ids: props.product?.modifier_groups?.map(g => g.id) ?? [],
    // Variants only for create
    variants: props.product ? [] : [{ name: 'Default', sku: '', price: '', cost_price: '', stock: 0, expiry_date: '' }],
});

// --- Variants for Create ---
const addVariant = () => {
    form.variants.push({ name: '', sku: '', price: '', cost_price: '', stock: 0, expiry_date: '' });
};

const removeVariant = (index) => {
    if (form.variants.length <= 1) return;
    form.variants.splice(index, 1);
};

// --- Submit ---
const submit = () => {
    if (isEditing.value) {
        // When form data contains files, Inertia automatically converts PUT to POST with _method=PUT
        form.transform(data => ({
            ...data,
            _method: 'PUT',
        })).post(`/owner/products/${props.product.id}`, {
            forceFormData: true,
            preserveScroll: true,
        });
    } else {
        form.post('/owner/products', {
            forceFormData: true,
        });
    }
};

// --- Add Variant (edit mode, modal) ---
const showAddVariant = ref(false);
const addVariantForm = useForm({
    name: '',
    sku: '',
    price: '',
    cost_price: '',
    stock: 0,
    expiry_date: '',
});

const openAddVariant = () => {
    addVariantForm.reset();
    addVariantForm.clearErrors();
    showAddVariant.value = true;
};

const closeAddVariant = () => {
    showAddVariant.value = false;
    addVariantForm.reset();
    addVariantForm.clearErrors();
};

const submitNewVariant = () => {
    addVariantForm.post(`/owner/products/${props.product.id}/variants`, {
        preserveScroll: true,
        onSuccess: () => closeAddVariant(),
    });
};

// --- Edit Variant (edit mode, modal) ---
const showEditVariant = ref(false);
const editingVariantId = ref(null);
const editVariantForm = useForm({
    name: '',
    sku: '',
    price: '',
    cost_price: '',
    stock: 0,
    expiry_date: '',
});

const openEditVariant = (variant) => {
    editingVariantId.value = variant.id;
    editVariantForm.name = variant.name;
    editVariantForm.sku = variant.sku || '';
    editVariantForm.price = Number(variant.price);
    editVariantForm.cost_price = Number(variant.cost_price);
    editVariantForm.stock = variant.stock;
    editVariantForm.expiry_date = variant.expiry_date || '';
    editVariantForm.clearErrors();
    showEditVariant.value = true;
};

const closeEditVariant = () => {
    showEditVariant.value = false;
    editingVariantId.value = null;
    editVariantForm.reset();
    editVariantForm.clearErrors();
};

const updateVariant = () => {
    // Stok dan tanggal hanya ditampilkan, tidak dikirim ([BL-111]); server
    // juga tidak menerimanya lagi.
    editVariantForm
        .transform(({ stock, expiry_date, ...editable }) => editable)
        .put(`/owner/products/${props.product.id}/variants/${editingVariantId.value}`, {
            preserveScroll: true,
            onSuccess: () => closeEditVariant(),
        });
};

/** Halaman Stok, tersaring ke produk ini, untuk mengubah stok variannya. */
const stockPageHref = computed(() => (
    props.product ? `/owner/stock?q=${encodeURIComponent(props.product.name)}` : null
));

// --- Currency formatting (CREATE mode inline variants) ---
const formatNumber = (value) => {
    const num = Number(String(value).replace(/\D/g, ''));
    if (!num) return '';
    return num.toLocaleString('id-ID');
};

const onVariantPriceInput = (i, field, event) => {
    const raw = event.target.value.replace(/\D/g, '');
    const num = Number(raw) || 0;
    form.variants[i][field] = num;
    nextTick(() => { event.target.value = num > 0 ? formatNumber(num) : ''; });
};

const onVariantPriceFocus = (i, field, event) => {
    const num = Number(form.variants[i][field]) || 0;
    event.target.value = num > 0 ? String(num) : '';
    event.target.select();
};

const onVariantPriceBlur = (i, field, event) => {
    const num = Number(form.variants[i][field]) || 0;
    event.target.value = num > 0 ? formatNumber(num) : '';
};

// --- Delete Variant (edit mode) ---
const deleteVariant = (variantId) => {
    if (!confirm('Hapus varian ini?')) return;
    router.delete(`/owner/products/${props.product.id}/variants/${variantId}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="isEditing ? `Edit: ${product.name}` : 'Tambah Produk'" />

    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
            <Link
                href="/owner/products"
                class="text-gray-400 hover:text-gray-600 transition-colors"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
            </Link>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ isEditing ? 'Edit Produk' : 'Tambah Produk Baru' }}
                </h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ isEditing ? 'Perbarui informasi produk' : 'Isi data produk dan varian' }}
                </p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Section 1: Info Produk -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Informasi Produk</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <!-- Nama -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nama Produk *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="Contoh: Nasi Goreng"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                            :class="{ 'border-red-300': form.errors.name }"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <!-- Kategori -->
                    <div>
                        <SelectDropdown
                            v-model="form.category_id"
                            :options="categoryOptions"
                            label="Kategori"
                            placeholder="Tanpa Kategori"
                            searchable
                            :error="form.errors.category_id"
                        />
                    </div>

                    <!-- Gambar -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gambar Produk</label>
                        <ImageUpload
                            v-model="form.image"
                            :current-image="product?.image_url"
                            :error="form.errors.image"
                        />
                    </div>

                    <!-- Aktif -->
                    <div class="flex items-center gap-3 md:col-span-2">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input v-model="form.is_active" type="checkbox" class="sr-only peer" />
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
                        </label>
                        <span class="text-sm font-medium text-gray-700">Produk Aktif</span>
                    </div>
                </div>
            </div>

            <!-- Section 2: Variants (CREATE mode) -->
            <div v-if="!isEditing" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">Varian Produk</h2>
                    <Button type="button" variant="soft" size="sm" @click="addVariant">
                        <template #icon>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </template>
                        Tambah Varian
                    </Button>
                </div>

                <p v-if="form.errors.variants" class="mb-3 text-sm text-red-600">{{ form.errors.variants }}</p>

                <div class="space-y-4">
                    <div
                        v-for="(variant, i) in form.variants"
                        :key="i"
                        class="border border-gray-200 rounded-lg p-4 relative"
                    >
                        <button
                            v-if="form.variants.length > 1"
                            type="button"
                            @click="removeVariant(i)"
                            class="absolute top-2 right-2 text-gray-400 hover:text-red-500"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Nama Varian *</label>
                                <input v-model="variant.name" type="text" placeholder="Default"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                                <p v-if="form.errors[`variants.${i}.name`]" class="mt-1 text-xs text-red-600">{{ form.errors[`variants.${i}.name`] }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">SKU</label>
                                <input v-model="variant.sku" type="text" placeholder="Opsional"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Harga Jual *</label>
                                <input
                                    type="text"
                                    inputmode="numeric"
                                    :value="Number(variant.price) > 0 ? formatNumber(variant.price) : ''"
                                    @input="onVariantPriceInput(i, 'price', $event)"
                                    @focus="onVariantPriceFocus(i, 'price', $event)"
                                    @blur="onVariantPriceBlur(i, 'price', $event)"
                                    placeholder="0"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                />
                                <p v-if="form.errors[`variants.${i}.price`]" class="mt-1 text-xs text-red-600">{{ form.errors[`variants.${i}.price`] }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Harga Modal *</label>
                                <input
                                    type="text"
                                    inputmode="numeric"
                                    :value="Number(variant.cost_price) > 0 ? formatNumber(variant.cost_price) : ''"
                                    @input="onVariantPriceInput(i, 'cost_price', $event)"
                                    @focus="onVariantPriceFocus(i, 'cost_price', $event)"
                                    @blur="onVariantPriceBlur(i, 'cost_price', $event)"
                                    placeholder="0"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                />
                                <p v-if="form.errors[`variants.${i}.cost_price`]" class="mt-1 text-xs text-red-600">{{ form.errors[`variants.${i}.cost_price`] }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Stok *</label>
                                <input v-model="variant.stock" type="number" min="0" placeholder="0"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring" />
                                <p v-if="form.errors[`variants.${i}.stock`]" class="mt-1 text-xs text-red-600">{{ form.errors[`variants.${i}.stock`] }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Expired</label>
                                <DatePicker v-model="variant.expiry_date" block clearable />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Variants (EDIT mode) -->
            <div v-if="isEditing" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">Varian Produk</h2>
                    <Button type="button" variant="soft" size="sm" @click="openAddVariant">
                        <template #icon>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                        </template>
                        Tambah Varian
                    </Button>
                </div>

                <!-- Existing variants -->
                <div class="space-y-3">
                    <div
                        v-for="variant in product.variants"
                        :key="variant.id"
                        class="border border-gray-200 rounded-lg p-4"
                    >
                        <div class="flex items-center justify-between">
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 flex-1">
                                <div>
                                    <span class="text-xs text-gray-400">Nama</span>
                                    <p class="text-sm font-medium">{{ variant.name }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-400">SKU</span>
                                    <p class="text-sm">{{ variant.sku || '-' }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-400">Harga Jual</span>
                                    <p class="text-sm font-semibold text-primary">Rp {{ Number(variant.price).toLocaleString('id-ID') }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-400">Modal</span>
                                    <p class="text-sm">Rp {{ Number(variant.cost_price).toLocaleString('id-ID') }}</p>
                                </div>
                                <div>
                                    <span class="text-xs text-gray-400">Stok</span>
                                    <p class="text-sm font-semibold" :class="variant.stock <= 5 ? 'text-red-600' : 'text-gray-800'">
                                        {{ variant.stock }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 ml-4">
                                <Button variant="soft" size="sm" @click="openEditVariant(variant)">Edit</Button>
                                <Button variant="destructiveSoft" size="sm" @click="deleteVariant(variant.id)">Hapus</Button>
                            </div>
                        </div>
                    </div>

                    <div v-if="!product.variants?.length" class="py-8 text-center text-sm text-gray-500">
                        Belum ada varian. Tambahkan varian menggunakan tombol di atas.
                    </div>
                </div>
            </div>

            <!-- Section 3: Modifier Groups -->
            <div v-if="modifierGroups?.length > 0" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Modifier Groups</h2>
                <p class="text-sm text-gray-500 mb-3">Pilih modifier group yang tersedia untuk produk ini</p>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    <Checkbox
                        v-for="group in modifierGroups"
                        :key="group.id"
                        v-model="form.modifier_group_ids"
                        :value="group.id"
                        :label="group.name"
                        variant="card"
                    />
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-between">
                <Button href="/owner/products" variant="ghost" size="md">
                    <template #icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </template>
                    Kembali ke daftar produk
                </Button>
                <Button type="submit" variant="primary" size="lg" :loading="form.processing">
                    {{ form.processing ? 'Menyimpan...' : (isEditing ? 'Perbarui Produk' : 'Simpan Produk') }}
                </Button>
            </div>
        </form>
    </div>

    <!-- Add Variant Modal (edit mode) -->
    <VariantFormModal
        :show="showAddVariant"
        title="Tambah Varian Baru"
        :form="addVariantForm"
        submit-label="Simpan Varian"
        @submit="submitNewVariant"
        @close="closeAddVariant"
    />

    <!-- Edit Variant Modal (edit mode) -->
    <VariantFormModal
        :show="showEditVariant"
        title="Edit Varian"
        :form="editVariantForm"
        stock-locked
        :stock-href="stockPageHref"
        submit-label="Perbarui Varian"
        @submit="updateVariant"
        @close="closeEditVariant"
    />
</template>
