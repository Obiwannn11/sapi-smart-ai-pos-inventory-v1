<script setup>
import { usePage } from '@inertiajs/vue3';
import { Head, Link } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';

defineOptions({ layout: OwnerLayout });

const { auth } = usePage().props;

const quickLinks = [
    { name: 'Kategori', href: '/owner/categories', desc: 'Kelola kategori produk', icon: 'folder' },
    { name: 'Produk', href: '/owner/products', desc: 'Kelola produk & varian', icon: 'cube' },
    { name: 'Modifier', href: '/owner/modifiers', desc: 'Kelola modifier produk', icon: 'adjustments' },
    { name: 'Pembayaran', href: '/owner/payment-methods', desc: 'Kelola metode pembayaran', icon: 'credit-card' },
];
</script>

<template>
    <Head title="Dashboard" />

    <div class="max-w-5xl mx-auto">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 mb-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Selamat Datang, {{ auth.user.name }}!</h2>
            <p class="text-gray-500">Kelola bisnis Anda dari dashboard ini. Fitur analitik lengkap akan tersedia di Phase 4 & 5.</p>
        </div>

        <h3 class="text-lg font-semibold text-gray-800 mb-4">Menu Cepat</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Link
                v-for="link in quickLinks"
                :key="link.href"
                :href="link.href"
                class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 hover:shadow-md hover:border-indigo-200 transition-all group"
            >
                <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center mb-3 group-hover:bg-indigo-100 transition-colors">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path v-if="link.icon === 'folder'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" />
                        <path v-else-if="link.icon === 'cube'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        <path v-else-if="link.icon === 'adjustments'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                        <path v-else-if="link.icon === 'credit-card'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                </div>
                <h4 class="text-sm font-semibold text-gray-900">{{ link.name }}</h4>
                <p class="text-xs text-gray-500 mt-1">{{ link.desc }}</p>
            </Link>
        </div>
    </div>
</template>
