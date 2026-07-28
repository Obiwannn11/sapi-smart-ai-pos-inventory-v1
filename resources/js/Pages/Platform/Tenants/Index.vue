<script setup>
import { router, Head } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

defineProps({
    tenants: { type: Object, required: true },
    business_types: { type: Object, default: () => ({}) },
});

// Disimpan seketika saat dipilih, tanpa tombol simpan: satu kolom, satu
// keputusan. Perubahannya tercatat di jejak audit karena ia dasar penetapan
// harga, bukan sekadar keterangan.
const updateBusinessType = (tenant, value) =>
    router.put(
        `/platform/tenants/${tenant.id}/business-type`,
        { business_type: value || null },
        { preserveScroll: true },
    );
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
                            <th class="px-4 py-3">Jenis Usaha</th>
                            <th class="px-4 py-3 text-right">Akun</th>
                            <th class="px-4 py-3">Terdaftar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="tenant in tenants.data" :key="tenant.id" class="hover:bg-accent/30 transition-colors">
                            <td class="px-4 py-3">
                                <p class="font-medium text-foreground">
                                    {{ tenant.name }}
                                    <span
                                        v-if="!tenant.is_verified"
                                        class="ml-1.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-muted text-muted-foreground"
                                        title="Pemiliknya belum memverifikasi alamat emailnya"
                                    >
                                        belum verifikasi
                                    </span>
                                    <span
                                        v-if="tenant.flagged_at"
                                        class="ml-1.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-500/15 text-amber-700"
                                    >
                                        perlu ditinjau
                                    </span>
                                </p>
                                <p class="text-xs text-muted-foreground">{{ tenant.slug }}</p>
                                <p v-if="tenant.flag_reason" class="text-xs text-amber-700 mt-0.5">{{ tenant.flag_reason }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <template v-if="tenant.owner">
                                    <p class="text-foreground">{{ tenant.owner.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ tenant.owner.email }}</p>
                                </template>
                                <span v-else class="text-xs text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <select
                                    :value="tenant.business_type ?? ''"
                                    class="w-full max-w-[10rem] px-2 py-1.5 border border-border rounded-lg text-xs bg-card text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                                    @change="updateBusinessType(tenant, $event.target.value)"
                                >
                                    <option value="">Belum ditentukan</option>
                                    <option v-for="(label, value) in business_types" :key="value" :value="value">
                                        {{ label }}
                                    </option>
                                </select>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-foreground">{{ tenant.user_count }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ tenant.registered_at }}</td>
                        </tr>

                        <tr v-if="tenants.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-muted-foreground">
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
