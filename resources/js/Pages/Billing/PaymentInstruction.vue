<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePoll } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    attempt: { type: Object, required: true },
    invoice: { type: Object, required: true },
    gateway: { type: Object, required: true },
});

const formatRupiah = (value) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);

// --- Hitung mundur ----------------------------------------------------------
// Sebuah instruksi bayar punya umur, dan angka yang diam membuat orang mengira
// ia tidak punya. Detiknya berjalan di klien; yang menentukan sah atau tidaknya
// tetap server — `expires_at` diperiksa ulang di webhook, bukan dipercayakan
// pada jam peramban.
const now = ref(Date.now());
let ticker = null;

onMounted(() => {
    ticker = window.setInterval(() => (now.value = Date.now()), 1000);
});

onBeforeUnmount(() => window.clearInterval(ticker));

const secondsLeft = computed(() => {
    if (!props.attempt.expires_at) return null;
    return Math.max(0, Math.floor((new Date(props.attempt.expires_at).getTime() - now.value) / 1000));
});

const countdown = computed(() => {
    const total = secondsLeft.value;
    if (total === null) return null;
    const minutes = String(Math.floor(total / 60)).padStart(2, '0');
    const seconds = String(total % 60).padStart(2, '0');
    return `${minutes}:${seconds}`;
});

// --- Menunggu kabar dari penyedia -------------------------------------------
// Pembayaran diselesaikan di aplikasi lain, jadi halaman ini tidak akan pernah
// tahu sendiri kapan ia berubah. Polling berhenti begitu keadaannya final —
// terus bertanya setelah jawabannya tidak akan berubah lagi hanya membebani
// server tanpa memberi tahu siapa pun apa pun.
const { stop } = usePoll(5000, { only: ['attempt', 'invoice'] });

watch(
    () => props.attempt.status,
    (status) => {
        if (status !== 'pending') stop();
    },
    { immediate: true },
);

// Tenggat lewat tanpa kabar: satu muat ulang terakhir supaya server yang
// menuliskan keadaannya, bukan tampilan yang menebaknya sendiri.
watch(secondsLeft, (value, previous) => {
    if (previous > 0 && value === 0) router.reload({ only: ['attempt', 'invoice'] });
});

// --- Nomor VA ---------------------------------------------------------------
const copied = ref(false);

const copy = async (value) => {
    try {
        await navigator.clipboard.writeText(value);
        copied.value = true;
        window.setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Peramban yang menolak akses papan klip bukan kegagalan yang perlu
        // diumumkan — nomornya tetap terbaca dan bisa disalin manual.
    }
};

// --- QR peragaan ------------------------------------------------------------
// Bukan QRIS sungguhan: menghasilkan kode yang benar butuh pustaka baru, dan
// yang dibutuhkan alur ini cuma bentuk yang bisa dikenali sebagai QR. Polanya
// diturunkan dari isi payload sehingga tetap sama tiap kali halaman dibuka —
// gambar yang berubah tiap refresh langsung terbaca sebagai palsu.
const qrCells = computed(() => {
    const size = 21;
    const payload = props.attempt.instructions?.qr_payload ?? '';
    let seed = 7;
    for (const character of payload) {
        seed = (seed * 31 + character.charCodeAt(0)) >>> 0;
    }

    const random = () => {
        seed = (seed * 1664525 + 1013904223) >>> 0;
        return seed / 4294967296;
    };

    const finders = [[0, 0], [size - 7, 0], [0, size - 7]];
    const cells = [];

    for (let y = 0; y < size; y++) {
        for (let x = 0; x < size; x++) {
            const finder = finders.find(([fx, fy]) => x >= fx && x < fx + 7 && y >= fy && y < fy + 7);

            if (finder) {
                const [lx, ly] = [x - finder[0], y - finder[1]];
                const onEdge = lx === 0 || lx === 6 || ly === 0 || ly === 6;
                const inCore = lx >= 2 && lx <= 4 && ly >= 2 && ly <= 4;
                if (onEdge || inCore) cells.push({ x, y });
                continue;
            }

            if (random() > 0.5) cells.push({ x, y });
        }
    }

    return cells;
});

