<script setup>
import { ref } from 'vue';
import { Deferred, useForm, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import SkeletonTable from '@/Components/Skeleton/SkeletonTable.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    // Ditunda ([BL-037]) — null selama daftarnya masih dimuat.
    roles: { type: Array, default: null },
    // [{ name, label, sensitive }]
    modules: Array,
});

const moduleLabel = (name) => props.modules.find((m) => m.name === name)?.label ?? name;

// --- Form State ---
const showForm = ref(false);
const editingId = ref(null);

const form = useForm({
    name: '',
    modules: [],
});

const openCreate = () => {
    form.reset();
    form.clearErrors();
    // Default: semua modul tidak dicentang (Keputusan B — modul sensitif tak
    // boleh tercentang otomatis; owner memilih sendiri).
    form.modules = [];
    editingId.value = null;
    showForm.value = true;
};

const openEdit = (role) => {
    form.name = role.name;
    form.modules = [...role.modules];
    form.clearErrors();
    editingId.value = role.id;
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const toggleModule = (name) => {
    const i = form.modules.indexOf(name);
    if (i === -1) { form.modules.push(name); }
    else { form.modules.splice(i, 1); }
};

const submit = () => {
    if (editingId.value) {
        form.put(`/owner/roles/${editingId.value}`, {
            onSuccess: () => closeForm(),
            preserveScroll: true,
        });
    } else {
        form.post('/owner/roles', {
            onSuccess: () => closeForm(),
            preserveScroll: true,
        });
    }
};

// --- Delete ---
const deleteTarget = ref(null);
const deleteForm = useForm({});

const confirmDelete = (role) => { deleteTarget.value = role; };
const doDelete = () => {
    if (!deleteTarget.value) return;
    deleteForm.delete(`/owner/roles/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null; },
    });
};
const cancelDelete = () => { deleteTarget.value = null; };
</script>

<template>
    <Head title="Role" />

    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Role</h1>
                <p class="text-sm text-gray-500 mt-1">Buat kumpulan modul yang bisa diberikan ke staf</p>
            </div>
            <button
                @click="openCreate"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ring transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Role
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
                    <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            {{ editingId ? 'Edit Role' : 'Tambah Role' }}
                        </h3>

                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Role *</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Contoh: Kasir + Gudang"
                                    autofocus
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    :class="{ 'border-red-300': form.errors.name }"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Modul yang bisa dibuka</label>
                                <div class="space-y-2">
                                    <label
                                        v-for="mod in modules"
                                        :key="mod.name"
                                        class="flex items-center gap-3 px-3 py-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="form.modules.includes(mod.name)"
                                            @change="toggleModule(mod.name)"
                                            class="w-4 h-4 rounded border-gray-300 text-primary focus:ring-ring"
                                        />
                                        <span class="text-sm text-gray-800">{{ mod.label }}</span>
                                        <span
                                            v-if="mod.sensitive"
                                            class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-amber-100 text-amber-700"
                                        >
                                            Sensitif
                                        </span>
                                    </label>
                                </div>
                                <p v-if="form.errors.modules" class="mt-1 text-xs text-red-600">{{ form.errors.modules }}</p>
                            </div>

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
                                    {{ form.processing ? 'Menyimpan...' : (editingId ? 'Perbarui' : 'Simpan') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Table. Ditunda ([BL-037]) — kerangkanya memakai jumlah kolom
             yang sama supaya lebar kolom tidak berubah saat barisnya tiba. -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <Deferred data="roles">
                <template #fallback>
                    <SkeletonTable :rows="6" :columns="4" label="Memuat daftar role…" />
                </template>

            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Modul</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Staf</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="role in roles" :key="role.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-4">
                            <span class="text-sm font-medium text-gray-900">{{ role.name }}</span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="mod in role.modules"
                                    :key="mod"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700"
                                >
                                    {{ moduleLabel(mod) }}
                                </span>
                                <span v-if="role.modules.length === 0" class="text-xs text-gray-400">—</span>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <span class="text-sm text-gray-600">{{ role.users_count }}</span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button @click="openEdit(role)" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-primary bg-primary/10 border border-primary/20 rounded-lg hover:bg-primary/20 transition-colors">
                                    Edit
                                </button>
                                <button @click="confirmDelete(role)" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-destructive bg-destructive/10 border border-destructive/20 rounded-lg hover:bg-destructive/20 transition-colors">
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="roles.length === 0">
                        <td colspan="4" class="px-5 py-12 text-center">
                            <p class="text-sm text-gray-500">Belum ada role</p>
                            <button @click="openCreate" class="mt-2 text-sm text-primary hover:text-primary/80 font-medium">
                                Buat role pertama
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            </Deferred>
        </div>
    </div>

    <!-- Delete confirm -->
    <ConfirmDialog
        :show="!!deleteTarget"
        title="Hapus Role"
        :message="`Hapus role '${deleteTarget?.name}'?`"
        confirmText="Hapus"
        @confirm="doDelete"
        @cancel="cancelDelete"
    />
</template>
