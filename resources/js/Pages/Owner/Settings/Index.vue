<script setup>
import { computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import SettingsNav from '@/Components/SettingsNav.vue';
import SelectDropdown from '@/Components/SelectDropdown.vue';
import Button from '@/Components/Button.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    tenant: Object,
    businessTypes: { type: Object, default: () => ({}) },
});

const form = useForm({
    address:       props.tenant.address ?? '',
    phone:         props.tenant.phone ?? '',
    business_type: props.tenant.business_type ?? 'lainnya',
});

// Daftar jenis usaha datang sebagai peta nilai→label; SelectDropdown bekerja
// dengan daftar.
const businessTypeOptions = computed(() =>
    Object.entries(props.businessTypes).map(([value, label]) => ({ value, label })),
);

const submit = () => {
    form.patch('/owner/settings', { preserveScroll: true });
};
</script>

<template>
    <Head title="Profil & Merek" />

    <div class="max-w-2xl mx-auto">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Profil &amp; Merek</h1>
            <p class="text-sm text-gray-500 mt-1">Keterangan usaha yang tampil di header struk, dan jenis usaha Anda</p>
        </div>

        <SettingsNav current="profile" />

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
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring resize-none"
                        :class="{ 'border-destructive/50': form.errors.address }"
                    />
                    <p v-if="form.errors.address" class="mt-1 text-xs text-destructive">{{ form.errors.address }}</p>
                </div>

                <!-- No. Telepon -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">No. Telepon</label>
                    <input
                        v-model="form.phone"
                        type="text"
                        placeholder="Contoh: 021-1234567"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        :class="{ 'border-destructive/50': form.errors.phone }"
                    />
                    <p v-if="form.errors.phone" class="mt-1 text-xs text-destructive">{{ form.errors.phone }}</p>
                </div>

                <!-- Jenis Usaha -->
                <!-- Sebelumnya kolom ini hanya bisa diubah dari panel pengelola
                     layanan. Tempatnya di sini: yang tahu jenis usahanya adalah
                     Anda, dan keterangan usaha yang bisa diganti pihak lain
                     tanpa sepengetahuan pemiliknya bukan keterangan yang sehat.

                     Ia satu-satunya kolom di halaman ini yang ikut menggerakkan
                     uang, jadi ia satu-satunya yang diberi keterangan dampak —
                     kolom yang menentukan tarif tidak boleh terlihat sama tak
                     berbahayanya dengan kolom nomor telepon ([BL-039]). -->
                <div class="pt-5 border-t border-gray-200">
                    <SelectDropdown
                        v-model="form.business_type"
                        :options="businessTypeOptions"
                        label="Jenis Usaha"
                        :error="form.errors.business_type"
                    />
                    <p v-if="!form.errors.business_type" class="mt-1 text-xs text-gray-500 leading-relaxed">
                        Ikut menentukan tarif langganan Anda. Mengubahnya
                        <span class="font-medium text-gray-700">tidak mengubah tagihan yang sudah terbit</span> —
                        pengaruhnya baru terasa di periode berikutnya.
                    </p>
                </div>

                <!-- Submit -->
                <div class="flex justify-end pt-2">
                    <Button type="submit" size="lg" :loading="form.processing">
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Profil' }}
                    </Button>
                </div>
            </form>
        </div>
    </div>
</template>
