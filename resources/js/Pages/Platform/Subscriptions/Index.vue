<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

defineProps({
    subscriptions: { type: Object, required: true },
    brackets: { type: Object, required: true },
    can_view_revenue: { type: Boolean, required: true },
});

const statusLabels = {
    trial: 'Masa coba',
    active: 'Aktif',
    grace: 'Masa tenggang',
    suspended: 'Ditangguhkan',
};

const statusClasses = {
    trial: 'bg-muted text-muted-foreground',
    active: 'bg-primary/10 text-primary',
    grace: 'bg-amber-500/15 text-amber-700',
    suspended: 'bg-destructive/10 text-destructive',
};

const formatRupiah = (value) =>
    value === null
        ? '—'
        : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
</script>

<template>
    <Head title="Langganan — Platform" />

    <PlatformLayout>
        <template #header>Langganan</template>

        <div class="rounded-xl border border-border bg-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-accent/50 text-left">
                        <tr class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <th class="px-4 py-3">Tenant</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Paket</th>
                            <th class="px-4 py-3">Jalur</th>
                            <th class="px-4 py-3 text-right">Seat</th>
                            <th class="px-4 py-3 text-right">Tarif Terkunci</th>
                            <th class="px-4 py-3">Periode Berakhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="row in subscriptions.data" :key="row.id" class="hover:bg-accent/30 transition-colors">
                            <td class="px-4 py-3 font-medium text-foreground">{{ row.tenant?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="statusClasses[row.tenant?.status] ?? 'bg-muted text-muted-foreground'"
                                >
                                    {{ statusLabels[row.tenant?.status] ?? row.tenant?.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-foreground">{{ row.plan_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ row.pricing_track === 'subsidized' ? 'Subsidi' : 'Normal' }}
                                <template v-if="row.pricing_track === 'subsidized' && can_view_revenue">
                                    <span class="ml-1.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                        {{ brackets[row.tenant?.id] ?? '—' }}
                                    </span>
                                    <Link
                                        :href="`/platform/revenue/${row.tenant?.id}`"
                                        class="ml-1.5 text-xs font-medium text-primary hover:text-primary/80"
                                    >
                                        rincian
                                    </Link>
                                </template>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-foreground">
                                {{ row.seats }}
                                <span class="text-xs text-muted-foreground">(puncak {{ row.seat_high_water }})</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-foreground">
                                {{ formatRupiah(row.price_locked) }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.current_period_end ?? '—' }}</td>
                        </tr>

                        <tr v-if="subscriptions.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                Belum ada langganan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-4 text-xs text-muted-foreground leading-relaxed max-w-2xl">
            Semua angka di halaman ini adalah keterangan komersial — paket, tarif, dan seat yang kita tetapkan sendiri.
            Tidak satu pun berasal dari penjualan klien. Untuk tenant jalur subsidi, yang tampil hanya
            <span class="font-medium">kelompok harganya</span>; angka omzet rupiahnya dibuka lewat "rincian",
            dan setiap pembukaan itu tercatat di jejak audit.
        </p>
    </PlatformLayout>
</template>
