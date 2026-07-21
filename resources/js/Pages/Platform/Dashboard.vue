<script setup>
import { Head } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

defineProps({
    stats: { type: Object, required: true },
});
</script>

<template>
    <Head title="Beranda — Platform" />

    <PlatformLayout>
        <template #header>Beranda</template>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 max-w-3xl">
            <div class="rounded-xl border border-border bg-card p-5">
                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Tenant Terdaftar</p>
                <p class="mt-2 text-3xl font-bold text-foreground tabular-nums">{{ stats.tenant_count }}</p>
            </div>

            <div class="rounded-xl border border-border bg-card p-5">
                <p class="text-xs font-medium text-muted-foreground uppercase tracking-wide">Total Akun</p>
                <p class="mt-2 text-3xl font-bold text-foreground tabular-nums">{{ stats.user_count }}</p>
            </div>
        </div>

        <p class="mt-6 max-w-3xl text-sm text-muted-foreground leading-relaxed">
            Angka di atas hanya cacah administratif. Data langganan dan pembayaran menyusul di tahap berikutnya.
        </p>
    </PlatformLayout>
</template>
