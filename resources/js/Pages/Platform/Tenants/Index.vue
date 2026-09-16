<script setup>
import { Deferred, Head, Link } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import PageHeader from '@/Components/Platform/PageHeader.vue';
import DataTable from '@/Components/Platform/DataTable.vue';
import StatusBadge from '@/Components/Platform/StatusBadge.vue';
import Notice from '@/Components/Platform/Notice.vue';
import SkeletonTable from '@/Components/Skeleton/SkeletonTable.vue';
import { TENANT_STATUS } from '@/support/platform';

defineProps({
    // Ditunda ([BL-037]) — null selama daftar tenantnya masih dimuat.
    tenants: { type: Object, default: null },
});

// Yang tersisa hanyalah kolom yang membedakan satu baris dari baris lain.
// Jenis usaha, jumlah akun, dan tanggal terdaftar dulu ikut di sini dan
// membuat daftarnya terbaca seperti laporan — padahal tak satu pun dari
// ketiganya menjawab "baris mana yang perlu saya buka". Semuanya pindah ke
// rincian, satu klik dari sini.
const columns = [
    { key: 'name', label: 'Nama Usaha' },
    { key: 'owner', label: 'Pemilik' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: 'Aksi', align: 'right' },
];

const statusOf = (value) => TENANT_STATUS[value] ?? { label: value ?? '—', tone: 'neutral' };
</script>

<template>
    <Head title="Daftar Tenant — Platform" />

    <PlatformLayout>
        <PageHeader
            title="Daftar Tenant"
            description="Siapa saja yang memakai layanan ini. Panel ini sengaja tidak menampilkan data operasional klien — transaksi, produk, stok, maupun laporan."
        />

        <Deferred data="tenants">
            <template #fallback>
                <div class="rounded-lg border border-border bg-card shadow-sm overflow-hidden">
                    <SkeletonTable :rows="8" :columns="columns.length" label="Memuat daftar tenant…" />
                </div>
            </template>

        <DataTable
            v-slot="{ cellClass }"
            :columns="columns"
            :count="tenants.data.length"
            empty="Belum ada tenant terdaftar."
        >
            <tr v-for="tenant in tenants.data" :key="tenant.id" class="hover:bg-accent/30 transition-colors">
                <td :class="cellClass">
                    <p class="font-medium text-foreground">
                        {{ tenant.name }}
                        <StatusBadge
                            v-if="!tenant.is_verified"
                            class="ml-1.5"
                            label="belum verifikasi"
                            tone="neutral"
                            title="Pemiliknya belum memverifikasi alamat emailnya"
                        />
                        <StatusBadge v-if="tenant.flagged_at" class="ml-1.5" label="perlu ditinjau" tone="warning" />
                    </p>
                    <p class="text-xs text-muted-foreground font-mono">{{ tenant.slug }}</p>
                    <p v-if="tenant.flag_reason" class="mt-0.5 text-xs text-amber-700">{{ tenant.flag_reason }}</p>
                </td>

                <td :class="cellClass">
                    <template v-if="tenant.owner">
                        <p class="text-foreground">{{ tenant.owner.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ tenant.owner.email }}</p>
                    </template>
                    <span v-else class="text-xs text-muted-foreground">—</span>
                </td>

                <td :class="cellClass">
                    <StatusBadge :label="statusOf(tenant.status).label" :tone="statusOf(tenant.status).tone" />
                </td>

                <!-- Tombol yang menyebut dirinya, bukan nama tenant yang
                     diam-diam bisa diklik. Tautan sebelumnya tidak punya
                     penanda apa pun bahwa ia jalan masuk ke suatu tempat. -->
                <td :class="[cellClass, 'text-right']">
                    <Link
                        :href="`/platform/tenants/${tenant.id}`"
                        class="inline-flex items-center gap-1 rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground hover:bg-accent transition-colors whitespace-nowrap"
                    >
                        Lihat detail
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </td>
            </tr>
        </DataTable>
        </Deferred>

        <Notice class="mt-6 max-w-2xl">
            Jenis usaha, jumlah akun, riwayat tagihan, dan fitur kasir tiap tenant ada di halaman rinciannya.
            Jenis usaha adalah dasar penetapan harga, tapi <span class="font-medium">bukan milik kita</span> — ia
            diatur pemilik toko dari halaman Pengaturan mereka, dan hanya bisa dibaca di sana.
        </Notice>
    </PlatformLayout>
</template>
