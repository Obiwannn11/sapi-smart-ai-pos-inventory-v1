<script setup>
import { usePage, router } from '@inertiajs/vue3';

const { auth } = usePage().props;

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <nav class="bg-white shadow px-6 py-4 flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-800">SAPI — Point of Sale</h1>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-600">{{ auth.user.name }}</span>
                <button
                    @click="logout"
                    class="text-sm text-red-600 hover:text-red-800"
                >
                    Logout
                </button>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto py-10 px-6">
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <h2 class="text-2xl font-semibold text-gray-700 mb-2">Point of Sale</h2>
                <p class="text-gray-500">POS interface akan dibangun di Phase 3.</p>
                <p class="text-gray-400 text-sm mt-4">Kasir: {{ auth.user.name }} | Tenant ID: {{ auth.user.tenant_id }}</p>
            </div>
        </main>
    </div>
</template>
