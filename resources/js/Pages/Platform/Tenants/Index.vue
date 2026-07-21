<script setup>
import { Head } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

defineProps({
    tenants: { type: Object, required: true },
});
</script>

<template>
    <Head title="Daftar Tenant — Platform" />

    <PlatformLayout>
        <template #header>Daftar Tenant</template>

        <div class="rounded-xl border border-border bg-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-accent/50 text-left">
                        <tr class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <th class="px-4 py-3">Nama Usaha</th>
                            <th class="px-4 py-3">Pemilik</th>
                            <th class="px-4 py-3 text-right">Akun</th>
                            <th class="px-4 py-3">Terdaftar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="tenant in tenants.data" :key="tenant.id" class="hover:bg-accent/30 transition-colors">
                            <td class="px-4 py-3">
                                <p class="font-medium text-foreground">{{ tenant.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ tenant.slug }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <template v-if="tenant.owner">
                                    <p class="text-foreground">{{ tenant.owner.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ tenant.owner.email }}</p>
                                </template>
                                <span v-else class="text-xs text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-foreground">{{ tenant.user_count }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ tenant.registered_at }}</td>
                        </tr>

                        <tr v-if="tenants.data.length === 0">
                            <td colspan="4" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                Belum ada tenant terdaftar.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-4 text-xs text-muted-foreground leading-relaxed max-w-2xl">
            Panel ini sengaja tidak menampilkan data operasional klien — transaksi, produk, stok, maupun laporan.
        </p>
    </PlatformLayout>
</template>
