<script setup>
import { ref, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    categories: Array,
});

// --- State ---
const showForm = ref(false);
const editingId = ref(null);
const deleteTarget = ref(null);

// --- Form ---
const form = useForm({ name: '' });

const openCreate = () => {
    form.reset();
    form.clearErrors();
    editingId.value = null;
    showForm.value = true;
};

const openEdit = (category) => {
    form.name = category.name;
    form.clearErrors();
    editingId.value = category.id;
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
        form.put(`/owner/categories/${editingId.value}`, {
            onSuccess: () => closeForm(),
            preserveScroll: true,
        });
    } else {
        form.post('/owner/categories', {
            onSuccess: () => closeForm(),
            preserveScroll: true,
        });
    }
};

// --- Delete ---
const confirmDelete = (category) => {
    deleteTarget.value = category;
};

const deleteForm = useForm({});

const doDelete = () => {
    if (!deleteTarget.value) return;
    deleteForm.delete(`/owner/categories/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            deleteTarget.value = null;
        },
    });
};

const cancelDelete = () => {
    deleteTarget.value = null;
};
</script>

<template>
    <Head title="Kategori" />

    <div class="max-w-4xl mx-auto">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Kategori</h1>
                <p class="text-sm text-gray-500 mt-1">Kelola kategori produk</p>
            </div>
            <button
                @click="openCreate"
                class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Kategori
            </button>
        </div>

        <!-- Inline Form (Create / Edit) -->
        <Transition
            enter-active-class="transition-all duration-200"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-150"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div v-if="showForm" class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">
                    {{ editingId ? 'Edit Kategori' : 'Tambah Kategori Baru' }}
                </h3>
                <form @submit.prevent="submit" class="flex items-start gap-3">
                    <div class="flex-1">
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="Nama kategori"
                            autofocus
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            :class="{ 'border-red-300 ring-red-200': form.errors.name }"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                    >
                        {{ form.processing ? 'Menyimpan...' : (editingId ? 'Perbarui' : 'Simpan') }}
                    </button>
                    <button
                        type="button"
                        @click="closeForm"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Batal
                    </button>
                </form>
            </div>
        </Transition>

        <!-- Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200">
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Nama Kategori</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wider">Jumlah Produk</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <tr v-for="category in categories" :key="category.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-5 py-4">
                            <span class="text-sm font-medium text-gray-900">{{ category.name }}</span>
                        </td>
                        <td class="px-5 py-4 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                {{ category.products_count }} produk
                            </span>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button
                                    @click="openEdit(category)"
                                    class="text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                                >
                                    Edit
                                </button>
                                <button
                                    @click="confirmDelete(category)"
                                    class="text-sm text-red-600 hover:text-red-800 font-medium"
                                >
                                    Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="categories.length === 0">
                        <td colspan="3" class="px-5 py-12 text-center">
                            <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">Belum ada kategori</p>
                            <button @click="openCreate" class="mt-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                Tambah kategori pertama
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
        title="Hapus Kategori"
        :message="`Apakah Anda yakin ingin menghapus kategori '${deleteTarget?.name}'? Produk di kategori ini akan dipindahkan ke 'Tanpa Kategori'.`"
        confirmText="Hapus"
        @confirm="doDelete"
        @cancel="cancelDelete"
    />
</template>
