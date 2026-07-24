<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

defineProps({
    tenant: { type: Object, required: true },
    metrics: { type: Array, required: true },
    retention_months: { type: Number, required: true },
});

const formatRupiah = (value) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);
</script>

<template>
    <Head :title="`Omzet ${tenant.name} — Platform`" />

    <PlatformLayout>
        <template #header>Rincian Omzet</template>

        <div class="mb-4">
            <p class="text-lg font-semibold text-foreground">{{ tenant.name }}</p>
            <p class="text-sm text-muted-foreground">Jalur subsidi UMKM</p>
        </div>

        <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 mb-4">
            <p class="text-sm text-foreground leading-relaxed">
                Kunjungan Anda ke halaman ini <span class="font-medium">tercatat di jejak audit</span>, dan klien
                berhak meminta salinannya. Itulah yang membuat janji di dokumen persetujuan mereka bisa dibuktikan.
            </p>
        </div>

        <div class="rounded-xl border border-border bg-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-accent/50 text-left">
                        <tr class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <th class="px-4 py-3">Periode</th>
                            <th class="px-4 py-3 text-right">Omzet</th>
                            <th class="px-4 py-3 text-right">Transaksi</th>
                            <th class="px-4 py-3">Kelompok</th>
                            <th class="px-4 py-3">Dihitung</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="metric in metrics" :key="metric.period" class="hover:bg-accent/30 transition-colors">
                            <td class="px-4 py-3 tabular-nums text-foreground">{{ metric.period }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-foreground">
                                {{ formatRupiah(metric.revenue) }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">
                                {{ metric.transaction_count }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                    {{ metric.bracket ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ metric.computed_at }}</td>
                        </tr>

                        <tr v-if="metrics.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                Belum ada periode yang terhitung.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-4 text-xs text-muted-foreground leading-relaxed max-w-2xl">
            Angka di atas dihitung otomatis dari transaksi berstatus selesai, dan disimpan paling lama
            {{ retention_months }} bulan. Tidak ada laba, margin, maupun harga pokok di sini — angka itu memang
            tidak pernah dihitung.
        </p>

        <p class="mt-6 text-sm">
            <Link href="/platform/subscriptions" class="font-medium text-primary hover:text-primary/80 transition-colors">
                Kembali ke daftar langganan
            </Link>
        </p>
    </PlatformLayout>
</template>
