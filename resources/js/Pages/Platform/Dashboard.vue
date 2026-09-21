<script setup>
import { Head } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import PageHeader from '@/Components/Platform/PageHeader.vue';
import StatCard from '@/Components/Platform/StatCard.vue';
import Notice from '@/Components/Platform/Notice.vue';

defineProps({
    stats: { type: Object, required: true },
});
</script>

<template>
    <Head title="Beranda — Platform" />

    <PlatformLayout>
        <PageHeader
            title="Beranda"
            description="Ringkasan administratif layanan. Angka bisnis klien tidak pernah muncul di sini."
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 max-w-3xl">
            <StatCard label="Tenant terdaftar" :value="stats.tenant_count" hint="Usaha yang punya akun di layanan ini." />
            <StatCard label="Total akun" :value="stats.user_count" hint="Seluruh pengguna di semua tenant." />
        </div>

        <Notice class="mt-6 max-w-3xl">
            Angka di atas hanya cacah administratif. Keadaan komersial tiap klien — paket, tarif, batas pengguna, dan
            tagihannya — ada di halaman Langganan &amp; Tagihan.
        </Notice>
    </PlatformLayout>
</template>
