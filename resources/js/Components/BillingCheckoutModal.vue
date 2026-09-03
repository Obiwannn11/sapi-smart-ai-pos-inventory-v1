<script setup>
/**
 * Modal bayar sekali jalan, untuk peragaan.
 *
 * Kenapa ada, padahal alur tiga halaman (`Billing/Pay` → `PaymentInstruction`)
 * sudah bekerja: yang itu menirukan bentuk penyedia sungguhan, dan karena itu
 * ia menuntut hal-hal yang tidak dimiliki sebuah peragaan — aplikasi bank,
 * kesabaran menunggu, dan dua kali pindah halaman. Di depan calon klien,
 * berpindah halaman dua kali adalah tempat perhatian orang hilang.
 *
 * Di sini semuanya terjadi di atas halaman langganan: pilih kanal, ketik kata
 * sandi, lihat hasilnya. Tagihannya berubah lunas di baris yang sama, di layar
 * yang sama — dan itulah yang sebenarnya ingin ditunjukkan.
 *
 * Dua hal yang sengaja TIDAK disederhanakan:
 *
 *   KATA SANDINYA SUNGGUHAN. Ia diperiksa di server terhadap akun yang sedang
 *   masuk, bukan dicocokkan di sini. Sebuah layar bayar yang menerima ketikan
 *   apa pun adalah maket; yang menuntut sesuatu yang hanya diketahui pemiliknya
 *   adalah peragaan.
 *
 *   PENANTIANNYA TIDAK DIPOTONG. Server bisa menjawab dalam 40 milidetik, dan
 *   pembayaran yang selesai secepat itu terbaca sebagai tombol, bukan sebagai
 *   pembayaran. `MINIMUM_PROCESSING_MS` menahannya sebentar supaya yang
 *   terlihat adalah proses.
 */
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    /** Tagihan yang sedang dibayar; `null` menutup modal. */
    invoice: { type: Object, default: null },
    /** Kanal dari gateway, bentuknya `{ code, label, hint }`. */
    channels: { type: Array, default: () => [] },
    /** Driver yang sedang aktif tiruan? Menentukan baris peringatannya. */
    isSimulated: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const page = usePage();

/**
 * Lama minimum layar "memproses", dalam milidetik.
 *
 * Bukan penundaan buatan demi terlihat sibuk: tanpa ini, satu-satunya umpan
 * balik atas kata sandi yang benar adalah modal yang berkedip. Dua setengah
 * detik cukup untuk membaca kalimat statusnya, dan tidak cukup lama untuk
 * membuat siapa pun mengira aplikasinya menggantung.
 */
const MINIMUM_PROCESSING_MS = 2400;

/** `form` → `processing` → `success`. Kegagalan mengembalikannya ke `form`. */
const step = ref('form');

const form = useForm({ channel: null, password: '' });

const formatRupiah = (value) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);

const selectedChannel = computed(() => props.channels.find((channel) => channel.code === form.channel) ?? null);

// Modal yang dibuka ulang harus bersih: kata sandi yang tertinggal di medan
// dari percobaan sebelumnya, apalagi galat "kata sandi salah" milik tagihan
// lain, adalah dua hal yang tidak pernah benar untuk ditampilkan lagi.
watch(
    () => props.invoice,
    (invoice) => {
        if (!invoice) return;

        step.value = 'form';
        form.reset();
        form.clearErrors();
        form.channel = props.channels[0]?.code ?? null;
    },
);

const close = () => {
    if (step.value === 'processing') return;

    emit('close');
};

/**
 * Kalimat yang berjalan selama penantian.
 *
 * Menyebut nama kanal yang dipilih, bukan satu kalimat generik: pilihan kanal
 * di atasnya harus terasa berakibat sesuatu, dan di layar inilah satu-satunya
 * tempat akibat itu bisa terlihat.
 */
const processingStage = ref(0);
let stageTimer = null;
let settleTimer = null;

const processingCaptions = computed(() => {
    const label = selectedChannel.value?.label ?? 'penyedia pembayaran';

    return [
        `Menghubungkan ke ${label}…`,
        'Memverifikasi pemilik akun…',
        'Menyelesaikan pembayaran…',
    ];
});

const startProcessing = () => {
    step.value = 'processing';
    processingStage.value = 0;

    stageTimer = window.setInterval(() => {
        processingStage.value = Math.min(processingStage.value + 1, processingCaptions.value.length - 1);
    }, MINIMUM_PROCESSING_MS / 3);
};

const stopProcessing = () => {
    window.clearInterval(stageTimer);
    stageTimer = null;
};

/** Tahan hasilnya sampai penantian minimumnya lewat, lalu tampilkan. */
const settleUi = (startedAt, nextStep) => {
    const remaining = Math.max(0, MINIMUM_PROCESSING_MS - (Date.now() - startedAt));

    settleTimer = window.setTimeout(() => {
        stopProcessing();
        step.value = nextStep;
    }, remaining);
};

const submit = () => {
    if (!props.invoice || !form.channel || form.password === '') return;

    const startedAt = Date.now();

    startProcessing();

    form.post(`/langganan/tagihan/${props.invoice.id}/bayar-cepat`, {
        preserveScroll: true,
        // Server menjawab `back()`, jadi prop halaman langganan sudah segar di
        // sini — termasuk baris tagihan yang barusan berubah lunas.
        onSuccess: () => {
            // Tidak semua jawaban tanpa galat validasi berarti terbayar:
            // tagihan yang keburu lunas lewat jalur lain kembali dengan flash
            // galat. Merayakannya sebagai berhasil adalah berbohong tentang
            // satu-satunya hal yang tidak boleh dibohongi di layar ini.
            const failed = Boolean(page.props.flash?.error);

            settleUi(startedAt, failed ? 'form' : 'success');

            if (!failed) form.reset('password');
        },
        onError: () => settleUi(startedAt, 'form'),
    });
};

