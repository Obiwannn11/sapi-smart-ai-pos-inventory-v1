<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import PageHeader from '@/Components/Platform/PageHeader.vue';
import Panel from '@/Components/Platform/Panel.vue';
import DataTable from '@/Components/Platform/DataTable.vue';
import StatCard from '@/Components/Platform/StatCard.vue';
import StatusBadge from '@/Components/Platform/StatusBadge.vue';
import Notice from '@/Components/Platform/Notice.vue';
import FormField from '@/Components/Platform/FormField.vue';
import TabNav from '@/Components/Platform/TabNav.vue';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import { businessMonth } from '@/support/date';
import {
    formatRupiah,
    formatDate,
    formatPeriod,
    inputClass,
    TENANT_STATUS,
    INVOICE_STATUS,
    INVOICE_KIND,
    PRICING_TRACK,
} from '@/support/platform';

const props = defineProps({
    tenant: { type: Object, required: true },
    subscription: { type: Object, default: null },
    plans: { type: Array, default: null },
    invoices: { type: Object, default: null },
    bracket: { type: String, default: null },
    revenue: { type: Object, default: null },
    can: { type: Object, required: true },
});

const status = computed(() => TENANT_STATUS[props.tenant.status] ?? { label: props.tenant.status, tone: 'neutral' });
const track = computed(() => PRICING_TRACK[props.subscription?.pricing_track] ?? PRICING_TRACK.normal);
const isAdaptive = computed(() => props.subscription?.pricing_track === 'subsidized');

const invoiceRows = computed(() => props.invoices?.data ?? props.invoices ?? []);

// Ke daftar mana pembacanya boleh dikembalikan. Halaman ini kini milik modul
// tenant, tapi pemegang modul tagihan saja tetap bisa sampai ke sini dari
// daftar langganan — dan mengirimnya ke halaman yang ia tak berhak buka adalah
// bentuk kecil dari kekeliruan yang membuat halaman ini pindah.
const backLink = computed(() =>
    props.can.tenants
        ? { href: '/platform/tenants', label: 'Kembali ke daftar tenant' }
        : { href: '/platform/subscriptions', label: 'Kembali ke daftar langganan' },
);

// --- Tab ---
// Yang modulnya tidak dipegang pembacanya tidak dirender sama sekali, sejalan
// dengan payload-nya: data yang tidak boleh dilihat memang tidak terkirim, jadi
// tab-nya pun tidak ada untuk ditekan.
const tabs = computed(() => [
    { key: 'ikhtisar', label: 'Ikhtisar' },
    ...(props.can.subscriptions && props.subscription ? [{ key: 'langganan', label: 'Langganan' }] : []),
    ...(props.can.payments ? [{ key: 'tagihan', label: 'Tagihan', badge: invoiceRows.value.length }] : []),
    { key: 'kapabilitas', label: 'Kapabilitas' },
    ...(props.can.revenue && isAdaptive.value ? [{ key: 'omzet', label: 'Omzet' }] : []),
]);

// Datang lewat rute omzet berarti angkanya sudah sengaja dibuka — mendarat di
// tab lain akan membuat tindakan yang baru saja tercatat di jejak audit tidak
// menghasilkan apa-apa di layar.
const initialTab = () => {
    if (props.revenue !== null) {
        return 'omzet';
    }

    const wanted = new URLSearchParams(window.location.search).get('tab');

    return tabs.value.some((tab) => tab.key === wanted) ? wanted : 'ikhtisar';
};

const activeTab = ref(initialTab());

// Tab yang aktif dititipkan ke query string, bukan disimpan di komponen saja.
// Setiap tindakan tagihan berakhir dengan redirect kembali ke alamat ini; tanpa
// jejak di URL, memverifikasi satu bukti bayar akan melempar pembacanya keluar
// dari tab yang sedang ia kerjakan.
const selectTab = (key) => {
    activeTab.value = key;

    const url = new URL(window.location.href);
    url.searchParams.set('tab', key);
    window.history.replaceState(window.history.state, '', url);
};

// --- Batas pengguna ---
// Angka `seats` berbeda-beda antar toko karena ia BERGERAK: berangkat dari
// jatah paket, lalu naik tiap kali tenant membayar tagihan penambahan
// pengguna. Ditampilkan sebagai penjumlahan supaya tidak terbaca sebagai angka
// yang ditetapkan sembarangan.
const purchasedSeats = computed(() => {
    const included = props.subscription?.plan_included_seats ?? 0;

    return Math.max(0, (props.subscription?.seats ?? 0) - included);
});

const showSeatForm = ref(false);

const seatForm = useForm({ seats: 1, reason: '' });

const openSeatForm = () => {
    seatForm.seats = props.subscription.seats;
    seatForm.reason = '';
    seatForm.clearErrors();
    showSeatForm.value = true;
};

const submitSeats = () => {
    seatForm.put(`/platform/subscriptions/${props.subscription.id}/seats`, {
        preserveScroll: true,
        onSuccess: () => { showSeatForm.value = false; },
    });
};

// --- Perpindahan paket ---
// Batas AI paket dikirim `null` bila paketnya tidak menyetelnya sendiri. Dibaca
// apa adanya, bukan diganti angka bawaan: "10 karena paket ini" tidak boleh
// terbaca sama dengan "10 karena kebetulan itu bawaan hari ini".
const aiLimitLabel = (limit) => {
    if (limit === null || limit === undefined) {
        return 'ikut bawaan platform';
    }

    return limit === 0 ? 'tidak termasuk' : `${limit}/hari`;
};

