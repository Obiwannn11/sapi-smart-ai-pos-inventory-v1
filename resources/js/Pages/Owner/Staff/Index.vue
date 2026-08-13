<script setup>
import { ref, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    staff: Array,
    owners: { type: Array, default: () => [] },
    roles: Array,
    modules: { type: Array, default: () => [] },
    seats: Object,
});

const moduleLabel = (name) => props.modules.find((m) => m.name === name)?.label ?? name;

// `['*']` dari `User::modulePermissions()`. Dibaca dari payload, bukan
// disimpulkan dari "baris ini baris owner": kalau suatu saat ada akun lain yang
// melewati pemeriksaan, ia akan tampil benar tanpa halaman ini diubah.
const bypassesEveryCheck = (row) => row.modules?.includes('*') ?? false;

const seatsFull = computed(() => props.seats.used >= props.seats.total);

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

// --- Aktif / nonaktif ---
const toggleForm = useForm({});

const toggleActive = (member) => {
    toggleForm.patch(`/owner/staff/${member.id}/active`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Staf" />

    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Staf</h1>
                <p class="text-sm text-gray-500 mt-1">
                    Kelola akun kasir dan role akses modulnya ·
                    <span :class="seatsFull ? 'font-medium text-destructive' : ''">
                        {{ seats.used }} dari {{ seats.total }} pengguna aktif
                    </span>
                </p>
            </div>
            <button
                @click="openCreate"
                :disabled="seatsFull"
                :title="seatsFull ? 'Semua seat paket Anda sudah terpakai' : undefined"
                class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ring transition-colors disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-primary"
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
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Role &amp; modul</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <!--
                        Baris owner. Dibaca saja: tidak ada Edit, Nonaktifkan,
                        atau Hapus, karena tak satu pun dari ketiganya berlaku
                        untuk pemilik akun — `authorizeStaff()` di server memang
                        menolaknya, dan tombol yang selalu ditolak lebih buruk
                        daripada tombol yang tidak ada.
                    -->
                    <tr v-for="owner in owners" :key="`owner-${owner.id}`" class="bg-primary/[0.04]">
                        <td class="px-5 py-4">
                            <span class="text-sm font-medium text-gray-900">{{ owner.name }}</span>
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[0.65rem] font-medium bg-primary/10 text-primary">
                                Pemilik
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            <span class="text-sm text-gray-600">{{ owner.email }}</span>
                        </td>
                        <td class="px-5 py-4">
                            <template v-if="bypassesEveryCheck(owner)">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                    Akses penuh — melewati seluruh pemeriksaan
                                </span>
                                <p class="mt-1 text-xs text-gray-500">
                                    Aksesnya tidak berasal dari role, jadi mengubah role tidak akan membatasinya.
                                </p>
                            </template>
                            <div v-else class="flex flex-wrap gap-1">
                                <span
                                    v-for="mod in owner.modules"
                                    :key="mod"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700"
                                >
                                    {{ moduleLabel(mod) }}
                                </span>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <span class="text-xs text-gray-400">—</span>
                        </td>
                    </tr>

                    <tr v-for="member in staff" :key="member.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-4">
                            <span class="text-sm font-medium" :class="member.is_active ? 'text-gray-900' : 'text-gray-400'">
                                {{ member.name }}
                            </span>
                            <span
                                v-if="!member.is_active"
                                class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-[0.65rem] font-medium bg-muted text-muted-foreground"
                            >
                                Nonaktif
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            <span class="text-sm text-gray-600">{{ member.email }}</span>
                        </td>
                        <td class="px-5 py-4">
                            <!--
                                Nama role tetap ada, tapi sebagai ASAL-USUL —
                                yang menjawab "orang ini bisa buka apa saja"
                                adalah lencana modul di bawahnya, bukan namanya.
                            -->
                            <span
                                v-if="member.roles.length"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary"
                            >
                                {{ member.roles[0] }}
                            </span>
                            <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-muted text-muted-foreground">
                                Tanpa role
                            </span>

                            <div v-if="member.modules.length" class="mt-1.5 flex flex-wrap gap-1">
                                <span
                                    v-for="mod in member.modules"
                                    :key="mod"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700"
                                >
                                    {{ moduleLabel(mod) }}
                                </span>
                            </div>
                            <p v-else class="mt-1.5 text-xs text-gray-400">
                                Belum ada modul — hanya bisa membuka kasir.
                            </p>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button @click="openEdit(member)" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-primary bg-primary/10 border border-primary/20 rounded-lg hover:bg-primary/20 transition-colors">
                                    Edit
                                </button>
                                <button
                                    @click="toggleActive(member)"
                                    :disabled="!member.is_active && seatsFull"
                                    :title="!member.is_active && seatsFull ? 'Semua seat paket Anda sudah terpakai' : undefined"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-gray-700 bg-gray-100 border border-gray-200 rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-gray-100"
                                >
                                    {{ member.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                                <button @click="confirmDelete(member)" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-destructive bg-destructive/10 border border-destructive/20 rounded-lg hover:bg-destructive/20 transition-colors">
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="staff.length === 0">
                        <td colspan="4" class="px-5 py-12 text-center">
                            <p class="text-sm text-gray-500">Belum ada staf selain pemilik</p>
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
