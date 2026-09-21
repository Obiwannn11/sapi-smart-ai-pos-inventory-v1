<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import CashierTopbar from '@/Components/CashierTopbar.vue';

/**
 * Layar kasir di tahap `locked` ([BL-054](b)).
 *
 * Dirender oleh `EnsureSubscriptionActive` di URL kasir yang asli, BUKAN lewat
 * pengalihan ke halaman langganan: pengalihan diam-diam membuat pengguna
 * mengira aplikasinya rusak, dan kasir yang tiba-tiba mendarat di halaman
 * tagihan tidak tahu apa yang baru saja terjadi pada layar kerjanya.
 *
 * Yang dihentikan hanya berjualan. Riwayat transaksi, laporan, stok, dan ekspor
 * tetap terbuka — dan halaman ini menyebutkannya, karena orang yang mengira
 * datanya ikut terkunci akan menghabiskan sore itu menelepon, bukan membayar.
 */

const props = defineProps({
    graceDay: { type: Number, default: null },
    graceDays: { type: Number, required: true },
    suspendsAt: { type: String, default: null },
    isOwner: { type: Boolean, default: false },
});

const page = usePage();
const tenantName = computed(() => page.props.auth?.tenant?.name ?? 'Kasir');

/**
 * Tanggal ISO dibaca sebagai tanggal kalender, bukan titik waktu — pola yang
 * sama seperti `SubscriptionBanner`.
 */
const suspendsAtLabel = computed(() => {
    if (!props.suspendsAt) return null;
    const [year, month, day] = props.suspendsAt.split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});
</script>

<template>
    <div class="h-screen flex flex-col bg-background overflow-hidden">
        <Head title="Selesaikan Tagihan" />

        <CashierTopbar :title="tenantName" />

        <main class="flex-1 overflow-y-auto p-4 sm:p-6">
            <div class="mx-auto max-w-xl rounded-xl border border-border bg-card p-6 sm:p-8">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>

                    <div class="min-w-0 space-y-4">
                        <div>
                            <h1 class="text-lg font-semibold text-foreground">
                                Selesaikan tagihan untuk membuka kasir
                            </h1>
                            <p class="mt-1 text-sm leading-relaxed text-muted-foreground">
                                Pencatatan penjualan dihentikan sementara karena tagihan
                                langganan belum diselesaikan<span v-if="graceDay">
                                — hari ke-{{ graceDay }} dari {{ graceDays }}</span>.
                            </p>
                        </div>

                        <div class="rounded-lg bg-muted px-4 py-3 text-sm leading-relaxed text-foreground">
                            <p class="font-medium">Data Anda tidak ke mana-mana.</p>
                            <p class="mt-1 text-muted-foreground">
                                Riwayat transaksi, laporan harian, stok, dan seluruh
                                ekspor tetap bisa dibuka seperti biasa. Yang berhenti
                                hanya menyimpan data baru.
                            </p>
                        </div>

                        <p v-if="suspendsAtLabel" class="text-sm text-muted-foreground">
                            Bila tetap tidak diselesaikan, akses ditutup sepenuhnya pada
                            <strong class="text-foreground">{{ suspendsAtLabel }}</strong>.
                        </p>

                        <div class="flex flex-col gap-2 pt-1 sm:flex-row">
                            <!--
                                Kasir tidak dikirim ke halaman Langganan — setiap tombol
                                di sana digerbang `role:owner`. Yang ia butuhkan justru
                                jalan kembali ke pekerjaan yang MASIH bisa ia lakukan.
                            -->
                            <Link
                                v-if="isOwner"
                                href="/langganan"
                                class="rounded-lg bg-primary px-4 py-2 text-center text-sm font-medium text-primary-foreground transition-colors hover:opacity-90"
                            >
                                Selesaikan pembayaran
                            </Link>
                            <span v-else class="text-sm text-muted-foreground">
                                Sampaikan ke pemilik usaha untuk menyelesaikannya.
                            </span>

                            <Link
                                href="/cashier/transactions"
                                class="rounded-lg border border-border px-4 py-2 text-center text-sm font-medium text-foreground transition-colors hover:bg-muted"
                            >
                                Lihat riwayat transaksi
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