const showPlanForm = ref(false);

const planForm = useForm({ plan_id: null, reason: '' });

// Seat tambahan yang sudah dibayar ikut pindah, jadi batas barunya bisa dihitung
// di layar sebelum tombolnya ditekan — aturan yang sama dengan yang ditegakkan
// `SubscriptionService::changePlan()`.
const targetPlan = computed(() => (props.plans ?? []).find((plan) => plan.id === planForm.plan_id) ?? null);

const seatsAfterPlanChange = computed(() =>
    targetPlan.value === null ? null : targetPlan.value.included_seats + purchasedSeats.value,
);

const openPlanForm = () => {
    planForm.plan_id = props.subscription?.plan_id ?? null;
    planForm.reason = '';
    planForm.clearErrors();
    showPlanForm.value = true;
};

const submitPlan = () => {
    planForm.put(`/platform/subscriptions/${props.subscription.id}/plan`, {
        preserveScroll: true,
        onSuccess: () => { showPlanForm.value = false; },
    });
};

// --- Terbitkan tagihan ---
const showInvoiceForm = ref(false);

const invoiceForm = useForm({
    tenant_id: props.tenant.id,
    period: businessMonth(),
    amount: '',
    due_date: '',
    amount_reason: '',
});

const suggestion = ref(null);
const suggestionState = ref('idle');

// Cerminan `$mengikutiAturan` di `Platform\InvoiceController::store()`, dengan
// ambang yang sama (0,01). Ia TIDAK menggantikan penjaga di server — ia hanya
// membuat syaratnya terlihat sebelum tombol ditekan. Formulir yang baru
// memberi tahu syaratnya lewat penolakan adalah formulir yang menyuruh orang
// menebak.
const followsRule = computed(() => {
    if (!suggestion.value?.matched) {
        return false;
    }

    const typed = Number.parseFloat(invoiceForm.amount);

    return Number.isFinite(typed) && Math.abs(typed - suggestion.value.amount) < 0.01;
});

// Saat usulannya belum/gagal diambil, jawabannya "belum diketahui", bukan
// "tidak wajib". Kolomnya tetap ditampilkan sebagai wajib supaya orangnya tidak
// mengirim lalu ditolak — server tetap pemutus akhirnya.
const reasonRequired = computed(() => suggestionState.value !== 'loading' && !followsRule.value);

