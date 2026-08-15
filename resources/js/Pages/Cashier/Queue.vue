<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Deferred, Head, router } from '@inertiajs/vue3';
import CashierTopbar from '@/Components/CashierTopbar.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import FlashMessage from '@/Components/FlashMessage.vue';
import { useOnlineStatus } from '@/composables/useOnlineStatus';
import SkeletonGrid from '@/Components/Skeleton/SkeletonGrid.vue';
import SkeletonCard from '@/Components/Skeleton/SkeletonCard.vue';

const props = defineProps({
    // Ditunda ([BL-037]) — null hanya pada pemuatan pertama. Penyegaran
    // berkala memakai permintaan parsial yang menyebut prop ini, jadi papan
    // lama tetap terpasang sampai papan baru datang: kerangkanya tidak muncul
    // lagi di setiap poll.
    queue: { type: Array, default: null },
});

const { isOnline } = useOnlineStatus();

// ── Penyegaran ─────────────────────────────────────────────────────────────
// Polling, bukan websocket. Karena setiap aksi mengirim `expected_from`, kartu
// basi gagal dengan pesan yang jelas alih-alih merusak status — jadi menaikkan
// ini ke Reverb nanti aman dilakukan belakangan.
const POLL_MS = 7000;
let poller = null;

onMounted(() => {
    poller = setInterval(() => {
        if (isOnline.value) {
            router.reload({ only: ['queue'] });
        }
    }, POLL_MS);
});

onUnmounted(() => {
    if (poller) clearInterval(poller);
});

// ── Timer tunggu ───────────────────────────────────────────────────────────
// Dihitung di klien: angka yang dihitung server akan membeku di antara dua
// polling dan terlihat macet.
const now = ref(Date.now());
let ticker = null;

onMounted(() => {
    ticker = setInterval(() => (now.value = Date.now()), 30000);
});

onUnmounted(() => {
    if (ticker) clearInterval(ticker);
});

const waitLabel = (card) => {
    const started = new Date(card.waiting_since).getTime();
    const minutes = Math.max(0, Math.floor((now.value - started) / 60000));

    if (minutes < 60) return `${minutes}m`;

    return `${Math.floor(minutes / 60)}j ${minutes % 60}m`;
};

// Menua = perlu perhatian. Ambangnya kasar dan sengaja: papan ini dibaca
// sekilas oleh orang yang sedang memasak.
const isStale = (card) =>
    (now.value - new Date(card.waiting_since).getTime()) / 60000 >= 15;

// ── Label status ───────────────────────────────────────────────────────────
const STEP = {
    waiting: { label: 'Menunggu', action: 'Mulai masak', tone: 'bg-slate-100 text-slate-700' },
    preparing: { label: 'Dimasak', action: 'Siap', tone: 'bg-amber-100 text-amber-800' },
    ready: { label: 'Siap', action: 'Selesai', tone: 'bg-emerald-100 text-emerald-800' },
};

const stepOf = (card) => STEP[card.fulfillment_status] ?? STEP.waiting;

const formatRupiah = (value) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 })
        .format(value ?? 0);

// ── Aksi ───────────────────────────────────────────────────────────────────
const busy = ref(null);

const send = (url, card) => {
    busy.value = card.id;
    router.post(url, {}, {
        preserveScroll: true,
        onFinish: () => (busy.value = null),
    });
};

const advance = (card) => {
    busy.value = card.id;
    router.post(`/cashier/queue/${card.id}/advance`,
        { expected_from: card.fulfillment_status },
        { preserveScroll: true, onFinish: () => (busy.value = null) },
    );
};

// Konfirmasi HANYA di dua tempat yang benar-benar layak. Menaruhnya di ▲/▼
// akan menghukum pemakainya: aksinya kecil, sering, dan mudah dibatalkan
// dengan menekan arah sebaliknya.
const confirming = ref(null);   // { kind: 'unpaid' | 'prioritise', card }

const onAdvanceClick = (card) => {
    // Menandai selesai pesanan yang belum ditagih adalah kebocoran uang yang
    // tidak meninggalkan jejak — syarat penerimaan, bukan penyempurnaan.
    if (card.fulfillment_status === 'ready' && !card.is_paid) {
        confirming.value = { kind: 'unpaid', card };

        return;
    }

    advance(card);
};

const onPrioritiseClick = (card) => {
    confirming.value = { kind: 'prioritise', card };
};

const confirmText = computed(() => {
    const c = confirming.value;
    if (!c) return {};

    if (c.kind === 'unpaid') {
        return {
            title: 'Pesanan ini belum dibayar',
            message: `Nomor ${c.card.queue_number ?? '-'} belum ditagih. Tagih ${formatRupiah(c.card.amount_due)} lebih dulu, atau tandai selesai kalau sudah diterima di luar sistem.`,
            confirmText: 'Tetap tandai selesai',
            variant: 'warning',
        };
    }

    return {
        title: 'Dahulukan pesanan',
        message: `Pindahkan nomor ${c.card.queue_number ?? '-'} ke urutan paling atas?`,
        confirmText: 'Dahulukan',
        variant: 'warning',
    };
});