// --- Keadaan ----------------------------------------------------------------
const state = computed(() => {
    switch (props.attempt.status) {
        case 'paid':
            return {
                tone: 'success',
                title: 'Pembayaran diterima',
                body: 'Tagihan ini lunas dan akses langganan sudah pulih. Tidak ada lagi yang perlu Anda lakukan.',
            };
        case 'mismatch':
            return {
                tone: 'warning',
                title: 'Nominalnya tidak sama',
                body: 'Uang yang masuk berbeda dari yang ditagih, jadi tagihannya belum kami tandai lunas. Pemilik layanan akan memeriksa dan menghubungi Anda.',
            };
        case 'expired':
            return {
                tone: 'muted',
                title: 'Instruksi ini sudah kedaluwarsa',
                body: 'Nomor di halaman ini tidak lagi ditunggu. Terbitkan instruksi baru, dan jangan mentransfer ke nomor lama.',
            };
        case 'failed':
            return {
                tone: 'destructive',
                title: 'Pembayaran gagal',
                body: 'Penyedia pembayaran menolak transaksi ini. Coba lagi, atau pilih cara membayar yang lain.',
            };
        default:
            return {
                tone: 'pending',
                title: 'Menunggu pembayaran',
                body: 'Selesaikan pembayaran sesuai petunjuk di bawah. Halaman ini akan berubah sendiri begitu pembayarannya masuk — tidak perlu dimuat ulang.',
            };
    }
});

/**
 * Nada dibawa oleh garis dan latar, teksnya tetap `foreground`.
 *
 * Godaannya adalah memakai `text-success-foreground` dkk, tapi token itu
 * dirancang sebagai warna teks DI ATAS latar penuh — di atas latar 10% ia
 * nyaris putih di mode terang dan hilang sama sekali.
 */
const toneClasses = {
    success: 'border-success/40 bg-success/10 text-foreground',
    warning: 'border-warning/50 bg-warning/10 text-foreground',
    destructive: 'border-destructive/40 bg-destructive/10 text-foreground',
    muted: 'border-border bg-accent/30 text-muted-foreground',
    pending: 'border-primary/40 bg-primary/5 text-foreground',
};

const isPending = computed(() => props.attempt.status === 'pending');

// --- Peragaan ---------------------------------------------------------------
const simulation = useForm({ outcome: null });

const simulate = (outcome) => {
    simulation.outcome = outcome;
    simulation.post(`/langganan/pembayaran/${props.attempt.id}/peragakan`, { preserveScroll: true });
};

/**
 * Pembayaran yang datang sendiri.
 *
 * Ini yang membuat alurnya bisa diperagakan di depan calon klien: yang terlihat
 * adalah instruksi, penantian, lalu berhasil — bukan seseorang menekan tombol
 * bernama "Bayar penuh". Servernya tetap memperlakukannya seperti notifikasi
 * penyedia mana pun: ditandatangani, lewat webhook, diperiksa nominalnya.
 *
 * Dijalankan dari sini alih-alih dari server saat halaman dimuat, karena
 * pemuatan halaman ini adalah GET yang dipanggil berulang oleh polling — dan
 * GET yang melunasi tagihan berarti prefetch pun ikut membayar.
 */
const autoSettleSeconds = Number(props.gateway.auto_settle_seconds ?? 0);
const autoSettleArmed = ref(autoSettleSeconds > 0 && props.attempt.status === 'pending');
let autoSettleTimer = null;

onMounted(() => {
    if (!autoSettleArmed.value) return;

    autoSettleTimer = window.setTimeout(() => simulate('paid'), autoSettleSeconds * 1000);
});

onBeforeUnmount(() => window.clearTimeout(autoSettleTimer));
</script>