// Diisikan, bukan dipaksakan: nominalnya tetap bisa diketik ulang. Aturan yang
// menyarankan lebih berguna daripada aturan yang memutuskan — harga yang turun
// atau naik sendiri secara keliru adalah uang yang sudah terlanjur ditagihkan.
const fetchSuggestion = async () => {
    suggestion.value = null;

    if (!/^\d{4}-\d{2}$/.test(invoiceForm.period)) {
        suggestionState.value = 'idle';

        return;
    }

    suggestionState.value = 'loading';

    try {
        const params = new URLSearchParams({
            tenant_id: props.tenant.id,
            period: invoiceForm.period,
        });
        const response = await fetch(`/platform/invoices/suggestion?${params}`, {
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            throw new Error('gagal');
        }

        const data = await response.json();
        suggestion.value = data;
        // Tiga keadaan, bukan dua: tarif dari aturan, tarif dari paket
        // penampung karena tak ada aturan yang cocok, dan tak ada tarif sama
        // sekali. Yang kedua dulu menyatu dengan yang ketiga, sehingga tenant
        // yang bracketnya dihapus terlihat seperti tenant tanpa harga.
        suggestionState.value = { rule: 'matched', plan: 'plan' }[data.source] ?? 'unmatched';

        // Hanya mengisi kolom yang masih kosong. Menimpa angka yang sudah
        // diketik akan membuang keputusan yang baru saja diambil orangnya.
        if (data.amount !== null && invoiceForm.amount === '') {
            invoiceForm.amount = data.amount;
        }
    } catch {
        suggestionState.value = 'error';
    }
};

const openInvoiceForm = () => {
    invoiceForm.reset();
    invoiceForm.tenant_id = props.tenant.id;
    invoiceForm.clearErrors();
    showInvoiceForm.value = true;
    fetchSuggestion();
};

const applySuggestion = () => {
    if (suggestion.value?.amount !== null && suggestion.value?.amount !== undefined) {
        invoiceForm.amount = suggestion.value.amount;
    }
};

const submitInvoice = () => {
    invoiceForm.post('/platform/invoices', {
        preserveScroll: true,
        onSuccess: () => {
            showInvoiceForm.value = false;
            invoiceForm.reset();
        },
    });
};

// --- Verifikasi & penolakan ---
const verifyForm = useForm({});
const rejectTarget = ref(null);
const rejectForm = useForm({ reason: '' });

const verify = (invoice) => {
    verifyForm.post(`/platform/invoices/${invoice.id}/verify`, { preserveScroll: true });
};

const openReject = (invoice) => {
    rejectForm.reset();
    rejectForm.clearErrors();
    rejectTarget.value = invoice;
};

const submitReject = () => {
    rejectForm.post(`/platform/invoices/${rejectTarget.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => { rejectTarget.value = null; },
    });
};

// --- Kunci pajak ([BL-065] butir 4) ---
// Yang dibuka adalah KUNCINYA, bukan setelannya: tombol di bawah tidak pernah
// mengirim tarif atau mode. Sesudah dibuka, yang memilih tetap pemilik toko.
const showTaxLockForm = ref(false);
const taxLockForm = useForm({ reason: '' });
const taxLockCloseForm = useForm({});

const tax = computed(() => props.tenant.tax ?? null);
const taxLockOpen = computed(() => Boolean(tax.value?.opened_until));

const openTaxLockForm = () => {
    taxLockForm.reason = '';
    taxLockForm.clearErrors();
    showTaxLockForm.value = true;
};

const submitTaxLock = () => {
    taxLockForm.post(`/platform/tenants/${props.tenant.id}/tax-lock`, {
        preserveScroll: true,
        onSuccess: () => { showTaxLockForm.value = false; },
    });
};

const closeTaxLock = () => {
    taxLockCloseForm.delete(`/platform/tenants/${props.tenant.id}/tax-lock`, { preserveScroll: true });
};

const invoiceColumns = [
    { key: 'period', label: 'Periode' },
    { key: 'kind', label: 'Jenis' },
    { key: 'amount', label: 'Nominal', align: 'right' },
    { key: 'status', label: 'Status' },
    { key: 'due', label: 'Jatuh tempo' },
    { key: 'actions', label: 'Aksi', align: 'right' },
];

const revenueColumns = [
    { key: 'period', label: 'Periode' },
    { key: 'revenue', label: 'Omzet', align: 'right' },
    { key: 'count', label: 'Transaksi', align: 'right' },
    { key: 'bracket', label: 'Kelompok' },
    { key: 'computed', label: 'Dihitung' },
];
</script>

<template>
    <Head :title="`${tenant.name} — Platform`" />

    <PlatformLayout>
        <template #header>{{ tenant.name }}</template>

        <Link
            :href="backLink.href"
            class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors mb-4"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            {{ backLink.label }}
        </Link>

        <PageHeader :title="tenant.name">
            <template #description>
                <span class="font-mono text-xs">{{ tenant.slug }}</span> · terdaftar {{ formatDate(tenant.registered_at) }}
            </template>

            <StatusBadge :label="status.label" :tone="status.tone" />
            <StatusBadge v-if="!tenant.is_verified" label="Email belum diverifikasi" tone="neutral" />
            <StatusBadge v-if="tenant.flagged_at" label="Perlu ditinjau" tone="warning" />
        </PageHeader>

        <Notice v-if="tenant.flag_reason" tone="warning" class="mb-6">
            Ditandai pada {{ formatDate(tenant.flagged_at) }}: {{ tenant.flag_reason }}
        </Notice>

        <!-- ── Ringkasan ────────────────────────────────────────────────────
             Di LUAR tab, bukan di dalam salah satunya: empat angka ini yang
             menjawab "apa yang sedang terjadi dengan akun ini" sebelum orangnya
             tahu tab mana yang perlu dibuka. -->
        <div v-if="subscription" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
            <StatCard label="Paket" :value="subscription.plan_name ?? '—'" :hint="track.label" />
            <StatCard
                label="Tarif berjalan"
                :value="formatRupiah(subscription.price_locked)"
                :hint="subscription.price_locked === null ? 'Belum ada tagihan yang dilunasi.' : 'Terkunci sejak pembayaran terakhir.'"
            />
            <StatCard
                label="Pengguna aktif"
                :value="`${subscription.seats_used} / ${subscription.seats}`"
                :hint="`Puncak periode ini: ${subscription.seat_high_water}`"
            />
            <StatCard
                label="Periode berakhir"
                :value="formatDate(subscription.current_period_end)"
                :hint="subscription.trial_ends_at ? `Masa coba sampai ${formatDate(subscription.trial_ends_at)}` : ''"
            />
        </div>

        <TabNav :tabs="tabs" :model-value="activeTab" class="mb-6" @update:model-value="selectTab" />

        <!-- ── Ikhtisar ─────────────────────────────────────────────────── -->
        <div v-show="activeTab === 'ikhtisar'" class="grid gap-6 xl:grid-cols-2">
            <Panel title="Akun" description="Siapa kliennya dan atas dasar apa tarifnya dihitung.">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Pemilik</dt>
                        <dd class="text-right text-foreground">
                            <template v-if="tenant.owner">
                                {{ tenant.owner.name }}
                                <p class="text-xs text-muted-foreground">{{ tenant.owner.email }}</p>
                            </template>
                            <span v-else class="text-muted-foreground">—</span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Jenis usaha</dt>
                        <dd class="text-right text-foreground">
                            {{ tenant.business_type_label ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Jumlah akun</dt>
                        <dd class="text-right tabular-nums text-foreground">{{ tenant.user_count }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Terdaftar</dt>
                        <dd class="text-right text-foreground">{{ formatDate(tenant.registered_at) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Alamat toko</dt>
                        <dd class="text-right font-mono text-xs text-foreground">{{ tenant.slug }}</dd>
                    </div>
                </dl>

                <template #footer>
                    <!-- Dulu jenis usaha bisa diganti dari panel ini lewat satu
                         dropdown yang menyimpan seketika. Kuasanya dicabut: ia
                         keterangan usaha milik kliennya, bukan milik kita, dan
                         mengubahnya diam-diam menggeser dasar tarif orang. -->
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Jenis usaha diatur pemilik toko sendiri dari Pengaturan mereka, dan hanya bisa dibaca di sini.
                        Ia dasar penetapan harga — tapi yang tahu jawabannya adalah pemilik usahanya.
                    </p>
                </template>
            </Panel>

            <Panel title="Keadaan akun" description="Apa yang boleh dan tidak boleh dikerjakan tenant ini sekarang.">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Status langganan</dt>
                        <dd class="text-right"><StatusBadge :label="status.label" :tone="status.tone" /></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Verifikasi email pemilik</dt>
                        <dd class="text-right">
                            <StatusBadge
                                :label="tenant.is_verified ? 'Sudah' : 'Belum'"
                                :tone="tenant.is_verified ? 'success' : 'neutral'"
                            />
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Ditandai perlu ditinjau</dt>
                        <dd class="text-right">
                            <StatusBadge
                                :label="tenant.flagged_at ? formatDate(tenant.flagged_at) : 'Tidak'"
                                :tone="tenant.flagged_at ? 'warning' : 'neutral'"
                            />
                        </dd>
                    </div>
                </dl>

                <template #footer>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Halaman ini tidak menampilkan data operasional klien — transaksi, produk, stok, maupun laporan.
                        Yang terlihat di sini hanya keterangan akun dan komersialnya.
                    </p>
                </template>
            </Panel>
        </div>

        <!-- ── Langganan ────────────────────────────────────────────────── -->
        <div v-if="can.subscriptions && subscription" v-show="activeTab === 'langganan'" class="grid gap-6 xl:grid-cols-2">
            <Panel title="Jalur harga" description="Atas dasar apa tarif akun ini ditetapkan.">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Golongan</dt>
                        <dd class="text-right">
                            <StatusBadge :label="track.label" :tone="track.tone" />
                            <p class="mt-1 text-xs text-muted-foreground max-w-xs">{{ track.description }}</p>
                        </dd>
                    </div>
                    <div v-if="isAdaptive && bracket" class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Kelompok harga</dt>
                        <dd class="text-right"><StatusBadge :label="bracket" tone="success" /></dd>
                    </div>
                    <div v-if="subscription.track_changed_at" class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Berpindah jalur</dt>
                        <dd class="text-right text-foreground">{{ formatDate(subscription.track_changed_at) }}</dd>
                    </div>
                    <div v-if="subscription.track_reverts_at" class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Kembali ke Harga Tetap</dt>
                        <dd class="text-right text-foreground">{{ formatDate(subscription.track_reverts_at) }}</dd>
                    </div>

                    <div class="pt-3 border-t border-border space-y-3">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Tarif berjalan</dt>
                            <dd class="text-right tabular-nums font-medium text-foreground">
                                {{ formatRupiah(subscription.price_locked) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Periode berjalan</dt>
                            <dd class="text-right text-foreground">
                                {{ formatDate(subscription.current_period_start) }} –
                                {{ formatDate(subscription.current_period_end) }}
                            </dd>
                        </div>
                    </div>
                </dl>
            </Panel>

            <Panel title="Paket" description="Yang menentukan tarif dasar, jatah pengguna, dan kuota AI akun ini.">
                <template #actions>
                    <Button size="sm" variant="soft" @click="openPlanForm">Pindahkan paket</Button>
                </template>

                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Paket sekarang</dt>
                        <dd class="text-right font-medium text-foreground">{{ subscription.plan_name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Tarif dasar paket</dt>
                        <dd class="text-right tabular-nums text-foreground">
                            {{ formatRupiah(subscription.plan_base_price) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Jatah pengguna</dt>
                        <dd class="text-right tabular-nums text-foreground">
                            {{ subscription.plan_included_seats ?? 0 }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-muted-foreground">Analisis AI</dt>
                        <dd class="text-right text-foreground">{{ aiLimitLabel(subscription.plan_ai_daily_limit) }}</dd>
                    </div>
                </dl>

                <template #footer>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Tarif dasar paket hanya berlaku bagi akun jalur Harga Tetap. Untuk jalur Harga Adaptif yang
                        tarifnya keluar dari aturan harga, paket tetap menentukan jatah pengguna dan kuota AI-nya.
                    </p>
                </template>
            </Panel>

            <Panel title="Batas pengguna" description="Berapa akun yang boleh aktif, dan dari mana angkanya datang.">
                <template #actions>
                    <Button size="sm" variant="soft" @click="openSeatForm">Atur batas pengguna</Button>
                </template>

                <dl class="text-sm">
                    <dt class="text-muted-foreground mb-1">Batas pengguna</dt>
                    <dd class="text-foreground">
                        <span class="text-lg font-semibold tabular-nums">{{ subscription.seats }}</span>
                        <span class="text-xs text-muted-foreground">
                            = {{ subscription.plan_included_seats ?? 0 }} dari paket
                            <template v-if="purchasedSeats > 0">
                                + {{ purchasedSeats }} tambahan yang sudah dibayar
                            </template>
                        </span>
                        <p class="mt-1.5 text-xs text-muted-foreground leading-relaxed">
                            Karena itulah tiap toko bisa berbeda: batasnya berangkat dari jatah paket, lalu naik
                            sendiri tiap kali tenant membayar tagihan penambahan pengguna
                            (<span class="tabular-nums">{{ formatRupiah(subscription.plan_extra_seat_price) }}</span>
                            per pengguna).
                        </p>
                    </dd>
                </dl>

                <template v-if="subscription.provisional_blocked" #footer>
                    <p class="text-xs text-amber-700 leading-relaxed">
                        Penambahan pengguna langsung-aktif dicabut untuk akun ini karena bukti bayarnya pernah ditolak.
                        Mereka tetap boleh menambah pengguna, hanya saja berlakunya menunggu pemeriksaan.
                    </p>
                </template>
            </Panel>
        </div>

        <!-- ── Riwayat tagihan ──────────────────────────────────────────── -->
        <div v-if="can.payments" v-show="activeTab === 'tagihan'">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="text-sm font-semibold text-foreground">Riwayat tagihan</h3>
                <Button size="sm" @click="openInvoiceForm">Terbitkan tagihan</Button>
            </div>

            <DataTable
                v-slot="{ cellClass }"
                :columns="invoiceColumns"
                :count="invoiceRows.length"
                empty="Belum ada tagihan untuk akun ini."
            >
                <tr v-for="invoice in invoiceRows" :key="invoice.id" class="hover:bg-accent/30 transition-colors">
                    <td :class="[cellClass, 'text-foreground whitespace-nowrap']">{{ formatPeriod(invoice.period) }}</td>

                    <td :class="[cellClass, 'text-muted-foreground']">
                        {{ INVOICE_KIND[invoice.kind] ?? invoice.kind }}
                        <p v-if="invoice.kind === 'upgrade' && invoice.grants_seats" class="text-xs">
                            {{ invoice.previous_seats }} → {{ invoice.grants_seats }} pengguna
                        </p>
                    </td>

                    <td :class="[cellClass, 'text-right tabular-nums text-foreground']">
                        {{ formatRupiah(invoice.amount) }}
                        <!--
                            Hanya muncul untuk tagihan yang nominalnya menyimpang
                            dari aturan — kolomnya null untuk sisanya.
                        -->
                        <p v-if="invoice.amount_reason" class="mt-1 text-xs font-normal text-muted-foreground text-right normal-case">
                            {{ invoice.amount_reason }}
                        </p>
                    </td>

                    <td :class="cellClass">
                        <StatusBadge
                            :label="INVOICE_STATUS[invoice.status]?.label ?? invoice.status"
                            :tone="INVOICE_STATUS[invoice.status]?.tone ?? 'neutral'"
                        />
                        <p v-if="invoice.rejection_reason" class="mt-1 text-xs text-muted-foreground max-w-xs">
                            {{ invoice.rejection_reason }}
                        </p>
                    </td>

                    <td :class="[cellClass, 'text-muted-foreground whitespace-nowrap']">
                        {{ formatDate(invoice.due_date) }}
                    </td>

                    <td :class="[cellClass, 'text-right']">
                        <div class="flex items-center justify-end gap-2">
                            <a
                                v-if="invoice.has_proof"
                                :href="`/platform/invoices/${invoice.id}/proof`"
                                target="_blank"
                                rel="noopener"
                                class="text-xs font-medium text-primary hover:underline"
                            >
                                Bukti
                            </a>
                            <template v-if="invoice.status !== 'paid'">
                                <Button size="sm" variant="soft" @click="verify(invoice)">Terima</Button>
                                <Button size="sm" variant="destructiveSoft" @click="openReject(invoice)">Tolak</Button>
                            </template>
                            <span v-else class="text-xs text-muted-foreground whitespace-nowrap">
                                {{ invoice.verifier ? `oleh ${invoice.verifier}` : 'lunas' }}
                            </span>
                        </div>
                    </td>
                </tr>
            </DataTable>

            <Notice class="mt-3 max-w-2xl">
                Menerima dan menolak bukti bayar tercatat di jejak audit sebagai kejadian sensitif. Menolak bukti tidak
                menonaktifkan akun staf tenant — hanya menahan penambahan berikutnya.
            </Notice>
        </div>

        <!-- ── Kapabilitas kasir ────────────────────────────────────────── -->
        <div v-show="activeTab === 'kapabilitas'">
            <Panel
                title="Kapabilitas sistem kasir"
                description="Fitur yang menyala untuk toko ini — inilah yang membedakan tampilan kasirnya dari tenant lain."
                flush
            >
                <ul class="divide-y divide-border">
                    <li
                        v-for="capability in tenant.capabilities"
                        :key="capability.key"
                        class="flex items-start justify-between gap-4 px-5 py-4"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-foreground">{{ capability.label }}</p>
                            <p class="mt-0.5 text-xs text-muted-foreground leading-relaxed max-w-xl">
                                {{ capability.description }}
                            </p>
                        </div>

                        <StatusBadge
                            :label="capability.enabled ? 'Aktif' : 'Tidak aktif'"
                            :tone="capability.enabled ? 'success' : 'neutral'"
                        />
                    </li>
                </ul>

                <template #footer>
                    <!-- Membaca saja, dan itu disengaja — alasan yang sama
                         seperti pencabutan kuasa mengubah tipe usaha. -->
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Hanya bisa dibaca dari sini. Menyalakan dan mematikannya adalah keputusan pemilik toko, diatur
                        dari Pengaturan mereka sendiri — cara sebuah usaha bekerja bukan milik penyedia layanannya.
                    </p>
                </template>
            </Panel>

            <!-- ── Pajak & kuncinya ([BL-065] butir 4) ──────────────────── -->
            <Panel
                v-if="tax"
                class="mt-6"
                title="Pajak penjualan"
                description="Setelan pajak toko ini, dan satu-satunya hal yang bisa disentuh dari sini: kuncinya."
            >
                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs text-muted-foreground">Status</dt>
                        <dd class="mt-1">
                            <StatusBadge
                                :label="tax.enabled ? 'Memungut' : 'Tidak memungut'"
                                :tone="tax.enabled ? 'success' : 'neutral'"
                            />
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Jenis</dt>
                        <dd class="mt-1 text-sm font-medium text-foreground">{{ tax.label ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Tarif</dt>
                        <dd class="mt-1 text-sm font-medium text-foreground">{{ tax.rate }}%</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Mode</dt>
                        <dd class="mt-1 text-sm font-medium text-foreground">
                            {{ tax.mode === 'inclusive' ? 'Termasuk harga' : 'Ditambahkan' }}
                        </dd>
                    </div>
                </dl>

                <div class="mt-5 border-t border-border pt-5">
                    <Notice v-if="taxLockOpen" tone="warning">
                        Kunci sedang terbuka sampai <span class="font-medium">{{ formatDate(tax.opened_until) }}</span>.
                        Selama itu pemilik toko bisa mengubah sakelar atau mode pajaknya satu kali; jendelanya habis
                        begitu dipakai.
                    </Notice>

                    <Notice v-else-if="tax.locked">
                        Sakelar dan mode pajak terkunci karena toko ini sudah memungut pajak pada penjualan yang
                        tercatat. Mengubahnya membuat omzet sebelum dan sesudahnya tidak sebanding — itu sebabnya
                        pembukaannya lewat sini, tercatat, dan bukan self-service.
                    </Notice>

                    <Notice v-else>
                        Belum ada penjualan berpajak, jadi belum ada yang terkunci. Pemilik toko masih bisa mengubah
                        sendiri sakelar dan modenya dari Pengaturan.
                    </Notice>

                    <div v-if="can.tenants && tax.locked" class="mt-4 flex gap-3">
                        <Button v-if="!taxLockOpen" @click="openTaxLockForm">Buka kunci</Button>
                        <Button
                            v-else
                            variant="secondary"
                            :loading="taxLockCloseForm.processing"
                            @click="closeTaxLock"
                        >
                            Tutup kembali
                        </Button>
                    </div>
                </div>

                <template #footer>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Membuka kunci TIDAK mengubah pajak siapa pun — ia mengembalikan kemampuan pemilik toko memilih,
                        di layarnya sendiri. Setiap pembukaan dan penutupan tercatat di jejak audit sebagai kejadian
                        sensitif, lengkap dengan alasannya.
                    </p>
                </template>
            </Panel>
        </div>

        <!-- ── Omzet bulanan ────────────────────────────────────────────── -->
        <div v-if="can.revenue && isAdaptive" v-show="activeTab === 'omzet'">
            <Panel
                title="Omzet bulanan"
                description="Angka yang mendasari tarif Harga Adaptif akun ini."
                :flush="revenue !== null"
            >
                <template v-if="revenue === null" #actions>
                    <!-- Tautan, bukan tombol yang membuka panel di tempat: alamat
                         tersendiri berarti pembukaannya bisa dicatat sekali, tepat
                         saat ia benar-benar terjadi. Menengok halaman ini tanpa
                         menekannya tidak menghasilkan catatan apa pun. -->
                    <Button
                        size="sm"
                        variant="soft"
                        :href="`/platform/tenants/${tenant.id}/revenue`"
                    >
                        Buka rincian omzet
                    </Button>
                </template>

                <template v-if="revenue === null">
                    <p class="text-sm text-muted-foreground leading-relaxed">
                        Angka rupiahnya belum terbuka. Membukanya
                        <span class="font-medium text-foreground">tercatat di jejak audit</span>, dan klien berhak meminta
                        salinannya — itulah yang membuat janji di dokumen persetujuan mereka bisa dibuktikan.
                    </p>
                </template>

                <template v-else>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-accent/40 border-b border-border">
                                    <th
                                        v-for="column in revenueColumns"
                                        :key="column.key"
                                        scope="col"
                                        :class="[
                                            'px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap',
                                            column.align === 'right' ? 'text-right' : 'text-left',
                                        ]"
                                    >
                                        {{ column.label }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr v-for="metric in revenue.metrics" :key="metric.period" class="hover:bg-accent/30 transition-colors">
                                    <td class="px-5 py-4 text-sm text-foreground whitespace-nowrap">{{ formatPeriod(metric.period) }}</td>
                                    <td class="px-5 py-4 text-sm text-right tabular-nums font-medium text-foreground">
                                        {{ formatRupiah(metric.revenue) }}
                                    </td>
                                    <td class="px-5 py-4 text-sm text-right tabular-nums text-muted-foreground">
                                        {{ metric.transaction_count }}
                                    </td>
                                    <td class="px-5 py-4 text-sm">
                                        <StatusBadge :label="metric.bracket ?? '—'" tone="success" />
                                    </td>
                                    <td class="px-5 py-4 text-sm text-muted-foreground whitespace-nowrap">
                                        {{ formatDate(metric.computed_at) }}
                                    </td>
                                </tr>
                                <tr v-if="revenue.metrics.length === 0">
                                    <td colspan="5" class="px-5 py-12 text-center text-sm text-muted-foreground">
                                        Belum ada periode yang terhitung.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <template v-if="revenue !== null" #footer>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Dihitung otomatis dari transaksi berstatus selesai, disimpan paling lama
                        {{ revenue.retention_months }} bulan. Tidak ada laba, margin, maupun harga pokok di sini — angka itu
                        memang tidak pernah dihitung. Kunjungan Anda ke halaman ini tercatat di jejak audit.
                    </p>
                </template>
            </Panel>
        </div>

        <!-- ── Atur batas pengguna ──────────────────────────────────────── -->
        <Modal
            :show="showSeatForm"
            title="Atur batas pengguna"
            description="Berlaku seketika. Tarif bulanan tidak ikut berubah — ubah itu lewat tagihan."
            @close="showSeatForm = false"
        >
            <form class="space-y-4" @submit.prevent="submitSeats">
                <FormField
                    label="Batas pengguna aktif"
                    :hint="`Sekarang ${subscription?.seats_used} pengguna aktif dari batas ${subscription?.seats}.`"
                    :error="seatForm.errors.seats"
                >
                    <input v-model.number="seatForm.seats" type="number" min="1" max="1000" :class="inputClass" />

                    <template #footnote>
                        Menurunkannya di bawah jumlah pengguna aktif diizinkan dan
                        <span class="font-medium text-foreground">tidak menonaktifkan siapa pun</span> — yang tertutup
                        hanyalah penambahan berikutnya.
                    </template>
                </FormField>

                <FormField
                    label="Alasan"
                    hint="Dicatat di jejak audit bersama angka lama dan barunya."
                    :error="seatForm.errors.reason"
                >
                    <textarea
                        v-model="seatForm.reason"
                        rows="3"
                        placeholder="Mis. koreksi setelah tenant salah pilih jumlah saat menambah kasir."
                        :class="inputClass"
                    />
                </FormField>
            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="showSeatForm = false">Batal</Button>
                    <Button :loading="seatForm.processing" @click="submitSeats">Simpan</Button>
                </div>
            </template>
        </Modal>

        <!-- ── Pindahkan paket ──────────────────────────────────────────── -->
        <Modal
            :show="showPlanForm"
            title="Pindahkan paket"
            description="Berlaku pada tagihan berikutnya. Tarif periode yang sedang berjalan tidak berubah."
            @close="showPlanForm = false"
        >
            <form class="space-y-4" @submit.prevent="submitPlan">
                <FormField label="Paket tujuan" :error="planForm.errors.plan_id">
                    <select v-model="planForm.plan_id" :class="inputClass">
                        <option v-for="plan in plans ?? []" :key="plan.id" :value="plan.id">
                            {{ plan.name }} — {{ formatRupiah(plan.base_price) }},
                            {{ plan.included_seats }} pengguna, AI {{ aiLimitLabel(plan.ai_daily_limit) }}
                        </option>
                    </select>

                    <template #footnote>
                        <span v-if="targetPlan && targetPlan.id !== subscription?.plan_id">
                            Batas penggunanya menjadi
                            <span class="font-medium text-foreground tabular-nums">{{ seatsAfterPlanChange }}</span>
                            <template v-if="purchasedSeats > 0">
                                = {{ targetPlan.included_seats }} dari paket baru + {{ purchasedSeats }} tambahan yang
                                sudah dibayar — seat yang sudah dibeli tidak hangus karena pindah paket.
                            </template>
                            <template v-else>, mengikuti jatah paket barunya.</template>
                        </span>
                        <span v-else>Paket yang sedang berjalan. Pilih paket lain untuk memindahkannya.</span>
                    </template>
                </FormField>

                <FormField
                    label="Alasan"
                    hint="Dicatat di jejak audit bersama paket, batas pengguna, dan kuota AI lama-barunya."
                    :error="planForm.errors.reason"
                >
                    <textarea
                        v-model="planForm.reason"
                        rows="3"
                        placeholder="Mis. omzetnya melewati batas jalur Harga Adaptif, dipindahkan ke Premium."
                        :class="inputClass"
                    />
                </FormField>
            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="showPlanForm = false">Batal</Button>
                    <Button :loading="planForm.processing" @click="submitPlan">Pindahkan</Button>
                </div>
            </template>
        </Modal>

        <!-- ── Terbitkan tagihan ────────────────────────────────────────── -->
        <Modal
            :show="showInvoiceForm"
            title="Terbitkan tagihan"
            :description="`Untuk ${tenant.name}. Satu tenant hanya bisa ditagih sekali per periode.`"
            @close="showInvoiceForm = false"
        >
            <form class="space-y-4" @submit.prevent="submitInvoice">
                <FormField label="Periode (YYYY-MM)" :error="invoiceForm.errors.period">
                    <input
                        v-model="invoiceForm.period"
                        type="text"
                        placeholder="2026-08"
                        :class="inputClass"
                        @blur="fetchSuggestion"
                    />
                </FormField>

                <FormField label="Nominal (Rp)" :error="invoiceForm.errors.amount">
                    <input v-model="invoiceForm.amount" type="number" min="0" :class="inputClass" />

                    <template #footnote>
                        <span v-if="suggestionState === 'loading'">Menghitung usulan tarif...</span>
                        <span v-else-if="suggestionState === 'matched'">
                            Aturan <span class="font-medium text-foreground">{{ suggestion.label }}</span> menyarankan
                            {{ formatRupiah(suggestion.amount) }}.
                            <button type="button" class="font-medium text-primary hover:underline" @click="applySuggestion">
                                Pakai angka ini
                            </button>
                        </span>
                        <span v-else-if="suggestionState === 'plan'" class="text-amber-700">
                            Tidak ada aturan harga yang cocok pada periode tersebut. Yang berlaku tarif paket
                            <span class="font-medium text-foreground">{{ suggestion.label }}</span>,
                            {{ formatRupiah(suggestion.amount) }}.
                            <button type="button" class="font-medium text-primary hover:underline" @click="applySuggestion">
                                Pakai angka ini
                            </button>
                        </span>
                        <span v-else-if="suggestionState === 'unmatched'" class="text-amber-700">
                            Tidak ada aturan harga yang cocok untuk tenant ini pada periode tersebut, dan belum ada paket
                            penampung yang ditunjuk — nominalnya perlu ditetapkan sendiri.
                        </span>
                        <span v-else-if="suggestionState === 'error'" class="text-amber-700">
                            Usulan tarif gagal diambil. Nominalnya tetap bisa diisi manual.
                        </span>
                    </template>
                </FormField>

                <FormField
                    :label="reasonRequired ? 'Alasan nominal khusus *' : 'Alasan nominal khusus'"
                    :error="invoiceForm.errors.amount_reason"
                >
                    <textarea
                        v-model="invoiceForm.amount_reason"
                        rows="2"
                        placeholder="Mis. potongan 20% tiga bulan pertama, kesepakatan 5 Agustus."
                        :class="inputClass"
                    />

                    <template #footnote>
                        <span v-if="reasonRequired" class="text-amber-700">
                            Nominalnya berbeda dari tarif aturan, jadi alasannya wajib.
                            <span class="font-medium text-foreground">Tenant ikut membacanya</span> di halaman
                            langganan mereka — tulis yang memang boleh mereka baca.
                        </span>
                        <span v-else>
                            Nominalnya persis mengikuti tarif aturan, jadi alasannya tidak diperlukan dan tidak akan
                            disimpan.
                        </span>
                    </template>
                </FormField>

                <FormField label="Jatuh tempo" :error="invoiceForm.errors.due_date">
                    <input v-model="invoiceForm.due_date" type="date" :class="inputClass" />
                </FormField>
            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="showInvoiceForm = false">Batal</Button>
                    <Button :loading="invoiceForm.processing" @click="submitInvoice">Terbitkan</Button>
                </div>
            </template>
        </Modal>

        <!-- ── Tolak bukti bayar ────────────────────────────────────────── -->
        <Modal
            :show="rejectTarget !== null"
            title="Tolak bukti bayar"
            description="Alasannya dikirim ke tenant, jadi tulis yang bisa mereka tindaklanjuti."
            @close="rejectTarget = null"
        >
            <form @submit.prevent="submitReject">
                <FormField label="Alasan" :error="rejectForm.errors.reason">
                    <textarea
                        v-model="rejectForm.reason"
                        rows="3"
                        placeholder="Mis. nominal transfer kurang Rp 15.000 dari tagihan."
                        :class="inputClass"
                    />
                </FormField>
            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="rejectTarget = null">Batal</Button>
                    <Button variant="destructive" :loading="rejectForm.processing" @click="submitReject">Tolak</Button>
                </div>
            </template>
        </Modal>

        <!-- Buka kunci pajak ([BL-065] butir 4) -->
        <Modal
            :show="showTaxLockForm"
            title="Buka kunci pajak"
            description="Berlaku tujuh hari, dan habis begitu pemilik toko memakainya sekali."
            @close="showTaxLockForm = false"
        >
            <form @submit.prevent="submitTaxLock">
                <FormField
                    label="Alasan"
                    hint="Dicatat di jejak audit bersama keadaan pajak sebelum dibuka."
                    :error="taxLockForm.errors.reason"
                >
                    <textarea
                        v-model="taxLockForm.reason"
                        rows="3"
                        placeholder="Mis. tenant salah memilih mode inclusive di hari pertama dan baru menjual tiga transaksi uji."
                        :class="inputClass"
                    />

                    <template #footnote>
                        Yang dibuka adalah kuncinya, bukan setelannya —
                        <span class="font-medium text-foreground">pemilik toko yang memilih</span>, dari Pengaturan
                        mereka sendiri.
                    </template>
                </FormField>
            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="showTaxLockForm = false">Batal</Button>
                    <Button :loading="taxLockForm.processing" @click="submitTaxLock">Buka kunci</Button>
                </div>
            </template>
        </Modal>
    </PlatformLayout>
</template>
