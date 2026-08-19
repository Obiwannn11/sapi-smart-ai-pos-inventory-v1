<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AiQuotaMeter from '@/Components/AiQuotaMeter.vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';

// Halaman ini terbuka untuk SEMUA pengguna tenant, bukan owner saja — begitu
// tenant ditangguhkan setiap halaman lain mengarah ke sini. Cangkangnya tetap
// `OwnerLayout` supaya ia bukan pulau tersendiri; keberatan lamanya — sidebar
// penuh tautan yang memantul balik — dijawab di layout itu sendiri, yang
// merender navigasinya mati saat `auth.tenant.is_suspended`.
defineOptions({ layout: OwnerLayout });

const props = defineProps({
    tenant: { type: Object, required: true },
    subscription: { type: Object, required: true },
    consent: { type: Object, required: true },
    subsidy: { type: Object, required: true },
    invoices: { type: Array, required: true },
    upgrade: { type: Object, required: true },
    // Payment gateway ([BL-059]). `is_simulated` menandai driver tiruan, dan
    // halaman ini menyebutnya terus terang — tombol bayar yang terlihat
    // sungguhan padahal tiruan adalah cara termudah membuat orang mengira
    // uangnya sudah berpindah.
    payment: { type: Object, default: () => ({ enabled: false, is_simulated: false }) },
    // Kuota AI (`[BL-067]`(b)). Sebelum ini isi paket terpecah dua — kursi
    // dijawab di halaman ini, kuota AI hanya di Pengaturan — dan tidak ada satu
    // pun layar yang menjawab "paket saya dapat apa saja".
    aiQuota: { type: Object, default: null },
    // Kelas harga jalur Harga Tetap ([BL-041](b)). `null` untuk tenant jalur
    // Harga Adaptif — mereka sudah punya `subsidy.bracket`.
    classification: { type: Object, default: null },
});

/**
 * Server mengirim tanggal kalender polos ('2026-08-21'). `new Date(string)`
 * membacanya sebagai tengah malam UTC, sehingga tampilannya bergantung pada
 * zona waktu peramban — di zona yang di belakang UTC hasilnya mundur sehari.
 * Zona Indonesia kebetulan aman, tapi tanggal jatuh tempo yang benar hanya
 * karena kebetulan bukan tanggal yang benar. Komponennya dirakit sendiri agar
 * selalu dibaca sebagai tanggal lokal.
 */
const parseDate = (value) => {
    if (!value) return null;
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day);
};

const formatDate = (value) =>
    parseDate(value)?.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }) ?? null;

const formatRupiah = (value) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);

