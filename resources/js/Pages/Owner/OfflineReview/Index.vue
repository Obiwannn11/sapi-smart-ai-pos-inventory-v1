<script setup>
import { router, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import FlashMessage from '@/Components/FlashMessage.vue';

defineOptions({ layout: OwnerLayout });

defineProps({
    transactions: Object,
    negativeVariants: { type: Array, default: () => [] },
});

const resolving = ref(null);

const formatCurrency = (value) => 'Rp ' + Number(value).toLocaleString('id-ID');

const formatDateTime = (value) => {
    if (!value) return '—';

    return new Date(value).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const resolve = (transaction) => {
    resolving.value = transaction.id;

    router.post(`/owner/offline-review/${transaction.id}/resolve`, {}, {
        preserveScroll: true,
        onFinish: () => { resolving.value = null; },
    });
};
</script>

<template>
    <Head title="Koreksi Transaksi Offline" />

    <FlashMessage />

    <div class="space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-xl font-semibold text-foreground">Koreksi Transaksi Offline</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Transaksi ini <span class="font-medium text-foreground">sudah tersimpan dan uangnya sudah diterima</span>.
                Yang perlu dirapikan hanya angkanya — stok atau harga melenceng saat perangkat offline.
            </p>
        </div>

        <!-- Negative stock: the root cause worth acting on first -->
        <div
            v-if="negativeVariants.length > 0"
            class="rounded-lg border border-amber-200 bg-amber-50 p-4"
        >
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z" />
                </svg>
                <div class="min-w-0 flex-1">
                    <h2 class="text-sm font-semibold text-amber-900">
                        {{ negativeVariants.length }} varian bernilai stok minus
                    </h2>
                    <p class="mt-0.5 text-xs text-amber-800">
                        Penjualan offline menembus stok yang tercatat. Lakukan opname fisik, lalu sesuaikan lewat halaman Stok.
                    </p>
                    <ul class="mt-2 flex flex-wrap gap-1.5">
                        <li
                            v-for="variant in negativeVariants"
                            :key="variant.id"
                            class="rounded-md border border-amber-300 bg-white px-2 py-1 text-xs text-amber-900"
                        >
                            {{ variant.product_name }} — {{ variant.variant_name }}
                            <span class="font-semibold text-destructive">{{ variant.stock }}</span>
                        </li>
                    </ul>
                    <Link
                        href="/owner/stock"
                        class="mt-2 inline-block text-xs font-semibold text-amber-900 underline underline-offset-2 hover:text-amber-950"
                    >
                        Buka halaman Stok untuk koreksi
                    </Link>
                </div>
            </div>
        </div>

        <!-- Transaction list -->
        <div class="rounded-lg border border-border bg-card">
            <div v-if="transactions.data.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                <svg class="mb-3 h-10 w-10 text-muted-foreground/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm font-medium text-foreground">Tidak ada yang perlu dikoreksi</p>
                <p class="mt-1 text-xs text-muted-foreground">Semua transaksi offline tersinkron tanpa anomali.</p>
            </div>

            <ul v-else class="divide-y divide-border">
                <li v-for="tx in transactions.data" :key="tx.id" class="p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-sm font-semibold text-foreground">{{ tx.code }}</span>
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">
                                    OFFLINE
                                </span>
                                <span v-if="tx.device_id" class="text-[10px] text-muted-foreground">
                                    {{ tx.device_id }}
                                </span>
                            </div>

                            <p class="mt-1 text-xs text-muted-foreground">
                                Terjadi {{ formatDateTime(tx.occurred_at) }}
                                · Tersinkron {{ formatDateTime(tx.synced_at) }}
                                <template v-if="tx.user"> · Kasir {{ tx.user.name }}</template>
                            </p>

                            <ul class="mt-2 space-y-1">
                                <li
                                    v-for="(reason, idx) in tx.review_reasons"
                                    :key="idx"
                                    class="flex items-start gap-1.5 text-xs text-foreground"
                                >
                                    <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-amber-500"></span>
                                    {{ reason }}
                                </li>
                            </ul>

                            <p class="mt-2 text-xs text-muted-foreground">
                                <span v-for="(item, idx) in tx.items" :key="item.id">
                                    <template v-if="idx > 0"> · </template>
                                    {{ item.qty }}× {{ item.variant_name }}
                                </span>
                            </p>
                        </div>

                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <span class="text-sm font-semibold text-foreground">{{ formatCurrency(tx.total_amount) }}</span>
                            <button
                                @click="resolve(tx)"
                                :disabled="resolving === tx.id"
                                class="rounded-md border border-border bg-white px-3 py-1.5 text-xs font-medium text-foreground transition hover:bg-muted disabled:opacity-40"
                            >
                                {{ resolving === tx.id ? 'Menandai…' : 'Tandai sudah ditinjau' }}
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Pagination -->
        <div v-if="transactions.links.length > 3" class="flex flex-wrap gap-1">
            <Link
                v-for="link in transactions.links"
                :key="link.label"
                :href="link.url ?? ''"
                :class="[
                    'rounded-md px-3 py-1.5 text-xs transition',
                    link.active ? 'bg-primary text-primary-foreground' : 'bg-white border border-border text-muted-foreground hover:border-primary/30',
                    !link.url ? 'pointer-events-none opacity-40' : '',
                ]"
                v-html="link.label"
            />
        </div>
    </div>
</template>
