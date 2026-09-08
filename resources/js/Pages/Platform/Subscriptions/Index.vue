<script setup>
import { computed } from 'vue';
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import PageHeader from '@/Components/Platform/PageHeader.vue';
import DataTable from '@/Components/Platform/DataTable.vue';
import StatCard from '@/Components/Platform/StatCard.vue';
import StatusBadge from '@/Components/Platform/StatusBadge.vue';
import Notice from '@/Components/Platform/Notice.vue';
import Pagination from '@/Components/Pagination.vue';
import SkeletonTable from '@/Components/Skeleton/SkeletonTable.vue';
import {
    formatRupiah,
    formatDate,
    TENANT_STATUS,
    PRICING_TRACK,
} from '@/support/platform';

const props = defineProps({
    // Empat prop pertama ditunda satu grup ([BL-037]): daftarnya beserta tiga
    // peta pendampingnya lahir dari halaman baris yang sama.
    subscriptions: { type: Object, default: null },
    filters: { type: Object, required: true },
    statuses: { type: Array, required: true },
    seat_usage: { type: Object, default: () => ({}) },
    billing: { type: Object, default: () => ({}) },
    summary: { type: Object, default: null },
    brackets: { type: Object, default: () => ({}) },
    can: { type: Object, required: true },
});

const columns = computed(() => [
    { key: 'account', label: 'Akun' },
    { key: 'status', label: 'Status' },
    { key: 'plan', label: 'Paket' },
    { key: 'track', label: 'Jalur harga' },
    { key: 'seats', label: 'Pengguna', align: 'right' },
    { key: 'price', label: 'Tarif', align: 'right' },
    { key: 'period', label: 'Periode berakhir' },
    ...(props.can.payments ? [{ key: 'billing', label: 'Tagihan' }] : []),
]);

const filterStatus = (status) => {
    router.get('/platform/subscriptions', status ? { status } : {}, {
        preserveState: true,
        replace: true,
    });
};

const statusOf = (row) => TENANT_STATUS[row.tenant?.status] ?? { label: row.tenant?.status ?? '—', tone: 'neutral' };
const trackOf = (row) => PRICING_TRACK[row.pricing_track] ?? PRICING_TRACK.normal;

const seatsUsed = (row) => props.seat_usage[row.tenant?.id] ?? 0;
const billingOf = (row) => props.billing[row.tenant?.id] ?? null;
</script>

<template>
    <Head title="Langganan & Tagihan — Platform" />

    <PlatformLayout>
        <PageHeader
            title="Langganan & Tagihan"
            description="Keadaan komersial tiap akun klien: paket, batas pengguna, tarif, dan tagihannya. Buka satu baris untuk rincian dan tindakannya."
        />

        <!-- Dua angka yang menentukan apakah halaman ini perlu dibuka hari ini. -->
        <div v-if="summary" class="grid gap-4 sm:grid-cols-2 max-w-2xl mb-6">
            <StatCard
                label="Menunggu diperiksa"
                :value="summary.awaiting"
                hint="Bukti bayar yang sudah diunggah dan menunggu keputusan Anda."
            />
            <StatCard
                label="Lewat jatuh tempo"
                :value="summary.overdue"
                hint="Tagihan terbuka yang tanggal jatuh temponya sudah lewat."
            />
        </div>

        <div class="flex flex-wrap gap-1.5 mb-4">
            <button
                type="button"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                :class="!filters.status ? 'bg-primary text-primary-foreground' : 'bg-card border border-border text-muted-foreground hover:bg-accent/40'"
                @click="filterStatus('')"
            >
                Semua
            </button>
            <button
                v-for="status in statuses"
                :key="status"
                type="button"
                class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                :class="filters.status === status ? 'bg-primary text-primary-foreground' : 'bg-card border border-border text-muted-foreground hover:bg-accent/40'"
                @click="filterStatus(status)"
            >
                {{ TENANT_STATUS[status]?.label ?? status }}
            </button>
        </div>

        <!-- Ditunda ([BL-037]): tombol saringan status di atas sudah bisa
             ditekan, dan kerangka ini muncul lagi tiap saringannya berganti. -->
        <Deferred :data="['subscriptions', 'seat_usage', 'billing', 'brackets']">
            <template #fallback>
                <div class="rounded-lg border border-border bg-card shadow-sm overflow-hidden">
                    <SkeletonTable :rows="8" :columns="columns.length" label="Memuat daftar langganan…" />
                </div>
            </template>

        <DataTable
            v-slot="{ cellClass }"
            :columns="columns"
            :count="subscriptions.data.length"
            empty="Belum ada langganan."
        >
            <tr
                v-for="row in subscriptions.data"
                :key="row.id"
                class="hover:bg-accent/30 transition-colors"
            >
                <td :class="cellClass">
                    <Link
                        :href="`/platform/tenants/${row.tenant?.id}`"
                        class="font-medium text-foreground hover:text-primary transition-colors"
                    >
                        {{ row.tenant?.name ?? '—' }}
                    </Link>
                    <p class="text-xs text-muted-foreground">{{ row.tenant?.slug }}</p>
                </td>

                <td :class="cellClass">
                    <StatusBadge :label="statusOf(row).label" :tone="statusOf(row).tone" />
                </td>

                <td :class="[cellClass, 'text-foreground']">{{ row.plan_name ?? '—' }}</td>

                <td :class="cellClass">
                    <span class="text-foreground">{{ trackOf(row).label }}</span>
                    <StatusBadge
                        v-if="row.pricing_track === 'subsidized' && can.revenue"
                        class="ml-1.5"
                        :label="brackets[row.tenant?.id] ?? '—'"
                        tone="success"
                        title="Kelompok harga yang sedang berlaku"
                    />
                </td>

                <td :class="[cellClass, 'text-right tabular-nums text-foreground']">
                    {{ seatsUsed(row) }} / {{ row.seats }}
                    <p class="text-xs text-muted-foreground">puncak {{ row.seat_high_water }}</p>
                </td>

                <td :class="[cellClass, 'text-right tabular-nums text-foreground']">
                    {{ formatRupiah(row.price_locked) }}
                </td>

                <td :class="[cellClass, 'text-muted-foreground whitespace-nowrap']">
                    {{ formatDate(row.current_period_end) }}
                </td>

                <td v-if="can.payments" :class="cellClass">
                    <template v-if="billingOf(row)">
                        <StatusBadge
                            v-if="billingOf(row).awaiting"
                            :label="`${billingOf(row).awaiting} menunggu diperiksa`"
                            tone="warning"
                        />
                        <StatusBadge
                            v-else-if="billingOf(row).overdue"
                            :label="`${billingOf(row).overdue} lewat tempo`"
                            tone="danger"
                        />
                        <StatusBadge
                            v-else
                            :label="`${billingOf(row).open} terbuka`"
                            tone="neutral"
                        />
                    </template>
                    <span v-else class="text-xs text-muted-foreground">lunas</span>
                </td>
            </tr>
        </DataTable>

        <Pagination v-if="subscriptions.meta" :paginator="subscriptions.meta" tone="platform" unit="langganan" />
        </Deferred>

        <Notice class="mt-6 max-w-2xl">
            Semua angka di halaman ini adalah keterangan komersial — paket, tarif, batas pengguna, tagihan. Tidak satu
            pun berasal dari penjualan klien. Untuk tenant jalur <span class="font-medium">Harga Adaptif</span>, yang
            tampil hanya kelompok harganya; angka omzet rupiahnya dibuka dari rincian akun, dan setiap pembukaan itu
            tercatat di jejak audit.
        </Notice>
    </PlatformLayout>
</template>
