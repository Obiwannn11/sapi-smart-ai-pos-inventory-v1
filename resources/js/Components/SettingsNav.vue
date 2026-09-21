<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * Penunjuk arah antar tiga halaman Pengaturan (`[BL-039]`).
 *
 * Sidebar sudah memuat ketiganya, tapi ia menghilang di layar kecil — dan
 * orang yang baru selesai mengganti jenis usaha di halaman ini biasanya
 * meneruskan ke halaman sebelahnya. Menaruh jalannya di tempat matanya
 * sudah berada lebih murah daripada menyuruhnya membuka menu.
 */
defineProps({
    current: { type: String, required: true },
});

const tabs = [
    { key: 'profile', label: 'Profil & Merek', href: '/owner/settings' },
    { key: 'operations', label: 'Cara Kerja Sistem', href: '/owner/settings/operations' },
    { key: 'integrations', label: 'Integrasi & Kredensial', href: '/owner/settings/integrations' },
];
</script>

<template>
    <nav class="flex gap-1 overflow-x-auto pb-px mb-6 border-b border-gray-200" aria-label="Pengaturan">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            class="shrink-0 px-3 py-2 text-sm font-medium border-b-2 -mb-px transition-colors"
            :class="tab.key === current
                ? 'border-primary text-primary'
                : 'border-transparent text-gray-500 hover:text-gray-800 hover:border-gray-300'"
            :aria-current="tab.key === current ? 'page' : undefined"
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
