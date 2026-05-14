<script setup>
import { useForm, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    tenant: Object,
});

const form = useForm({
    address: props.tenant.address ?? '',
    phone:   props.tenant.phone ?? '',
});

const submit = () => {
    form.patch('/owner/settings', { preserveScroll: true });
};
</script>

<template>
    <Head title="Pengaturan Usaha" />

    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Pengaturan Usaha</h1>
            <p class="text-sm text-gray-500 mt-1">Informasi usaha yang tampil di header struk</p>
        </div>

        <!-- Form Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <!-- Nama usaha (read-only) -->
            <div class="mb-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Usaha</label>
                <input
                    type="text"
                    :value="tenant.name"
                    disabled
                    class="w-full px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-500 cursor-not-allowed"
                />
                <p class="mt-1 text-xs text-gray-400">Nama usaha diatur saat pendaftaran.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-5">
                <!-- Alamat -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alamat</label>
                    <textarea
                        v-model="form.address"
                        rows="3"
                        placeholder="Contoh: Jl. Merdeka No. 1, Jakarta Pusat"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                        :class="{ 'border-red-300': form.errors.address }"
                    />
                    <p v-if="form.errors.address" class="mt-1 text-xs text-red-600">{{ form.errors.address }}</p>
                </div>

                <!-- No. Telepon -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. Telepon</label>
                    <input
                        v-model="form.phone"
                        type="text"
                        placeholder="Contoh: 021-1234567"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        :class="{ 'border-red-300': form.errors.phone }"
                    />
                    <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
                </div>

                <!-- Submit -->
                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition-colors"
                    >
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                        </svg>
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