/** '2026-07' → 'Juli 2026'. Perkiraan menyebut bulan, bukan tanggal. */
const formatMonth = (value) => {
    if (!value) return null;
    const [year, month] = value.split('-').map(Number);
    return new Date(year, month - 1, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
};

const daysUntil = (value) => {
    const date = parseDate(value);
    if (!date) return null;
    return Math.ceil((date - new Date().setHours(0, 0, 0, 0)) / 86400000);
};

/**
 * Satu sumber untuk judul, nada, dan penjelasan tiap keadaan. Dipisah dari
 * template supaya kalimatnya bisa dibaca berdampingan — nada yang melompat
 * antar keadaan justru terlihat di sini, bukan saat tersebar di markup.
 */
const postTrial = computed(() => props.subscription.post_trial_plan ?? null);

const state = computed(() => {
    const trialEnds = formatDate(props.subscription.trial_ends_at);
    const periodEnds = formatDate(props.subscription.current_period_end);
    const suspendsAt = formatDate(props.subscription.suspends_at);

    switch (props.tenant.status) {
        case 'trial':
            return {
                tone: 'neutral',
                label: 'Masa coba',
                heading: 'Anda sedang dalam masa coba',
                // Perpindahan paketnya disebut di sini, bukan disimpan sampai
                // tagihan pertamanya terbit (`[BL-052]`(c)). "Anda bisa memilih
                // untuk berlangganan" adalah kalimat yang sudah tidak benar
                // sejak masa gratis berakhir dengan perpindahan otomatis —
                // menyisakannya berarti tenant membaca janji, lalu menerima
                // tagihan yang membantahnya.
                body: postTrial.value
                    ? `Semua fitur terbuka sampai ${trialEnds}. Setelah itu langganan Anda berlanjut di paket `
                        + `${postTrial.value.name} (${formatRupiah(postTrial.value.base_price)}/bulan), dan tagihan `
                        + 'pertamanya terbit sekitar sepekan sebelum tanggal tersebut.'
                    : `Semua fitur terbuka sampai ${trialEnds}.`,
            };
        case 'active':
            return {
                tone: 'positive',
                label: 'Aktif',
                heading: 'Langganan Anda aktif',
                body: `Periode berjalan sampai ${periodEnds}.`,
            };
        case 'grace':
            return {
                tone: 'warning',
                label: 'Masa tenggang',
                heading: 'Masa langganan sudah berakhir',
                body:
                    `Data lama tetap bisa dibuka dan diunduh, tapi transaksi dan perubahan baru tidak bisa disimpan. ` +
                    `Bila belum diselesaikan sampai ${suspendsAt}, akses akan ditutup sepenuhnya.`,
            };
        case 'suspended':
            return {
                tone: 'critical',
                label: 'Ditangguhkan',
                heading: 'Akses ditangguhkan',
                body: 'Selesaikan pembayaran untuk membuka kembali akses. Data Anda tidak dihapus.',
            };
        default:
            return { tone: 'neutral', label: '—', heading: 'Status langganan', body: '' };
    }
});

const toneClasses = {
    neutral: 'border-border bg-card text-foreground',
    positive: 'border-border bg-card text-foreground',
    warning: 'border-amber-500/40 bg-amber-500/10 text-foreground',
    critical: 'border-destructive/40 bg-destructive/10 text-foreground',
};

const trialDaysLeft = computed(() =>
    props.tenant.status === 'trial' ? daysUntil(props.subscription.trial_ends_at) : null,
);

/**
 * Warna khas per jalur harga — tiga identitas yang harus bisa dibedakan sekilas
 * tanpa membaca satu kata pun.
 *
 * Masa coba menang atas jalur, dan itu disengaja: selama gratis, yang paling
 * perlu diketahui pemilik bukan tarif mana yang akan berlaku melainkan bahwa ia
 * belum membayar apa pun. Jalurnya tetap disebut satu baris di bawah, jadi tidak
 * ada yang disembunyikan — hanya diurutkan.
 *
 * Kelas ditulis UTUH, bukan dirakit dari potongan (`text-${c}-600`). Tailwind
 * memindai berkas sebagai teks; kelas yang baru terbentuk saat runtime tidak
 * pernah ikut ter-generate dan warnanya diam-diam hilang di build produksi.
 */
const trackIdentity = computed(() => {
    if (props.tenant.status === 'trial') {
        return {
            name: 'Masa Coba',
            tagline: 'Semua fitur terbuka, belum ada tagihan.',
            chip: 'border-violet-500/40 bg-violet-500/10 text-violet-700 dark:text-violet-300',
            strip: 'border-violet-500/30 bg-violet-500/[0.07]',
            accent: 'bg-violet-500',
            icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        };
    }

    if (props.subscription.pricing_track === 'subsidized') {
        return {
            name: 'Harga Adaptif',
            tagline: 'Tarif mengikuti omzet bulanan usaha Anda.',
            chip: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
            strip: 'border-emerald-500/30 bg-emerald-500/[0.07]',
            accent: 'bg-emerald-500',
            icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
        };
    }

    return {
        name: 'Harga Tetap',
        tagline: 'Tarif sama tiap bulan, tidak bergantung omzet.',
        chip: 'border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-300',
        strip: 'border-sky-500/30 bg-sky-500/[0.07]',
        accent: 'bg-sky-500',
        icon: 'M5 13l4 4L19 7',
    };
});

/** Bar pemakaian kursi. Dibatasi 100% supaya kelebihan kursi tidak meluber. */
/** Nilai satu dimensi dasar kelas, diformat menurut satuannya. */
const formatBasisValue = (item) => item.display ?? (item.unit === 'currency' ? formatRupiah(item.value) : item.value);

const seatPercent = computed(() => {
    const { seats, seats_used: used } = props.subscription;
    if (!seats) return 0;
    return Math.min(100, Math.round((used / seats) * 100));
});

/**
 * Tiga keadaan persetujuan, bukan dua. "Pernah setuju tapi teksnya sudah
 * diganti" bukan hal yang sama dengan "belum pernah setuju": yang pertama bukan
 * kelalaian tenant, dan menyebutnya begitu menuduh orang atas perubahan yang
 * kami sendiri yang melakukannya.
 */
const consentState = computed(() => {
    if (props.consent.agreed) {
        return {
            key: 'agreed',
            label: 'Disetujui',
            chip: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
            body: 'Anda sudah menyetujui ketentuan yang berlaku. Dokumennya bisa dibaca kembali kapan saja.',
        };
    }

    if (props.consent.agreed_version) {
        return {
            key: 'outdated',
            label: 'Perlu disetujui ulang',
            chip: 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300',
            body: `Anda menyetujui versi ${props.consent.agreed_version}, sementara yang berlaku sekarang versi ${props.consent.current_version}. Bacalah perubahannya lalu setujui ulang.`,
        };
    }

    return {
        key: 'pending',
        label: 'Belum disetujui',
        chip: 'border-border bg-muted text-muted-foreground',
        body: 'Dokumen ini menjelaskan apa yang kami lihat dan apa yang tidak. Bacalah sebelum menyetujuinya.',
    };
});

/**
 * Kesimpulan perkiraan Harga Adaptif untuk tenant jalur tetap — satu kalimat
 * yang menjawab "apakah saya termasuk yang bisa dapat tarif lebih rendah".
 *
 * Urutan penjagaannya penting: tanpa penjualan, angka omzet nol akan jatuh ke
 * kelompok termurah dan menjanjikan penghematan yang tidak nyata.
 */
const subsidyEstimate = computed(() => {
    const estimate = props.subsidy.estimate;
    if (!estimate) return null;

    const month = formatMonth(estimate.period);

    if (estimate.transaction_count === 0) {
        return {
            ...estimate,
            month,
            tone: 'neutral',
            headline: 'Belum bisa diperkirakan',
            body: `Belum ada penjualan tercatat sepanjang ${month}, jadi perkiraan tarifnya belum berarti apa-apa. Coba lihat lagi setelah satu bulan penuh berjalan.`,
        };
    }

    if (estimate.price === null) {
        return {
            ...estimate,
            month,
            tone: 'neutral',
            headline: 'Tarif adaptif belum bisa dihitung',
            body: `Omzet Anda pada ${month} ${formatRupiah(estimate.revenue)}, tapi belum ada kelompok tarif yang cocok untuknya. Hubungi kami bila Anda tetap ingin pindah jalur.`,
        };
    }

    if (estimate.is_cheaper) {
        return {
            ...estimate,
            month,
            tone: 'positive',
            headline: 'Omzet Anda masuk kelompok bertarif lebih rendah',
            body: `Dengan omzet ${formatRupiah(estimate.revenue)} pada ${month}, Anda masuk kelompok ${estimate.label} — tarifnya ${formatRupiah(estimate.price)}/bulan, ${formatRupiah(estimate.current_price - estimate.price)} lebih murah daripada tarif Anda sekarang.`,
        };
    }

    return {
        ...estimate,
        month,
        tone: 'neutral',
        headline: 'Harga Tetap masih lebih menguntungkan',
        body: `Dengan omzet ${formatRupiah(estimate.revenue)} pada ${month}, tarif adaptif Anda menjadi ${formatRupiah(estimate.price)}/bulan — tidak lebih murah daripada ${formatRupiah(estimate.current_price)} yang Anda bayar sekarang. Pindah jalur tidak menguntungkan Anda saat ini.`,
    };
});

/**
 * Kenapa pengajuan Harga Adaptif tertutup, atau null bila terbuka.
 *
 * Server mengirim ALASANNYA, bukan cuma boleh-tidaknya (`[BL-055]`(c)). Ketiga
 * sebab punya jalan keluar yang berbeda — menunggu, membayar penuh, atau tidak
 * melakukan apa-apa — dan kalimat tunggal "belum bisa" memaksa tenant menebak
 * yang mana.
 */
const subsidyBlocked = computed(() => {
    if (props.subsidy.can_switch) return null;

    if (props.subsidy.reason === 'above_ceiling') {
        return `Omzet Anda ${formatRupiah(props.subsidy.measured_revenue)} — di atas batas ${formatRupiah(props.subsidy.ceiling)} untuk keringanan. Harga Adaptif ditujukan untuk usaha beromzet rendah, jadi jalur yang berlaku bagi Anda adalah paket berbayar penuh.`;
    }

    if (props.subsidy.reason === 'cooldown') {
        return `Perpindahan jalur berikutnya bisa diajukan mulai ${formatDate(props.subsidy.switch_available_at)}.`;
    }

    return null;
});

const estimateTones = {
    positive: 'border-emerald-500/40 bg-emerald-500/[0.07]',
    neutral: 'border-border bg-muted/40',
};

// --- Tambah & lepas pengguna ---
const upgradeForm = useForm({ additional_seats: 1 });
const releaseForm = useForm({ released_seats: 1 });

const submitUpgrade = () => upgradeForm.post('/langganan/tambah-pengguna', { preserveScroll: true });
const submitRelease = () => releaseForm.post('/langganan/lepas-pengguna', { preserveScroll: true });

// Biaya BULANAN, bukan sekali bayar (`[BL-053]`). Kalimatnya harus menyebut
// satuannya, kalau tidak angka yang sama persis akan terbaca sebagai harga beli.
const upgradeCost = computed(() => formatRupiah(props.upgrade.extra_seat_price * upgradeForm.additional_seats));

// Selama paketnya mematok Rp 0 per pengguna tambahan, tidak ada yang bertambah
// di tagihan — jadi panelnya tidak boleh menjanjikan sebaliknya. Turunan dari
// harga, bukan saklar tersendiri.
const seatsAreFree = computed(() => props.upgrade.extra_seat_price <= 0);

// Biaya seat tambahan yang berjalan sekarang. Ditampilkan sebagai hak beserta
// asalnya — bukan sebagai statistik pemakaian: sejak dasarnya pembelian, kata
// "puncak"/"pemakaian tertinggi" tidak lagi menjelaskan tagihan apa pun dan
// justru menyesatkan siapa pun yang mencocokkannya dengan angka di layar.
const extraSeatsCost = computed(() =>
    formatRupiah(props.upgrade.extra_seat_price * props.subscription.extra_seats),
);

// --- Unggah bukti bayar ---
const proofTarget = ref(null);
const proofForm = useForm({ proof: null });

const openProof = (invoice) => {
    proofForm.reset();
    proofForm.clearErrors();
    proofTarget.value = invoice;
};

const submitProof = () => {
    proofForm.post(`/langganan/tagihan/${proofTarget.value.id}/bukti`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { proofTarget.value = null; },
    });
};