<template>
    <Head title="Instruksi Pembayaran" />

    <div class="p-4 sm:p-6 lg:p-8">
        <div class="max-w-xl mx-auto">
            <Link href="/langganan" class="text-sm text-muted-foreground hover:text-foreground">← Kembali ke Langganan</Link>

            <div class="mt-3 rounded-xl border p-5" :class="toneClasses[state.tone]">
                <p class="text-sm font-semibold">{{ state.title }}</p>
                <p class="mt-1 text-xs leading-relaxed">{{ state.body }}</p>
                <p v-if="isPending && countdown" class="mt-3 text-xs">
                    Berlaku sampai <span class="font-semibold tabular-nums">{{ countdown }}</span> lagi
                </p>

                <!--
                    Tanda bahwa halaman ini benar-benar sedang menunggu sesuatu,
                    bukan diam. Tanpa ini penantian delapan detik terbaca seperti
                    halaman yang macet — dan orang akan menekan muat ulang tepat
                    sebelum pembayarannya masuk.
                -->
                <p v-if="autoSettleArmed && isPending" class="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-primary animate-pulse" />
                    Memeriksa pembayaran…
                </p>
            </div>

            <div class="mt-4 rounded-xl border border-border bg-card p-5">
                <div class="flex items-baseline justify-between gap-3">
                    <div>
                        <p class="text-sm text-muted-foreground">
                            {{ invoice.kind === 'upgrade' ? 'Tambah pengguna' : 'Langganan' }} · {{ invoice.period }}
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">{{ attempt.channel_label }}</p>
                    </div>
                    <p class="text-xl font-semibold text-foreground tabular-nums shrink-0">{{ formatRupiah(attempt.amount) }}</p>
                </div>

                <!-- Petunjuk per kanal. Hanya ditampilkan selama masih ditunggu. -->
                <div v-if="isPending" class="mt-5 border-t border-border pt-5">
                    <div v-if="attempt.instructions?.type === 'qris'" class="text-center">
                        <svg viewBox="0 0 21 21" class="mx-auto h-44 w-44 rounded-lg bg-white p-2" role="img" aria-label="Kode QR peragaan">
                            <rect
                                v-for="cell in qrCells"
                                :key="`${cell.x}-${cell.y}`"
                                :x="cell.x"
                                :y="cell.y"
                                width="1"
                                height="1"
                                fill="#111"
                            />
                        </svg>
                        <p class="mt-3 text-xs text-muted-foreground">Pindai dengan aplikasi bank atau e-wallet</p>
                    </div>

                    <div v-else-if="attempt.instructions?.type === 'virtual_account'">
                        <p class="text-xs text-muted-foreground">Nomor Virtual Account {{ attempt.instructions.bank }}</p>
                        <div class="mt-1 flex items-center gap-3">
                            <p class="text-lg font-semibold text-foreground tabular-nums tracking-wider">
                                {{ attempt.instructions.virtual_account }}
                            </p>
                            <button
                                type="button"
                                class="text-xs font-medium text-primary hover:text-primary/80"
                                @click="copy(attempt.instructions.virtual_account)"
                            >
                                {{ copied ? 'Tersalin' : 'Salin' }}
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground leading-relaxed">
                            Transfer tepat sejumlah tagihan. Nominal yang berbeda tidak akan melunasi tagihan
                            secara otomatis.
                        </p>
                    </div>

                    <div v-else>
                        <p class="text-xs text-muted-foreground">Kode pembayaran</p>
                        <p class="mt-1 text-lg font-semibold text-foreground tracking-wider">{{ attempt.instructions?.reference }}</p>
                    </div>
                </div>

                <p class="mt-5 border-t border-border pt-3 text-[11px] text-muted-foreground">
                    No. transaksi <span class="tabular-nums">{{ attempt.external_id }}</span>
                </p>
            </div>

            <!-- Jalan keluar ketika keadaannya sudah final -->
            <Link
                v-if="!isPending && invoice.status !== 'paid'"
                :href="`/langganan/tagihan/${invoice.id}/bayar`"
                class="mt-4 block rounded-lg bg-primary px-4 py-3 text-center text-sm font-semibold text-primary-foreground hover:bg-primary/90"
            >
                Terbitkan instruksi baru
            </Link>

            <!--
                Alat pengembangan. Tertutup secara bawaan: keadaan yang tidak
                berakhir berhasil tetap perlu bisa dipanggil, tapi bukan di layar
                yang sedang ditonton calon klien. Alur normalnya sudah berjalan
                sendiri lewat `autoSettleArmed`.
            -->
            <details v-if="gateway.is_simulated && isPending" class="mt-4">
                <summary class="cursor-pointer text-xs text-muted-foreground hover:text-foreground select-none">
                    Alat pengembangan
                </summary>

                <div class="mt-2 rounded-xl border border-dashed border-warning/60 p-4">
                    <p class="text-[11px] text-muted-foreground leading-relaxed">
                        Tombol di bawah mengirim notifikasi seolah-olah datang dari penyedia pembayaran —
                        lewat jalur, tanda tangan, dan pemeriksaan yang sama persis dengan penyedia
                        sungguhan. Alur normal tidak membutuhkannya: pembayaran datang sendiri.
                    </p>

                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            :disabled="simulation.processing"
                            class="rounded-md border border-border px-3 py-2 text-xs font-medium text-foreground hover:bg-accent/40 disabled:opacity-50"
                            @click="simulate('paid')"
                        >
                            Bayar sekarang
                        </button>
                        <button
                            type="button"
                            :disabled="simulation.processing"
                            class="rounded-md border border-border px-3 py-2 text-xs font-medium text-foreground hover:bg-accent/40 disabled:opacity-50"
                            @click="simulate('underpaid')"
                        >
                            Bayar kurang
                        </button>
                        <button
                            type="button"
                            :disabled="simulation.processing"
                            class="rounded-md border border-border px-3 py-2 text-xs font-medium text-foreground hover:bg-accent/40 disabled:opacity-50"
                            @click="simulate('failed')"
                        >
                            Gagal
                        </button>
                        <button
                            type="button"
                            :disabled="simulation.processing"
                            class="rounded-md border border-border px-3 py-2 text-xs font-medium text-foreground hover:bg-accent/40 disabled:opacity-50"
                            @click="simulate('expired')"
                        >
                            Kedaluwarsa
                        </button>
                    </div>
                </div>
            </details>
        </div>
    </div>
</template>
