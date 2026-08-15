<script setup>
import { ref, nextTick } from 'vue';
import { Deferred, useForm, Head, router } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import Accordion from '@/Components/Accordion.vue';
import SkeletonGrid from '@/Components/Skeleton/SkeletonGrid.vue';
import SkeletonCard from '@/Components/Skeleton/SkeletonCard.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    // Ditunda ([BL-037]) — null selama daftar grupnya masih dimuat.
    modifierGroups: { type: Array, default: null },
});

// --- Form State ---
const showForm = ref(false);
const editingGroup = ref(null);

const form = useForm({
    name: '',
    is_required: false,
    is_multiple: false,
    modifiers: [{ name: '', extra_price: 0 }],
});

const openCreate = () => {
    form.reset();
    form.clearErrors();
    form.modifiers = [{ name: '', extra_price: 0 }];
    editingGroup.value = null;
    showForm.value = true;
};

const openEdit = (group) => {
    editingGroup.value = group;
    form.name = group.name;
    form.is_required = group.is_required;
    form.is_multiple = group.is_multiple;
    form.modifiers = group.modifiers.map(m => ({
        id: m.id,
        name: m.name,
        extra_price: Number(m.extra_price),
    }));
    form.clearErrors();
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    editingGroup.value = null;
    form.reset();
    form.clearErrors();
};

const addModifier = () => {
    form.modifiers.push({ name: '', extra_price: 0 });
};

const removeModifier = (index) => {
    if (form.modifiers.length <= 1) return;
    form.modifiers.splice(index, 1);
};

const submit = () => {
    if (editingGroup.value) {
        form.put(`/owner/modifiers/${editingGroup.value.id}`, {
            onSuccess: () => closeForm(),
            preserveScroll: true,
        });
    } else {
        form.post('/owner/modifiers', {
            onSuccess: () => closeForm(),
            preserveScroll: true,
        });
    }
};

// --- Delete ---
const deleteTarget = ref(null);
const deleteForm = useForm({});

const confirmDelete = (group) => {
    deleteTarget.value = group;
};