/**
 * Tutup layar berhasil.
 *
 * Sekalian memuat ulang halamannya: pelunasan tagihan langganan memulihkan
 * status tenant dari `grace`/`suspended` menjadi aktif, dan itu menyentuh
 * spanduk serta panel di luar daftar tagihan.
 */
const finish = () => {
    emit('close');
    router.reload();
};

onBeforeUnmount(() => {
    stopProcessing();
    window.clearTimeout(settleTimer);
});
</script>

<template>
    <Modal
        :show="Boolean(invoice)"
        max-width="max-w-lg"
        :close-on-backdrop="step !== 'processing'"
        @close="close"
    >
        <template #header>
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-foreground">
                    {{ step === 'success' ? 'Pembayaran Berhasil' : 'Bayar Tagihan' }}
                </h3>
                <p v-if="invoice" class="mt-0.5 text-sm text-muted-foreground">
                    {{ invoice.kind === 'upgrade' ? 'Tambah pengguna' : 'Langganan' }} · {{ invoice.period }}
                </p>
            </div>
        </template>

        <div v-if="invoice">
            <!--
                Nominal, dan ia tetap terlihat di ketiga langkah: orang yang
                sedang mengetikkan kata sandinya berhak melihat berapa yang akan
                terbayar tanpa menggulir ke mana pun.
            -->
            <div class="rounded-xl border border-border bg-accent/30 px-4 py-3.5">
                <p class="text-xs text-muted-foreground">Total tagihan</p>
                <p class="mt-0.5 text-3xl font-semibold text-foreground tabular-nums">
                    {{ formatRupiah(invoice.amount) }}
                </p>
            </div>

            <!-- Langkah 1: pilih kanal, lalu kata sandi -->
            <form v-if="step === 'form'" class="mt-5" @submit.prevent="submit">
                <fieldset>
                    <legend class="text-sm font-medium text-foreground">Cara membayar</legend>

                    <div class="mt-2 grid gap-2">
                        <label
                            v-for="channel in channels"
                            :key="channel.code"
                            class="flex items-start gap-3 rounded-lg border p-3 cursor-pointer transition-colors"
                            :class="form.channel === channel.code
                                ? 'border-primary bg-primary/5'
                                : 'border-border bg-card hover:bg-accent/40'"
                        >
                            <input
                                v-model="form.channel"
                                type="radio"
                                name="checkout-channel"
                                :value="channel.code"
                                class="mt-0.5 accent-primary"
                            />
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-foreground">{{ channel.label }}</span>
                                <span class="block text-xs text-muted-foreground">{{ channel.hint }}</span>
                            </span>
                        </label>
                    </div>
                </fieldset>

                <p v-if="form.errors.channel" role="alert" class="mt-2 text-xs text-destructive">
                    {{ form.errors.channel }}
                </p>

                <!--
                    Kalimat di bawah medan kata sandi menjelaskan kenapa ia
                    diminta: medan kata sandi yang muncul tanpa alasan di layar
                    bayar adalah hal yang benar untuk dicurigai.
                -->
                <div class="mt-5">
                    <label for="checkout-password" class="text-sm font-medium text-foreground">
                        Kata sandi akun Anda
                    </label>
                    <input
                        id="checkout-password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="mt-1.5 w-full rounded-lg border bg-background px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-1"
                        :class="form.errors.password
                            ? 'border-destructive focus:border-destructive focus:ring-destructive'
                            : 'border-border focus:border-primary focus:ring-primary'"
                    />
                    <p v-if="form.errors.password" role="alert" class="mt-1.5 text-xs text-destructive">
                        {{ form.errors.password }}
                    </p>
                    <p v-else class="mt-1.5 text-xs text-muted-foreground leading-relaxed">
                        Diminta untuk memastikan yang menyetujui pembayaran ini memang pemilik usaha.
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="!form.channel || form.password === ''"
                    class="mt-5 w-full rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                >
                    Bayar {{ formatRupiah(invoice.amount) }}
                </button>

                <p v-if="isSimulated" class="mt-3 text-center text-xs text-muted-foreground">
                    Mode peragaan — tidak ada uang yang benar-benar berpindah.
                </p>
            </form>

            <!-- Langkah 2: memproses -->
            <div v-else-if="step === 'processing'" class="mt-6 flex flex-col items-center py-6 text-center">
                <svg class="h-10 w-10 animate-spin text-primary" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                    <path class="opacity-90" d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                </svg>

                <p class="mt-4 text-sm font-medium text-foreground" aria-live="polite">
                    {{ processingCaptions[processingStage] }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Jangan tutup jendela ini.
                </p>
            </div>

            <!-- Langkah 3: berhasil -->
            <div v-else class="mt-6 flex flex-col items-center py-4 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-full border border-success/40 bg-success/10">
                    <svg class="h-7 w-7 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <p class="mt-4 text-base font-semibold text-foreground">
                    {{ formatRupiah(invoice.amount) }} terbayar
                </p>
                <p class="mt-1 text-sm text-muted-foreground leading-relaxed">
                    Tagihan {{ invoice.period }} sudah lunas dan akses langganan Anda aktif kembali.
                    <template v-if="selectedChannel"> Dibayar lewat {{ selectedChannel.label }}.</template>
                </p>

                <button
                    type="button"
                    class="mt-5 w-full rounded-lg bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground hover:bg-primary/90"
                    @click="finish"
                >
                    Selesai
                </button>
            </div>
        </div>
    </Modal>
</template>