const onConfirm = () => {
    const c = confirming.value;
    confirming.value = null;
    if (!c) return;

    if (c.kind === 'unpaid') {
        advance(c.card);
    } else {
        send(`/cashier/queue/${c.card.id}/move-to-top`, c.card);
    }
};
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <Head title="Antrian Dapur" />
        <CashierTopbar title="Antrian Dapur" />

        <FlashMessage />

        <main class="max-w-5xl mx-auto px-3 py-4 sm:px-4">
            <!--
                Keadaan offline dinyatakan, bukan didiamkan. Tanpa pita ini,
                orang pertama yang mengalaminya akan melaporkannya sebagai bug
                — dan mereka tidak akan salah menduga.
            -->
            <div
                v-if="!isOnline"
                class="mb-4 flex gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900"
            >
                <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 010 12.728m-12.728 0a9 9 0 010-12.728m9.9 9.9a5 5 0 010-7.072m-7.072 0a5 5 0 010 7.072M13 12a1 1 0 11-2 0 1 1 0 012 0z" />
                </svg>
                <span>
                    <strong>Sedang offline.</strong>
                    Pesanan baru tidak masuk papan sampai koneksi kembali, dan papan berhenti menyegarkan diri.
                    Kasir tetap bisa berjualan seperti biasa.
                </span>
            </div>

            <Deferred data="queue">
                <template #fallback>
                    <SkeletonGrid
                        :count="3"
                        columns="grid-cols-1"
                        gap="gap-3"
                        label="Memuat papan pesanan…"
                    >
                        <SkeletonCard icon :lines="3" footer padding="p-4" />
                    </SkeletonGrid>
                </template>

            <!-- Papan kosong -->
            <div v-if="queue.length === 0" class="rounded-xl border border-dashed border-gray-300 bg-white py-16 text-center">
                <p class="text-base font-medium text-gray-900">Belum ada pesanan</p>
                <p class="mt-1 text-sm text-gray-500">Pesanan yang masuk hari ini akan muncul di sini.</p>
            </div>

            <div v-else class="space-y-3">
                <article
                    v-for="(card, index) in queue"
                    :key="card.id"
                    class="rounded-xl border bg-white p-4 shadow-sm"
                    :class="isStale(card) ? 'border-amber-300' : 'border-gray-200'"
                >
                    <div class="flex gap-4">
                        <!-- Nomor antrian: elemen paling menonjol di kartu -->
                        <div class="shrink-0 text-center">
                            <div class="flex h-16 w-16 items-center justify-center rounded-xl bg-gray-900 text-2xl font-bold text-white">
                                {{ card.queue_number ?? '—' }}
                            </div>
                            <p class="mt-1 text-[11px] text-gray-400">{{ waitLabel(card) }}</p>
                        </div>

                        <div class="min-w-0 flex-1">
                            <!-- Badge -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="stepOf(card).tone">
                                    {{ stepOf(card).label }}
                                </span>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                    {{ card.source === 'self_order' ? 'Self-Order' : 'Kasir' }}
                                </span>
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                    {{ card.order_type === 'pickup' ? 'Bawa pulang' : 'Makan di tempat' }}
                                </span>
                                <span v-if="card.table_number" class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">
                                    Meja {{ card.table_number }}
                                </span>
                                <span
                                    v-if="!card.is_paid"
                                    class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-white"
                                >
                                    Belum bayar
                                </span>
                            </div>

                            <p v-if="card.customer_name" class="mt-1.5 text-sm font-medium text-gray-900">
                                {{ card.customer_name }}
                            </p>

                            <!-- Item -->
                            <ul class="mt-2 space-y-1">
                                <li v-for="item in card.items" :key="item.id" class="text-sm text-gray-800">
                                    <span class="font-semibold">{{ item.qty }}×</span>
                                    {{ item.name }}
                                    <span v-if="item.modifiers.length" class="text-gray-500">
                                        — {{ item.modifiers.map((m) => m.name).join(', ') }}
                                    </span>
                                    <span v-if="item.notes" class="block pl-5 text-xs italic text-amber-700">
                                        “{{ item.notes }}”
                                    </span>
                                </li>
                            </ul>

                            <p v-if="card.notes" class="mt-2 rounded-lg bg-amber-50 px-2 py-1 text-xs italic text-amber-800">
                                {{ card.notes }}
                            </p>
                        </div>
                    </div>

                    <!-- Aksi -->
                    <div class="mt-3 flex items-center gap-2 border-t border-gray-100 pt-3">
                        <button
                            type="button"
                            :disabled="busy === card.id"
                            class="flex-1 rounded-lg bg-gray-900 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            @click="onAdvanceClick(card)"
                        >
                            {{ stepOf(card).action }}
                        </button>

                        <!--
                            Prioritas tidak dirender pada kartu `ready`:
                            mengurutkan ulang sesuatu yang sudah matang tidak
                            bermakna.
                        -->
                        <template v-if="card.fulfillment_status !== 'ready'">
                            <button
                                v-if="index > 0"
                                type="button"
                                :disabled="busy === card.id"
                                class="rounded-lg border border-gray-300 px-3 py-3 text-sm font-medium text-gray-700 disabled:opacity-50"
                                @click="onPrioritiseClick(card)"
                            >
                                Dahulukan
                            </button>
                            <button
                                type="button"
                                :disabled="busy === card.id || index === 0"
                                class="rounded-lg border border-gray-300 px-3 py-3 text-gray-600 disabled:opacity-30"
                                aria-label="Naikkan satu posisi"
                                @click="send(`/cashier/queue/${card.id}/move-up`, card)"
                            >
                                ▲
                            </button>
                            <button
                                type="button"
                                :disabled="busy === card.id || index === queue.length - 1"
                                class="rounded-lg border border-gray-300 px-3 py-3 text-gray-600 disabled:opacity-30"
                                aria-label="Turunkan satu posisi"
                                @click="send(`/cashier/queue/${card.id}/move-down`, card)"
                            >
                                ▼
                            </button>
                        </template>
                    </div>
                </article>
            </div>
            </Deferred>
        </main>

        <ConfirmDialog
            :show="confirming !== null"
            :title="confirmText.title"
            :message="confirmText.message"
            :confirm-text="confirmText.confirmText"
            :variant="confirmText.variant"
            @confirm="onConfirm"
            @cancel="confirming = null"
        />
    </div>
</template>
