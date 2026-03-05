<script setup>
import { ref, computed } from 'vue';
import { useForm, Head, Link, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import ImageUpload from '@/Components/ImageUpload.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    product: { type: Object, default: null },
    categories: Array,
    modifierGroups: Array,
});

const isEditing = computed(() => !!props.product);

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

// --- Modifier toggle ---
const toggleModifier = (id) => {
    const idx = form.modifier_group_ids.indexOf(id);
    if (idx >= 0) {
        form.modifier_group_ids.splice(idx, 1);
    } else {
        form.modifier_group_ids.push(id);
    }
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

// --- Edit Variant inline (for edit mode, separate API calls) ---
const editVariantForm = useForm({
    name: '',
    sku: '',
    price: '',
    cost_price: '',
    stock: 0,
    expiry_date: '',
});

const editingVariantId = ref(null);

const startEditVariant = (variant) => {
    editingVariantId.value = variant.id;
    editVariantForm.name = variant.name;
    editVariantForm.sku = variant.sku || '';
    editVariantForm.price = variant.price;
    editVariantForm.cost_price = variant.cost_price;
    editVariantForm.stock = variant.stock;
    editVariantForm.expiry_date = variant.expiry_date || '';
};

const cancelEditVariant = () => {
    editingVariantId.value = null;
    editVariantForm.reset();
};

const updateVariant = (variantId) => {
    editVariantForm.put(`/owner/products/${props.product.id}/variants/${variantId}`, {
        preserveScroll: true,
        onSuccess: () => cancelEditVariant(),
    });
};

// --- Add Variant (edit mode) ---
const showAddVariant = ref(false);
const addVariantForm = useForm({
    name: '',
    sku: '',
    price: '',
    cost_price: '',
    stock: 0,
    expiry_date: '',
});

const submitNewVariant = () => {
    addVariantForm.post(`/owner/products/${props.product.id}/variants`, {
        preserveScroll: true,
        onSuccess: () => {
            showAddVariant.value = false;
            addVariantForm.reset();
        },
    });
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
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="{ 'border-red-300': form.errors.name }"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <!-- Kategori -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                        <select
                            v-model="form.category_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        >
                            <option value="">Tanpa Kategori</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                        <p v-if="form.errors.category_id" class="mt-1 text-xs text-red-600">{{ form.errors.category_id }}</p>
                    </div>

                    <!-- Gambar -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Gambar Produk</label>
                        <ImageUpload
                            v-model="form.image"
                            :current-image="product?.image_url"
                            :error="form.errors.image"
                        />
                    </div>

                    <!-- Aktif -->
                    <div class="flex items-center gap-3 pt-6">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input v-model="form.is_active" type="checkbox" class="sr-only peer" />
                            <div class="w-11 h-6 bg-gray-200 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                        <span class="text-sm font-medium text-gray-700">Produk Aktif</span>
                    </div>
                </div>
            </div>

            <!-- Section 2: Variants (CREATE mode) -->
            <div v-if="!isEditing" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">Varian Produk</h2>
                    <button
                        type="button"
                        @click="addVariant"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        + Tambah Varian
                    </button>
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
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <p v-if="form.errors[`variants.${i}.name`]" class="mt-1 text-xs text-red-600">{{ form.errors[`variants.${i}.name`] }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">SKU</label>
                                <input v-model="variant.sku" type="text" placeholder="Opsional"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Harga Jual *</label>
                                <input v-model="variant.price" type="number" min="0" step="any" placeholder="0"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <p v-if="form.errors[`variants.${i}.price`]" class="mt-1 text-xs text-red-600">{{ form.errors[`variants.${i}.price`] }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Harga Modal *</label>
                                <input v-model="variant.cost_price" type="number" min="0" step="any" placeholder="0"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <p v-if="form.errors[`variants.${i}.cost_price`]" class="mt-1 text-xs text-red-600">{{ form.errors[`variants.${i}.cost_price`] }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Stok *</label>
                                <input v-model="variant.stock" type="number" min="0" placeholder="0"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                <p v-if="form.errors[`variants.${i}.stock`]" class="mt-1 text-xs text-red-600">{{ form.errors[`variants.${i}.stock`] }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Expired</label>
                                <input v-model="variant.expiry_date" type="date"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Variants (EDIT mode) -->
            <div v-if="isEditing" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-gray-800">Varian Produk</h2>
                    <button
                        type="button"
                        @click="showAddVariant = !showAddVariant"
                        class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                    >
                        {{ showAddVariant ? 'Batal' : '+ Tambah Varian' }}
                    </button>
                </div>

                <!-- Add variant form -->
                <div v-if="showAddVariant" class="border border-indigo-200 bg-indigo-50/50 rounded-lg p-4 mb-4">
                    <h4 class="text-sm font-medium text-gray-700 mb-3">Varian Baru</h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Nama *</label>
                            <input v-model="addVariantForm.name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <p v-if="addVariantForm.errors.name" class="mt-1 text-xs text-red-600">{{ addVariantForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">SKU</label>
                            <input v-model="addVariantForm.sku" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Harga Jual *</label>
                            <input v-model="addVariantForm.price" type="number" min="0" step="any" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <p v-if="addVariantForm.errors.price" class="mt-1 text-xs text-red-600">{{ addVariantForm.errors.price }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Harga Modal *</label>
                            <input v-model="addVariantForm.cost_price" type="number" min="0" step="any" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <p v-if="addVariantForm.errors.cost_price" class="mt-1 text-xs text-red-600">{{ addVariantForm.errors.cost_price }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Stok *</label>
                            <input v-model="addVariantForm.stock" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                            <p v-if="addVariantForm.errors.stock" class="mt-1 text-xs text-red-600">{{ addVariantForm.errors.stock }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Expired</label>
                            <input v-model="addVariantForm.expiry_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button
                            type="button"
                            @click="submitNewVariant"
                            :disabled="addVariantForm.processing"
                            class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {{ addVariantForm.processing ? 'Menyimpan...' : 'Simpan Varian' }}
                        </button>
                    </div>
                </div>

                <!-- Existing variants -->
                <div class="space-y-3">
                    <div
                        v-for="variant in product.variants"
                        :key="variant.id"
                        class="border border-gray-200 rounded-lg p-4"
                    >
                        <!-- View mode -->
                        <div v-if="editingVariantId !== variant.id" class="flex items-center justify-between">
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
                                    <p class="text-sm font-semibold text-indigo-600">Rp {{ Number(variant.price).toLocaleString('id-ID') }}</p>
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
                                <button type="button" @click="startEditVariant(variant)" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Edit</button>
                                <button type="button" @click="deleteVariant(variant.id)" class="text-xs text-red-600 hover:text-red-800 font-medium">Hapus</button>
                            </div>
                        </div>

                        <!-- Edit mode -->
                        <div v-else>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Nama *</label>
                                    <input v-model="editVariantForm.name" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">SKU</label>
                                    <input v-model="editVariantForm.sku" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Harga Jual *</label>
                                    <input v-model="editVariantForm.price" type="number" min="0" step="any" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Harga Modal *</label>
                                    <input v-model="editVariantForm.cost_price" type="number" min="0" step="any" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Stok *</label>
                                    <input v-model="editVariantForm.stock" type="number" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Expired</label>
                                    <input v-model="editVariantForm.expiry_date" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                                </div>
                            </div>
                            <div class="mt-3 flex justify-end gap-2">
                                <button type="button" @click="cancelEditVariant" class="px-3 py-1.5 text-sm text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">Batal</button>
                                <button
                                    type="button"
                                    @click="updateVariant(variant.id)"
                                    :disabled="editVariantForm.processing"
                                    class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {{ editVariantForm.processing ? 'Menyimpan...' : 'Simpan' }}
                                </button>
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
                    <label
                        v-for="group in modifierGroups"
                        :key="group.id"
                        :class="[
                            'flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-colors',
                            form.modifier_group_ids.includes(group.id)
                                ? 'border-indigo-300 bg-indigo-50'
                                : 'border-gray-200 hover:border-gray-300'
                        ]"
                    >
                        <input
                            type="checkbox"
                            :checked="form.modifier_group_ids.includes(group.id)"
                            @change="toggleModifier(group.id)"
                            class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                        />
                        <span class="text-sm font-medium text-gray-700">{{ group.name }}</span>
                    </label>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-between">
                <Link
                    href="/owner/products"
                    class="text-sm text-gray-600 hover:text-gray-800 font-medium"
                >
                    &larr; Kembali ke daftar produk
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                >
                    {{ form.processing ? 'Menyimpan...' : (isEditing ? 'Perbarui Produk' : 'Simpan Produk') }}
                </button>
            </div>
        </form>
    </div>
</template>