// --- Jalur Harga Adaptif ---
const revokeForm = useForm({});
const confirmingRevoke = ref(false);

const revokeSubsidy = () => {
    revokeForm.post('/langganan/subsidi/cabut', {
        preserveScroll: true,
        onSuccess: () => { confirmingRevoke.value = false; },
    });
};

const invoiceStatusLabels = {
    unpaid: 'Belum dibayar',
    awaiting_verification: 'Menunggu diperiksa',
    paid: 'Lunas',
    rejected: 'Ditolak',
};
</script>

<template>
    <Head title="Langganan" />

    <div>
        <div class="mx-auto w-full max-w-lg">

            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold text-foreground tracking-tight">Langganan</h1>
                    <p class="mt-1.5 text-sm text-muted-foreground truncate">{{ tenant.name }}</p>
                </div>

                <span
                    :class="['inline-flex items-center gap-1.5 shrink-0 rounded-full border px-3 py-1 text-xs font-semibold', trackIdentity.chip]"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="trackIdentity.icon" />
                    </svg>
                    {{ trackIdentity.name }}
                </span>
            </div>

            <div :class="['mt-6 rounded-xl border px-5 py-5', toneClasses[state.tone]]">
                <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">{{ state.label }}</p>
                <h2 class="mt-2 text-lg font-semibold text-foreground">{{ state.heading }}</h2>
                <p class="mt-2 text-sm text-muted-foreground leading-relaxed">{{ state.body }}</p>

                <p v-if="trialDaysLeft !== null" class="mt-3 text-sm font-medium text-foreground tabular-nums">
                    Sisa {{ trialDaysLeft }} hari
                </p>
            </div>

            <!-- Detail paket, dengan kepala berwarna khas jalur harganya -->
            <div class="mt-6 rounded-xl border border-border bg-card overflow-hidden">
                <div :class="['flex items-start gap-3 border-b px-5 py-4', trackIdentity.strip]">
                    <span :class="['mt-0.5 w-1 self-stretch rounded-full', trackIdentity.accent]" aria-hidden="true" />
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-foreground">{{ trackIdentity.name }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground leading-relaxed">{{ trackIdentity.tagline }}</p>
                    </div>
                </div>

                <dl class="divide-y divide-border">
                    <div class="flex items-baseline justify-between px-5 py-3.5">
                        <dt class="text-sm text-muted-foreground">Paket</dt>
                        <dd class="text-sm font-medium text-foreground">{{ subscription.plan_name }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between px-5 py-3.5">
                        <dt class="text-sm text-muted-foreground">Tarif</dt>
                        <dd class="text-sm font-medium text-foreground tabular-nums">
                            {{ formatRupiah(subscription.effective_price) }}
                            <span class="text-muted-foreground font-normal">/bulan</span>
                        </dd>
                    </div>
                    <!-- Kelas harga, tepat di bawah tarif yang ia jelaskan
                         (`[BL-041]`(b)). Hanya muncul di jalur Harga Tetap:
                         tenant Harga Adaptif sudah punya kartu kelompoknya
                         sendiri, lengkap dengan omzet yang mendasarinya. -->
                    <div v-if="classification" class="px-5 py-3.5">
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-sm text-muted-foreground">Kelas harga</dt>
                            <dd class="text-sm font-medium text-foreground text-right">{{ classification.label }}</dd>
                        </div>

                        <!-- Dua sebab, dua kalimat. "Tarifnya cocok dengan
                             sebuah aturan" dan "tidak ada aturan yang cocok,
                             jadi berlaku tarif paket" adalah dua jawaban
                             berbeda atas pertanyaan yang sama, dan meleburnya
                             jadi satu kalimat akan salah pada separuh
                             pembacanya. -->
                        <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                            <template v-if="classification.source === 'rule' && classification.basis.length">
                                Berlaku karena
                                <template v-for="(item, i) in classification.basis" :key="item.name">
                                    <span v-if="i > 0"> dan </span>
                                    <span class="text-foreground font-medium">{{ item.label.toLowerCase() }} {{ formatBasisValue(item) }}</span>
                                </template>.
                            </template>
                            <template v-else-if="classification.source === 'rule'">
                                Berlaku dari aturan tarif yang cocok untuk usaha Anda.
                            </template>
                            <template v-else>
                                Tidak ada aturan tarif khusus yang cocok, jadi yang berlaku adalah tarif paket.
                            </template>
                        </p>

                        <!-- Batas privasi, disebut terus terang. Tenant jalur
                             Harga Tetap tidak pernah membuka data penjualannya,
                             dan kartu ini membuktikannya: yang disebut hanya
                             dimensi yang memang tidak menuntut persetujuan. -->
                        <p class="mt-1.5 text-xs text-muted-foreground/80 leading-relaxed">
                            Kelas ini ditentukan tanpa melihat penjualan Anda.
                        </p>
                    </div>

                    <div class="flex items-baseline justify-between px-5 py-3.5">
                        <dt class="text-sm text-muted-foreground">Pengguna tambahan</dt>
                        <dd class="text-sm font-medium text-foreground tabular-nums">
                            {{ formatRupiah(upgrade.extra_seat_price) }}
                            <span class="text-muted-foreground font-normal">/pengguna/bulan</span>
                        </dd>
                    </div>
                    <div v-if="subscription.current_period_end" class="flex items-baseline justify-between px-5 py-3.5">
                        <dt class="text-sm text-muted-foreground">
                            {{ tenant.status === 'trial' ? 'Masa coba berakhir' : 'Periode berjalan sampai' }}
                        </dt>
                        <dd class="text-sm font-medium text-foreground">
                            {{ formatDate(tenant.status === 'trial' ? subscription.trial_ends_at : subscription.current_period_end) }}
                        </dd>
                    </div>

                    <!-- Tarif setelah masa gratis mendapat barisnya sendiri di
                         ringkasan, bukan hanya kalimat di kartu keadaan: inilah
                         angka yang akan ditagihkan, dan angka yang menentukan
                         keputusan tidak boleh hanya lewat sebagai narasi. -->
                    <div v-if="postTrial" class="flex items-baseline justify-between px-5 py-3.5">
                        <dt class="text-sm text-muted-foreground">Setelah masa coba</dt>
                        <dd class="text-sm font-medium text-foreground">
                            {{ postTrial.name }} — {{ formatRupiah(postTrial.base_price) }}/bulan
                        </dd>
                    </div>

                    <!-- Kursi dapat barisnya sendiri, dan yang ditampilkan
                         adalah HAK beserta asalnya — bukan statistik pemakaian
                         (`[BL-053]`). Sejak seat tambahan ditagih karena dibeli,
                         "terpakai sekian" tidak lagi menjelaskan tagihan apa
                         pun; ia tinggal jadi petunjuk kapan kursi sebaiknya
                         dilepas. -->
                    <div class="px-5 py-3.5">
                        <div class="flex items-baseline justify-between">
                            <dt class="text-sm text-muted-foreground">Pengguna</dt>
                            <dd class="text-sm font-medium text-foreground tabular-nums">
                                {{ subscription.seats }} kursi
                            </dd>
                        </div>

                        <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                            {{ subscription.included_seats }} dari paket {{ subscription.plan_name }}<template v-if="subscription.extra_seats > 0">,
                            <span class="text-foreground font-medium">{{ subscription.extra_seats }} kursi tambahan yang Anda beli</span>
                            ({{ formatRupiah(upgrade.extra_seat_price) }}/kursi/bulan = {{ extraSeatsCost }}/bulan)</template>.
                            Terpakai {{ subscription.seats_used }}, tersisa {{ Math.max(0, subscription.seats - subscription.seats_used) }}.
                        </p>

                        <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                :class="['h-full rounded-full transition-[width] duration-300', seatPercent >= 100 ? 'bg-amber-500' : trackIdentity.accent]"
                                :style="{ width: `${seatPercent}%` }"
                            />
                        </div>
                        <p v-if="seatPercent >= 100" class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                            Kursi Anda sudah penuh. Tambah pengguna di bawah bila perlu menambah staf.
                        </p>

                        <!-- Pelepasan yang sudah dijadwalkan. Tanggalnya wajib
                             disebut: sampai hari itu kursinya masih boleh
                             dipakai, dan tenant yang hanya melihat "akan
                             dilepas" akan mengira kursinya hilang hari ini. -->
                        <p v-if="upgrade.release_at" class="mt-2 text-xs text-muted-foreground">
                            Pelepasan kursi tercatat: mulai {{ formatDate(upgrade.release_at) }} kursi tambahan Anda
                            menjadi {{ upgrade.scheduled_seats }}. Sampai tanggal itu semuanya masih bisa dipakai.
                        </p>
                    </div>

                    <!-- Kuota AI, di blok yang sama dengan kursi (`[BL-067]`(b)).
                         Memakai meteran yang sudah ada, bukan menggambar ulang
                         angkanya: yang dibacakan di sini dan yang dibacakan di
                         Pengaturan wajib identik, dan dua tempat yang menghitung
                         sendiri-sendiri adalah cara termudah membuatnya tidak.

                         Varian `compact`, BUKAN `detailed` meski peran halaman
                         ini lebih dekat ke Pengaturan. Alasannya bukan selera:
                         teks varian `detailed` berbunyi "kolom API Key di bawah"
                         dan "isi kunci API Anda sendiri di bawah" — benar di
                         Pengaturan, karena kolomnya memang ada di sana, dan
                         salah di halaman ini. Varian `compact` tidak terikat
                         tempat, dan jalan keluarnya berupa tautan ke halaman
                         kredensial, bukan tunjuk-arah ke bawah. -->
                    <div v-if="aiQuota" class="px-5 py-3.5">
                        <dt class="text-sm text-muted-foreground">Analisis AI</dt>
                        <AiQuotaMeter :quota="aiQuota" variant="compact" class="mt-2" />
                    </div>
                </dl>
            </div>

            <div class="mt-6 rounded-xl border border-border bg-card px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-medium text-foreground">Persetujuan langganan</p>
                    <span :class="['shrink-0 rounded-full border px-2.5 py-0.5 text-xs font-semibold', consentState.chip]">
                        {{ consentState.label }}
                    </span>
                </div>

                <p class="mt-1.5 text-sm text-muted-foreground leading-relaxed">{{ consentState.body }}</p>

                <!-- Jejak buktinya: versi mana, kapan, oleh siapa. "Sudah
                     disetujui" tanpa ketiganya menyuruh tenant percaya begitu
                     saja pada catatan yang tak bisa ia periksa. -->
                <dl v-if="consentState.key === 'agreed'" class="mt-3 space-y-1 text-xs text-muted-foreground">
                    <div class="flex gap-2">
                        <dt class="w-20 shrink-0">Versi</dt>
                        <dd class="text-foreground">{{ consent.agreed_version }}</dd>
                    </div>
                    <div v-if="consent.agreed_at" class="flex gap-2">
                        <dt class="w-20 shrink-0">Tanggal</dt>
                        <dd class="text-foreground">{{ formatDate(consent.agreed_at) }}</dd>
                    </div>
                    <div v-if="consent.agreed_by" class="flex gap-2">
                        <dt class="w-20 shrink-0">Oleh</dt>
                        <dd class="text-foreground">{{ consent.agreed_by }}</dd>
                    </div>
                </dl>

                <Link
                    href="/langganan/persetujuan"
                    :class="[
                        'mt-3 inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-medium transition-colors duration-150',
                        consentState.key === 'agreed'
                            ? 'border border-border text-foreground hover:bg-accent/40'
                            : 'bg-primary text-primary-foreground hover:bg-primary/90',
                    ]"
                >
                    {{ consentState.key === 'agreed' ? 'Baca dokumen persetujuan' : 'Baca dan setujui' }}
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>

            <!-- Jalur Harga Adaptif -->
            <div
                :class="[
                    'mt-6 rounded-xl border bg-card px-5 py-4',
                    subsidy.is_active ? 'border-emerald-500/40' : 'border-border',
                ]"
            >
                <div class="flex items-start justify-between gap-3">
                    <p class="text-sm font-medium text-foreground">Harga Adaptif</p>
                    <span
                        v-if="subsidy.is_active"
                        class="shrink-0 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300"
                    >
                        Aktif
                    </span>
                </div>

                <template v-if="subsidy.is_active">
                    <div v-if="subsidy.bracket" class="mt-3 rounded-lg border border-emerald-500/30 bg-emerald-500/[0.07] px-4 py-3">
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">
                            Kelompok {{ subsidy.bracket.label }} · {{ formatMonth(subsidy.bracket.period) }}
                        </p>
                        <p class="mt-1.5 text-lg font-semibold text-foreground tabular-nums">
                            {{ formatRupiah(subsidy.bracket.price) }}
                            <span class="text-sm font-normal text-muted-foreground">/bulan</span>
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Dihitung dari omzet {{ formatRupiah(subsidy.bracket.revenue) }}.
                        </p>
                    </div>
                    <p v-else class="mt-1 text-sm text-muted-foreground leading-relaxed">
                        Belum ada penjualan tercatat di bulan yang sudah tutup, jadi kelompok tarif Anda belum bisa
                        ditentukan. Angkanya muncul di sini setelah satu bulan penuh berjalan.
                    </p>

                    <!-- Dua sebab, dua kalimat. Pencabutan sukarela dan
                         pemindahan karena omzet melewati ambang sama-sama
                         berakhir di Harga Tetap, tapi hanya yang kedua menaikkan
                         tagihan seseorang tanpa ia meminta apa pun — dan itu
                         harus terbaca sebagai pemberitahuan, bukan konfirmasi. -->
                    <p
                        v-if="subsidy.reverts_at && subsidy.revert_reason === 'above_ceiling'"
                        class="mt-2 rounded-lg border border-amber-500/40 bg-amber-500/10 px-3.5 py-2.5 text-sm text-foreground leading-relaxed"
                    >
                        Omzet Anda melewati batas keringanan, jadi tarif adaptif berlaku sampai
                        {{ formatDate(subsidy.reverts_at) }} — setelah itu tarif Anda mengikuti paket berbayar
                        penuh. Tagihan yang sudah terbit tidak berubah.
                        <Link href="/langganan/harga-adaptif" class="font-medium underline underline-offset-2">
                            Lihat rinciannya
                        </Link>
                    </p>

                    <p v-else-if="subsidy.reverts_at" class="mt-2 text-sm text-foreground">
                        Persetujuan sudah dicabut. Tarif adaptif berlaku sampai {{ formatDate(subsidy.reverts_at) }},
                        setelah itu kembali ke Harga Tetap.
                    </p>

                    <div v-else-if="tenant.is_owner" class="mt-3">
                        <button
                            v-if="!confirmingRevoke"
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-destructive/40 px-3.5 py-2 text-sm font-medium text-destructive transition-colors duration-150 hover:bg-destructive/10"
                            @click="confirmingRevoke = true"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                            Cabut persetujuan Harga Adaptif
                        </button>

                        <div v-else class="rounded-lg border border-destructive/40 bg-destructive/10 px-3.5 py-3">
                            <p class="text-sm text-foreground leading-relaxed">
                                Ringkasan omzet Anda dihapus seketika. Tarif adaptif tetap berlaku sampai akhir
                                periode berjalan, jadi tagihan Anda tidak naik mendadak.
                            </p>
                            <div class="mt-3 flex gap-3">
                                <button
                                    :disabled="revokeForm.processing"
                                    class="px-3 py-1.5 text-xs font-medium bg-destructive text-white rounded-lg hover:bg-destructive/90 disabled:opacity-50"
                                    @click="revokeSubsidy"
                                >
                                    Ya, cabut
                                </button>
                                <button class="px-3 py-1.5 text-xs font-medium text-foreground border border-border rounded-lg hover:bg-accent/40" @click="confirmingRevoke = false">
                                    Batal
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <p class="mt-1 text-sm text-muted-foreground leading-relaxed">
                        Tarif yang mengikuti omzet usaha Anda, bukan daftar harga tetap. Sebagai gantinya, omzet
                        bulanan Anda dihitung otomatis dan angka persisnya bisa dilihat pengelola layanan untuk
                        menentukan tarif. Bacalah dokumennya sebelum memutuskan.
                    </p>

                    <!-- Perkiraan: apakah omzetnya memang masuk kelompok yang
                         lebih murah. Angkanya dihitung untuk mata pemiliknya
                         sendiri dan tidak dikirim ke mana pun sebelum ia setuju
                         — kalimat terakhir di bawah ada supaya itu tidak perlu
                         ditebak. -->
                    <div v-if="subsidyEstimate" :class="['mt-3 rounded-lg border px-4 py-3', estimateTones[subsidyEstimate.tone]]">
                        <p class="text-sm font-semibold text-foreground">{{ subsidyEstimate.headline }}</p>
                        <p class="mt-1 text-sm text-muted-foreground leading-relaxed">{{ subsidyEstimate.body }}</p>

                        <div v-if="subsidyEstimate.price !== null && subsidyEstimate.transaction_count > 0" class="mt-3 flex items-end gap-4">
                            <div>
                                <p class="text-xs text-muted-foreground">Tarif sekarang</p>
                                <p class="text-sm font-medium text-foreground tabular-nums">
                                    {{ formatRupiah(subsidyEstimate.current_price) }}
                                </p>
                            </div>
                            <span class="pb-1 text-muted-foreground" aria-hidden="true">&rarr;</span>
                            <div>
                                <p class="text-xs text-muted-foreground">Perkiraan adaptif</p>
                                <p
                                    :class="[
                                        'text-sm font-semibold tabular-nums',
                                        subsidyEstimate.is_cheaper ? 'text-emerald-700 dark:text-emerald-300' : 'text-foreground',
                                    ]"
                                >
                                    {{ formatRupiah(subsidyEstimate.price) }}
                                </p>
                            </div>
                        </div>

                        <p class="mt-3 text-xs text-muted-foreground leading-relaxed">
                            Perkiraan, bukan janji: tarif sesungguhnya dihitung ulang tiap bulan dari omzet bulan
                            yang baru tutup. Angka ini belum dikirim ke mana pun — omzet Anda baru mulai dihitung
                            dan dibagikan setelah Anda menyetujui ketentuannya.
                        </p>
                    </div>

                    <!-- Penolakan menyebut sebabnya, bukan sekadar menutup
                         pintunya (`[BL-055]`(c)). Omzet di atas ambang dan masa
                         tunggu tiga bulan menuntut tindakan yang sama sekali
                         berbeda dari tenant, dan hanya kalimat yang menyebut
                         yang mana bisa menunjukkannya. -->
                    <p v-if="subsidyBlocked" class="mt-3 text-sm text-foreground leading-relaxed">
                        {{ subsidyBlocked }}
                    </p>

                    <!-- Tujuannya halaman pengajuan, bukan langsung ke dokumen
                         persetujuan. Meminta orang menyetujui pembukaan data
                         penjualannya sebelum ia melihat tangga tarifnya adalah
                         tukar-menukar yang tidak seimbang. -->
                    <Link
                        v-if="tenant.is_owner"
                        href="/langganan/harga-adaptif"
                        :class="[
                            'mt-3 inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-medium transition-colors duration-150',
                            subsidy.can_switch && subsidyEstimate?.is_cheaper
                                ? 'bg-emerald-600 text-white hover:bg-emerald-600/90'
                                : 'border border-border text-foreground hover:bg-accent/40',
                        ]"
                    >
                        {{ subsidy.can_switch && subsidyEstimate?.is_cheaper ? 'Ajukan Harga Adaptif' : 'Lihat Harga Adaptif' }}
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                    <p v-else class="mt-3 text-xs text-muted-foreground">
                        Perpindahan jalur harga adalah keputusan pemilik usaha.
                    </p>
                </template>
            </div>

            <!-- Tambah & lepas pengguna -->
            <div v-if="tenant.is_owner" class="mt-6 rounded-xl border border-border bg-card px-5 py-4">
                <p class="text-sm font-medium text-foreground">Tambah pengguna</p>

                <p v-if="seatsAreFree" class="mt-1 text-sm text-muted-foreground leading-relaxed">
                    Paket {{ subscription.plan_name }} tidak menagih biaya per pengguna, jadi penambahannya
                    langsung aktif dan tidak menambah tagihan apa pun.
                </p>
                <p v-else class="mt-1 text-sm text-muted-foreground leading-relaxed">
                    Kursi baru langsung bisa dipakai dan <span class="text-foreground font-medium">gratis sampai periode ini habis</span>.
                    Sesudah itu ia masuk tagihan bulanan sebesar {{ formatRupiah(upgrade.extra_seat_price) }} per kursi —
                    tidak ada tagihan terpisah di tengah bulan.
                </p>

                <p v-if="upgrade.has_open_request" class="mt-3 text-sm text-foreground">
                    Ada tagihan penambahan pengguna lama yang belum selesai. Selesaikan tagihannya di bawah dulu.
                </p>

                <form v-else class="mt-3 flex flex-wrap items-end gap-3" @submit.prevent="submitUpgrade">
                    <div>
                        <label for="additional-seats" class="block text-xs font-medium text-muted-foreground mb-1">Jumlah</label>
                        <input
                            id="additional-seats"
                            v-model.number="upgradeForm.additional_seats"
                            type="number"
                            min="1"
                            max="20"
                            class="w-24 px-3 py-2 border border-border rounded-lg text-sm bg-card text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="upgradeForm.processing"
                        class="px-4 py-2 bg-primary text-primary-foreground text-sm font-semibold rounded-lg hover:bg-primary/90 disabled:opacity-50"
                    >
                        {{ seatsAreFree ? 'Tambah pengguna · gratis' : `Tambah pengguna · ${upgradeCost}/bulan` }}
                    </button>
                </form>

                <p v-if="upgradeForm.errors.additional_seats" role="alert" class="mt-2 text-xs text-destructive">
                    {{ upgradeForm.errors.additional_seats }}
                </p>

                <!-- Pelepasan. Wajib ada sejak tagihan mengikuti pembelian:
                     tanpa jalan turun, tenant yang mengecil terkunci membayar
                     selamanya (`[BL-053]`). Hanya dirender bila memang ada yang
                     bisa dilepas — tombol mati tanpa penjelasan lebih buruk
                     daripada tidak ada tombol. -->
                <template v-if="!seatsAreFree && subscription.extra_seats > 0">
                    <hr class="my-4 border-border" />

                    <p class="text-sm font-medium text-foreground">Lepas pengguna tambahan</p>

                    <p v-if="upgrade.release_at" class="mt-1 text-sm text-muted-foreground leading-relaxed">
                        Sudah ada pelepasan yang tercatat, berlaku {{ formatDate(upgrade.release_at) }}.
                        Menambah pengguna lagi akan membatalkannya.
                    </p>
                    <p v-else-if="upgrade.releasable_seats < 1" class="mt-1 text-sm text-muted-foreground leading-relaxed">
                        Semua kursi Anda sedang dipakai staf aktif. Nonaktifkan salah satu staf dulu, baru kursinya
                        bisa dilepas — pelepasan tidak akan mematikan akun siapa pun.
                    </p>
                    <template v-else>
                        <p class="mt-1 text-sm text-muted-foreground leading-relaxed">
                            Berlaku di akhir periode berikutnya, bukan hari ini: kursinya masih bisa dipakai selama
                            periode yang sudah ditagihkan. Paling banyak {{ upgrade.releasable_seats }} kursi sekarang.
                        </p>

                        <form class="mt-3 flex flex-wrap items-end gap-3" @submit.prevent="submitRelease">
                            <div>
                                <label for="released-seats" class="block text-xs font-medium text-muted-foreground mb-1">Jumlah</label>
                                <input
                                    id="released-seats"
                                    v-model.number="releaseForm.released_seats"
                                    type="number"
                                    min="1"
                                    :max="upgrade.releasable_seats"
                                    class="w-24 px-3 py-2 border border-border rounded-lg text-sm bg-card text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                                />
                            </div>
                            <button
                                type="submit"
                                :disabled="releaseForm.processing"
                                class="px-4 py-2 border border-border text-foreground text-sm font-semibold rounded-lg hover:bg-muted disabled:opacity-50"
                            >
                                Lepas kursi
                            </button>
                        </form>

                        <p v-if="releaseForm.errors.released_seats" role="alert" class="mt-2 text-xs text-destructive">
                            {{ releaseForm.errors.released_seats }}
                        </p>
                    </template>
                </template>
            </div>

            <!-- Tagihan -->
            <div class="mt-6 rounded-xl border border-border bg-card overflow-hidden">
                <p class="px-5 pt-4 text-sm font-medium text-foreground">Tagihan</p>

                <ul class="mt-2 divide-y divide-border">
                    <li v-for="invoice in invoices" :key="invoice.id" class="px-5 py-3.5">
                        <div class="flex items-baseline justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-foreground">
                                    {{ invoice.kind === 'upgrade' ? 'Tambah pengguna' : 'Langganan' }} · {{ invoice.period }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ invoiceStatusLabels[invoice.status] }} · jatuh tempo {{ formatDate(invoice.due_date) }}
                                </p>
                                <!--
                                    Kenapa nominalnya begini (`[BL-057]`(a)).
                                    Bukan `text-destructive` seperti penolakan
                                    bukti di bawahnya: ini keterangan, bukan
                                    kabar buruk — sering kali justru potongan
                                    harga.
                                -->
                                <p v-if="invoice.amount_reason" class="mt-1 text-xs text-muted-foreground">
                                    {{ invoice.amount_reason }}
                                </p>
                                <p v-if="invoice.rejection_reason" class="mt-1 text-xs text-destructive">
                                    {{ invoice.rejection_reason }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-medium text-foreground tabular-nums">{{ formatRupiah(invoice.amount) }}</p>

                                <!--
                                    Tindakan utama, karena inilah yang selesai
                                    sendiri: bayar lalu akses pulih tanpa
                                    menunggu siapa pun memeriksa apa pun.
                                    Unggah bukti tetap ada di bawahnya — banyak
                                    yang memang membayar dengan transfer biasa,
                                    dan ia satu-satunya jalur yang tetap jalan
                                    ketika gateway sedang mati.
                                -->
                                <Link
                                    v-if="payment.enabled && tenant.is_owner && invoice.status !== 'paid'"
                                    :href="`/langganan/tagihan/${invoice.id}/bayar`"
                                    class="mt-1.5 block rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-primary-foreground hover:bg-primary/90"
                                >
                                    Bayar sekarang
                                </Link>

                                <button
                                    v-if="tenant.is_owner && invoice.status !== 'paid'"
                                    class="mt-1 text-xs font-medium text-primary hover:text-primary/80"
                                    @click="openProof(invoice)"
                                >
                                    {{ invoice.has_proof ? 'Unggah ulang bukti' : 'Unggah bukti transfer' }}
                                </button>
                            </div>
                        </div>
                    </li>

                    <li v-if="invoices.length === 0" class="px-5 py-6 text-sm text-muted-foreground">
                        Belum ada tagihan.
                    </li>
                </ul>
            </div>

            <p class="mt-6 text-xs text-muted-foreground leading-relaxed">
                <template v-if="payment.enabled">
                    Bayar lewat QRIS, transfer virtual account, atau e-wallet — akses langganan pulih sendiri
                    begitu pembayarannya masuk. Transfer manual tetap bisa: unggah buktinya di sini dan kami
                    periksa menyusul.
                    <span v-if="payment.is_simulated" class="text-warning-foreground">
                        Saat ini kanal pembayaran masih berupa peragaan, jadi tidak ada uang yang benar-benar berpindah.
                    </span>
                </template>
                <template v-else>
                    Pembayaran masih dicatat manual: transfer, lalu unggah buktinya di sini. Kami periksa dan
                    mengonfirmasi menyusul.
                </template>
            </p>

            <!-- Unggah bukti -->
            <Teleport to="body">
                <div v-if="proofTarget" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/50" @click="proofTarget = null" />
                    <div class="relative w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-2xl">
                        <h3 class="text-lg font-semibold text-foreground mb-1">Unggah Bukti Transfer</h3>
                        <p class="text-sm text-muted-foreground mb-4">
                            {{ proofTarget.period }} · {{ formatRupiah(proofTarget.amount) }}
                        </p>

                        <form class="space-y-4" @submit.prevent="submitProof">
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.pdf"
                                class="w-full text-sm text-foreground file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border file:border-border file:bg-card file:text-sm file:text-foreground"
                                @input="proofForm.proof = $event.target.files[0]"
                            />
                            <p v-if="proofForm.errors.proof" role="alert" class="text-xs text-destructive">
                                {{ proofForm.errors.proof }}
                            </p>

                            <div class="flex justify-end gap-3">
                                <button type="button" class="px-4 py-2 text-sm font-medium text-foreground border border-border rounded-lg hover:bg-accent/40" @click="proofTarget = null">
                                    Batal
                                </button>
                                <button type="submit" :disabled="proofForm.processing" class="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 disabled:opacity-50">
                                    {{ proofForm.processing ? 'Mengunggah...' : 'Unggah' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Teleport>

        </div>
    </div>
</template>