const doDelete = () => {
    if (!deleteTarget.value) return;
    deleteForm.delete(`/owner/modifiers/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null; },
    });
};

const cancelDelete = () => {
    deleteTarget.value = null;
};

// --- Quick Settings Toggle ---
const togglingSettings = ref({});

const toggleSetting = (group, field) => {
    const key = `${group.id}-${field}`;
    if (togglingSettings.value[key]) return;
    togglingSettings.value[key] = true;

    router.patch(`/owner/modifiers/${group.id}/settings`, {
        is_required: field === 'is_required' ? !group.is_required : group.is_required,
        is_multiple: field === 'is_multiple' ? !group.is_multiple : group.is_multiple,
    }, {
        preserveScroll: true,
        onFinish: () => { delete togglingSettings.value[key]; },
    });
};

// --- Formatting ---
const formatCurrency = (val) => {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(val);
};

const formatNumber = (value) => {
    const num = Number(String(value).replace(/\D/g, ''));
    if (!num) return '';
    return num.toLocaleString('id-ID');
};

const onExtraPriceInput = (i, event) => {
    const raw = event.target.value.replace(/\D/g, '');
    const num = Number(raw) || 0;
    form.modifiers[i].extra_price = num;
    nextTick(() => { event.target.value = num > 0 ? formatNumber(num) : ''; });
};

const onExtraPriceFocus = (i, event) => {
    const num = Number(form.modifiers[i].extra_price) || 0;
    event.target.value = num > 0 ? String(num) : '';
    event.target.select();
};

const onExtraPriceBlur = (i, event) => {
    const num = Number(form.modifiers[i].extra_price) || 0;
    event.target.value = num > 0 ? formatNumber(num) : '';
};
</script>

<template>
    <Head title="Grup Modifier" />

    <div class="max-w-6xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Modifier Groups</h1>
                <p class="text-sm text-gray-500 mt-1">Kelola modifier/tambahan untuk produk</p>
            </div>
            <button
                @click="openCreate"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ring transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Group
            </button>
        </div>

        <!-- Create/Edit Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showForm" class="fixed inset-0 z-[100] flex items-start justify-center p-4 pt-20 overflow-y-auto">
                    <div class="absolute inset-0 bg-black/50" @click="closeForm" />
                    <div class="relative bg-white rounded-xl shadow-2xl max-w-lg w-full p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            {{ editingGroup ? 'Edit Modifier Group' : 'Tambah Modifier Group' }}
                        </h3>

                        <form @submit.prevent="submit" class="space-y-4">
                            <!-- Nama Group -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Group *</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Contoh: Level Pedas"
                                    autofocus
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    :class="{ 'border-red-300': form.errors.name }"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                            </div>

                            <!-- Toggles -->
                            <div class="flex items-center gap-6">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.is_required" type="checkbox" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-ring" />
                                    <span class="text-sm text-gray-700">Wajib dipilih</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.is_multiple" type="checkbox" class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-ring" />
                                    <span class="text-sm text-gray-700">Boleh pilih banyak</span>
                                </label>
                            </div>

                            <!-- Modifiers list -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="block text-sm font-medium text-gray-700">Modifier Items</label>
                                    <button type="button" @click="addModifier" class="text-xs text-primary hover:text-primary/80 font-medium">
                                        + Tambah Item
                                    </button>
                                </div>
                                <p v-if="form.errors.modifiers" class="mb-2 text-xs text-red-600">{{ form.errors.modifiers }}</p>

                                <div class="space-y-2">
                                    <div v-for="(mod, i) in form.modifiers" :key="i" class="flex items-start gap-2">
                                        <div class="flex-1">
                                            <input
                                                v-model="mod.name"
                                                type="text"
                                                placeholder="Nama modifier"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                                :class="{ 'border-red-300': form.errors[`modifiers.${i}.name`] }"
                                            />
                                            <p v-if="form.errors[`modifiers.${i}.name`]" class="mt-1 text-xs text-red-600">{{ form.errors[`modifiers.${i}.name`] }}</p>
                                        </div>
                                        <div class="w-32">
                                            <input
                                                type="text"
                                                inputmode="numeric"
                                                :value="Number(mod.extra_price) > 0 ? formatNumber(mod.extra_price) : ''"
                                                @input="onExtraPriceInput(i, $event)"
                                                @focus="onExtraPriceFocus(i, $event)"
                                                @blur="onExtraPriceBlur(i, $event)"
                                                placeholder="Gratis"
                                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                            />
                                        </div>
                                        <button
                                            v-if="form.modifiers.length > 1"
                                            type="button"
                                            @click="removeModifier(i)"
                                            class="mt-2 text-gray-400 hover:text-red-500"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    @click="closeForm"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                                >
                                    Batal
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="px-4 py-2 text-sm font-medium text-primary-foreground bg-primary rounded-lg hover:bg-primary/90 disabled:opacity-50"
                                >
                                    {{ form.processing ? 'Menyimpan...' : (editingGroup ? 'Perbarui' : 'Simpan') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Card Grid. Ditunda ([BL-037]) — grid dan jarak kartunya disalin
             apa adanya supaya kartunya tidak bergeser saat data tiba. -->
        <Deferred data="modifierGroups">
            <template #fallback>
                <SkeletonGrid
                    :count="4"
                    columns="grid-cols-1 lg:grid-cols-2"
                    gap="gap-5"
                    label="Memuat grup modifier…"
                >
                    <SkeletonCard :lines="3" footer padding="p-5" />
                </SkeletonGrid>
            </template>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div
                v-for="group in modifierGroups"
                :key="group.id"
                class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col hover:shadow-md hover:border-gray-300 transition-shadow"
            >
                <!-- Card header -->
                <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="text-sm font-semibold text-gray-900 truncate">{{ group.name }}</h3>
                            <span v-if="group.is_required" class="px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700 rounded">Wajib</span>
                            <span v-if="group.is_multiple" class="px-2 py-0.5 text-xs font-medium bg-primary/10 text-primary rounded">Multi</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">{{ group.modifiers?.length || 0 }} modifier · {{ group.products_count }} produk</p>
                    </div>
                    <div class="flex gap-2 flex-shrink-0">
                        <button @click="openEdit(group)" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-primary bg-primary/10 border border-primary/20 rounded-lg hover:bg-primary/20 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </button>
                        <button @click="confirmDelete(group)" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-destructive bg-destructive/10 border border-destructive/20 rounded-lg hover:bg-destructive/20 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Hapus
                        </button>
                    </div>
                </div>

                <!-- Card body -->
                <div class="p-5 space-y-5 flex-1">

                    <!-- Section 1: Pengaturan -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Pengaturan</h4>
                        <div class="flex flex-wrap gap-3">
                            <!-- Toggle: Wajib dipilih -->
                            <button
                                type="button"
                                @click="toggleSetting(group, 'is_required')"
                                :disabled="togglingSettings[`${group.id}-is_required`]"
                                class="flex items-center gap-3 px-4 py-2.5 rounded-lg border transition-colors"
                                :class="group.is_required
                                    ? 'bg-red-50 border-red-200 text-red-700'
                                    : 'bg-gray-50 border-gray-200 text-gray-500 hover:bg-gray-100'"
                            >
                                <!-- Toggle switch visual -->
                                <span
                                    class="relative inline-flex h-5 w-9 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200"
                                    :class="group.is_required ? 'bg-red-500' : 'bg-gray-300'"
                                >
                                    <span
                                        class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200"
                                        :class="group.is_required ? 'translate-x-4' : 'translate-x-0'"
                                    />
                                </span>
                                <div class="text-left">
                                    <p class="text-xs font-semibold leading-none">Wajib dipilih</p>
                                    <p class="text-xs mt-0.5 opacity-70">{{ group.is_required ? 'Pelanggan harus memilih' : 'Bersifat opsional' }}</p>
                                </div>
                            </button>

                            <!-- Toggle: Boleh pilih banyak -->
                            <button
                                type="button"
                                @click="toggleSetting(group, 'is_multiple')"
                                :disabled="togglingSettings[`${group.id}-is_multiple`]"
                                class="flex items-center gap-3 px-4 py-2.5 rounded-lg border transition-colors"
                                :class="group.is_multiple
                                    ? 'bg-primary/5 border-primary/30 text-primary'
                                    : 'bg-gray-50 border-gray-200 text-gray-500 hover:bg-gray-100'"
                            >
                                <span
                                    class="relative inline-flex h-5 w-9 flex-shrink-0 rounded-full border-2 border-transparent transition-colors duration-200"
                                    :class="group.is_multiple ? 'bg-primary' : 'bg-gray-300'"
                                >
                                    <span
                                        class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200"
                                        :class="group.is_multiple ? 'translate-x-4' : 'translate-x-0'"
                                    />
                                </span>
                                <div class="text-left">
                                    <p class="text-xs font-semibold leading-none">Boleh pilih banyak</p>
                                    <p class="text-xs mt-0.5 opacity-70">{{ group.is_multiple ? 'Multi-pilih aktif' : 'Hanya satu pilihan' }}</p>
                                </div>
                            </button>
                        </div>
                    </div>

                    <!-- Section 2: Item Modifier -->
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Item Modifier</h4>
                        <div v-if="group.modifiers?.length" class="rounded-lg border border-gray-100 divide-y divide-gray-50">
                            <div
                                v-for="(mod, idx) in group.modifiers"
                                :key="mod.id"
                                class="flex items-center justify-between gap-3 px-3 py-2 text-sm"
                            >
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-xs text-gray-400 w-4 flex-shrink-0">{{ idx + 1 }}</span>
                                    <span class="text-gray-700 font-medium truncate">{{ mod.name }}</span>
                                </div>
                                <span v-if="Number(mod.extra_price) > 0" class="font-medium text-gray-700 flex-shrink-0">+{{ formatCurrency(mod.extra_price) }}</span>
                                <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-50 text-green-700 flex-shrink-0">Gratis</span>
                            </div>
                        </div>
                        <div v-else class="py-3 text-sm text-gray-400 italic">Belum ada item modifier</div>
                    </div>

                    <!-- Section 3: Produk yang Menggunakan -->
                    <Accordion :count="group.products_count">
                        <template #title>
                            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Digunakan oleh Produk</span>
                        </template>

                        <div v-if="group.products?.length" class="flex flex-wrap gap-2">
                            <span
                                v-for="product in group.products"
                                :key="product.id"
                                class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700"
                            >
                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                                </svg>
                                {{ product.name }}
                            </span>
                        </div>
                        <p v-else class="text-sm text-gray-400 italic">Belum digunakan oleh produk manapun</p>
                    </Accordion>

                </div>
            </div>

            <!-- Empty state -->
            <div v-if="modifierGroups.length === 0" class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 py-16 text-center">
                <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
                <p class="mt-2 text-sm text-gray-500">Belum ada modifier group</p>
                <button @click="openCreate" class="mt-2 text-sm text-primary hover:text-primary/80 font-medium">
                    Tambah modifier group pertama
                </button>
            </div>
        </div>
        </Deferred>
    </div>

    <!-- Delete confirm -->
    <ConfirmDialog
        :show="!!deleteTarget"
        title="Hapus Modifier Group"
        :message="`Apakah Anda yakin ingin menghapus group '${deleteTarget?.name}'? Semua modifier di dalamnya juga akan dihapus.`"
        confirmText="Hapus"
        @confirm="doDelete"
        @cancel="cancelDelete"
    />
</template>
