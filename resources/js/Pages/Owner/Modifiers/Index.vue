<script setup>
import { ref, nextTick } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    modifierGroups: Array,
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

// --- Accordion ---
const expandedId = ref(null);
const toggle = (id) => {
    expandedId.value = expandedId.value === id ? null : id;
};

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

    <div class="max-w-4xl mx-auto">
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

        <!-- Accordion List -->
        <div class="space-y-3">
            <div
                v-for="group in modifierGroups"
                :key="group.id"
                class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
            >
                <!-- Header -->
                <button
                    @click="toggle(group.id)"
                    class="w-full px-5 py-4 flex items-center justify-between hover:bg-gray-50 transition-colors"
                >
                    <div class="flex items-center gap-3">
                        <svg
                            class="w-4 h-4 text-gray-400 transition-transform"
                            :class="{ 'rotate-90': expandedId === group.id }"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span class="text-sm font-semibold text-gray-900">{{ group.name }}</span>
                        <span v-if="group.is_required" class="px-2 py-0.5 text-xs font-medium bg-red-100 text-red-700 rounded">Wajib</span>
                        <span v-if="group.is_multiple" class="px-2 py-0.5 text-xs font-medium bg-primary/10 text-primary rounded">Multi</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-400">{{ group.modifiers?.length || 0 }} modifier · {{ group.products_count }} produk</span>
                        <div class="flex gap-2" @click.stop>
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
                </button>

                <!-- Expanded content -->
                <Transition
                    enter-active-class="transition-all duration-200"
                    enter-from-class="max-h-0 opacity-0"
                    enter-to-class="max-h-96 opacity-100"
                    leave-active-class="transition-all duration-150"
                    leave-from-class="max-h-96 opacity-100"
                    leave-to-class="max-h-0 opacity-0"
                >
                    <div v-if="expandedId === group.id" class="overflow-hidden">
                        <div class="px-5 pb-4 border-t border-gray-100">
                            <table class="w-full mt-3">
                                <thead>
                                    <tr>
                                        <th class="text-left text-xs font-medium text-gray-500 pb-2">Nama Modifier</th>
                                        <th class="text-right text-xs font-medium text-gray-500 pb-2">Harga Tambahan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="mod in group.modifiers" :key="mod.id" class="text-sm">
                                        <td class="py-2 text-gray-700">{{ mod.name }}</td>
                                        <td class="py-2 text-right text-gray-600">
                                            <span v-if="Number(mod.extra_price) > 0">{{ formatCurrency(mod.extra_price) }}</span>
                                            <span v-else class="text-gray-400">Gratis</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-if="!group.modifiers?.length" class="py-4 text-center text-sm text-gray-400">
                                Belum ada modifier
                            </div>
                        </div>
                    </div>
                </Transition>
            </div>

            <!-- Empty state -->
            <div v-if="modifierGroups.length === 0" class="bg-white rounded-lg shadow-sm border border-gray-200 py-16 text-center">
                <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                </svg>
                <p class="mt-2 text-sm text-gray-500">Belum ada modifier group</p>
                <button @click="openCreate" class="mt-2 text-sm text-primary hover:text-primary/80 font-medium">
                    Tambah modifier group pertama
                </button>
            </div>
        </div>
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
