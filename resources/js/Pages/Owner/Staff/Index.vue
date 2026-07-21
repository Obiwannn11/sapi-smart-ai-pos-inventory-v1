<script setup>
import { ref, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    staff: Array,
    roles: Array,
});

const roleOptions = computed(() => [
    { value: '', label: 'Tanpa role (POS saja)' },
    ...props.roles.map((name) => ({ value: name, label: name })),
]);

// --- Form State ---
const showForm = ref(false);
const editingId = ref(null);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role_name: '',
});

const openCreate = () => {
    form.reset();
    form.clearErrors();
    editingId.value = null;
    showForm.value = true;
};

const openEdit = (member) => {
    form.name = member.name;
    form.email = member.email;
    form.password = '';
    form.role_name = member.roles?.[0] ?? '';
    form.clearErrors();
    editingId.value = member.id;
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    editingId.value = null;
    form.reset();
    form.clearErrors();
};

const submit = () => {
    if (editingId.value) {
        form.put(`/owner/staff/${editingId.value}`, {
            onSuccess: () => closeForm(),
            preserveScroll: true,
        });
    } else {
        form.post('/owner/staff', {
            onSuccess: () => closeForm(),
            preserveScroll: true,
        });
    }
};

// --- Delete ---
const deleteTarget = ref(null);
const deleteForm = useForm({});

const confirmDelete = (member) => { deleteTarget.value = member; };
const doDelete = () => {
    if (!deleteTarget.value) return;
    deleteForm.delete(`/owner/staff/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { deleteTarget.value = null; },
    });
};
const cancelDelete = () => { deleteTarget.value = null; };
</script>

<template>
    <Head title="Staf" />

    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Staf</h1>
                <p class="text-sm text-gray-500 mt-1">Kelola akun kasir dan role akses modulnya</p>
            </div>
            <button
                @click="openCreate"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ring transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Staf
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
                            {{ editingId ? 'Edit Staf' : 'Tambah Staf' }}
                        </h3>

                        <form @submit.prevent="submit" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nama *</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Nama staf"
                                    autofocus
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    :class="{ 'border-red-300': form.errors.name }"
                                />
                                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    placeholder="email@usaha.com"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    :class="{ 'border-red-300': form.errors.email }"
                                />
                                <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ editingId ? 'Password baru (kosongkan bila tak diubah)' : 'Password *' }}
                                </label>
                                <input
                                    v-model="form.password"
                                    type="password"
                                    placeholder="Minimal 8 karakter"
                                    autocomplete="new-password"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                                    :class="{ 'border-red-300': form.errors.password }"
                                />
                                <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                <SelectDropdown
                                    v-model="form.role_name"
                                    :options="roleOptions"
                                    :error="form.errors.role_name"
                                />
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

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Role</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="member in staff" :key="member.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-4">
                            <span class="text-sm font-medium text-gray-900">{{ member.name }}</span>
                        </td>
                        <td class="px-5 py-4">
                            <span class="text-sm text-gray-600">{{ member.email }}</span>
                        </td>
                        <td class="px-5 py-4">
                            <span
                                v-if="member.roles.length"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary"
                            >
                                {{ member.roles[0] }}
                            </span>
                            <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-muted text-muted-foreground">
                                POS saja
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button @click="openEdit(member)" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-primary bg-primary/10 border border-primary/20 rounded-lg hover:bg-primary/20 transition-colors">
                                    Edit
                                </button>
                                <button @click="confirmDelete(member)" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-destructive bg-destructive/10 border border-destructive/20 rounded-lg hover:bg-destructive/20 transition-colors">
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="staff.length === 0">
                        <td colspan="4" class="px-5 py-12 text-center">
                            <p class="text-sm text-gray-500">Belum ada staf</p>
                            <button @click="openCreate" class="mt-2 text-sm text-primary hover:text-primary/80 font-medium">
                                Tambah staf pertama
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Delete confirm -->
    <ConfirmDialog
        :show="!!deleteTarget"
        title="Hapus Staf"
        :message="`Hapus akun staf '${deleteTarget?.name}'? Tindakan ini tidak bisa dibatalkan.`"
        confirmText="Hapus"
        @confirm="doDelete"
        @cancel="cancelDelete"
    />
</template>
