<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import PageHeader from '@/Components/Platform/PageHeader.vue';
import DataTable from '@/Components/Platform/DataTable.vue';
import StatusBadge from '@/Components/Platform/StatusBadge.vue';
import Notice from '@/Components/Platform/Notice.vue';
import FormField from '@/Components/Platform/FormField.vue';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { formatRupiah, formatDate, inputClass } from '@/support/platform';
import { businessDaysAhead, businessToday } from '@/support/date';

const props = defineProps({
    plans: { type: Array, required: true },
    rules: { type: Array, required: true },
    dimensions: { type: Array, required: true },
    aiDailyDefault: { type: Number, required: true },
    aiBlockPrice: { type: Object, required: true },
});

// --- Katalog dimensi ---
// Seluruh daftarnya datang dari `config/pricing-dimensions.php` lewat props.
// Halaman ini sengaja tidak menyalin satu pun nama dimensi, supaya dimensi baru
// di config langsung tersedia di form tanpa menyentuh berkas ini.
const dimensionByName = computed(() =>
    Object.fromEntries(props.dimensions.map((dimension) => [dimension.name, dimension])),
);

const operatorLabels = {
    gte: '≥',
    gt: '>',
    lte: '≤',
    lt: '<',
    eq: '=',
    neq: '≠',
    in: 'salah satu dari',
};

const describeCondition = (condition) => {
    const dimension = dimensionByName.value[condition.dimension];

    if (!dimension) {
        // Aturan yang menyebut dimensi yang sudah dicabut dari katalog tidak
        // akan pernah cocok lagi. Ditampilkan apa adanya supaya kenyataan itu
        // terlihat, bukan disembunyikan di balik tanda hubung.
        return `${condition.dimension} ${condition.operator} ${condition.value} (dimensi tak dikenal)`;
    }

    const operator = operatorLabels[condition.operator] ?? condition.operator;
    let value = condition.value;

    if (dimension.unit === 'currency') {
        value = formatRupiah(Number(condition.value));
    } else if (dimension.options) {
        value = condition.value
            .split(',')
            .map((part) => dimension.options[part.trim()] ?? part.trim())
            .join(', ');
    }

    return `${dimension.label} ${operator} ${value}`;
};

// --- Golongan paket ---
// Diturunkan dari tarifnya, bukan disimpan sebagai kolom sendiri: satu-satunya
// yang membedakan paket gratis dari paket berbayar adalah harganya, dan kolom
// terpisah hanya akan menghadirkan kemungkinan keduanya berselisih — paket
// bertanda "Gratis" seharga Rp 50.000.
const planTier = (plan) =>
    plan.base_price === 0
        ? { label: 'Gratis', tone: 'neutral' }
        : { label: 'Berbayar', tone: 'success' };

// Batas AI paket. Tiga keadaan yang bunyinya harus berbeda: paket menyetel
// sendiri, paket mengikuti bawaan platform, dan paket yang sengaja tidak
// menyertakan AI. Menampilkan ketiganya sebagai satu angka akan membuat
// "10 karena paket ini" tak bisa dibedakan dari "10 karena kebetulan itu
// bawaannya hari ini" — dan bawaannya bisa berubah tanpa paketnya disentuh.
const aiLimitLabel = (plan) => {
    if (plan.ai_daily_limit === null) {
        return `${props.aiDailyDefault} — bawaan`;
    }

    return plan.ai_daily_limit === 0 ? 'Tidak termasuk' : `${plan.ai_daily_limit}`;
};

const fallbackPlan = computed(() => props.plans.find((plan) => plan.is_adaptive_fallback) ?? null);

// Tanpa penunjukan ini, tenant yang masa gratisnya habis tidak berpindah ke mana
// pun — ia tetap di paket Rp 0, tidak tertagih, lalu jatuh ke masa tenggang
// (`[BL-052]`). Kegagalannya sunyi di sisi tenant, jadi ia harus berisik di sini.
const postTrialPlan = computed(() => props.plans.find((plan) => plan.is_post_trial_target) ?? null);

const planColumns = [
    { key: 'name', label: 'Paket' },
    { key: 'tier', label: 'Golongan' },
    { key: 'base', label: 'Tarif bulanan', align: 'right' },
    { key: 'seats', label: 'Pengguna termasuk', align: 'right' },
    { key: 'ai', label: 'Analisis AI / hari', align: 'right' },
    { key: 'actions', label: 'Aksi', align: 'right' },
];

const ruleColumns = [
    { key: 'label', label: 'Nama aturan' },
    { key: 'conditions', label: 'Berlaku untuk' },
    { key: 'priority', label: 'Prioritas', align: 'right' },
    { key: 'price', label: 'Tarif bulanan', align: 'right' },
    { key: 'effective', label: 'Status' },
    { key: 'actions', label: 'Aksi', align: 'right' },
];

// --- Ubah paket ---
const editingPlan = ref(null);
const planForm = useForm({
    name: '',
    base_price: 0,
    included_seats: 1,
    extra_seat_price: 0,
    ai_daily_limit: '',
    is_adaptive_fallback: false,
    is_post_trial_target: false,
});

// Kolom kosong berarti "ikut bawaan platform" dan harus sampai ke server
// sebagai null — bukan sebagai string kosong, yang akan ditolak validasi
// integer, dan bukan sebagai 0, yang artinya justru sebaliknya: AI dimatikan.
const withNullableAiLimit = (data) => ({
    ...data,
    ai_daily_limit: data.ai_daily_limit === '' || data.ai_daily_limit === null
        ? null
        : Number(data.ai_daily_limit),
});

const openPlan = (plan) => {
    planForm.name = plan.name;
    planForm.base_price = plan.base_price;
    planForm.included_seats = plan.included_seats;
    planForm.extra_seat_price = plan.extra_seat_price;
    planForm.ai_daily_limit = plan.ai_daily_limit === null ? '' : plan.ai_daily_limit;
    planForm.is_adaptive_fallback = plan.is_adaptive_fallback;
    planForm.is_post_trial_target = plan.is_post_trial_target;
    planForm.clearErrors();
    editingPlan.value = plan;
};

const submitPlan = () => {
    planForm.transform(withNullableAiLimit).put(`/platform/plans/${editingPlan.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editingPlan.value = null; },
    });
};

// --- Harga blok kuota AI ---
// Satu angka untuk semua paket, dan satu-satunya di halaman ini yang berlaku
// juga untuk yang SUDAH dibeli. Formulirnya karena itu tidak ditaruh di modal
// seperti paket: yang perlu dibaca sebelum menekan simpan adalah peringatannya,
// dan peringatan di dalam modal hanya terbaca oleh yang sudah membukanya.
const aiBlockPriceForm = useForm({
    block_price: props.aiBlockPrice.value,
});

const submitAiBlockPrice = () => {
    aiBlockPriceForm.put('/platform/ai-block-price', { preserveScroll: true });
};

// --- Paket baru ---
// Rutenya sudah ada sejak lama tapi tak pernah punya tombol, sehingga daftar
// paket praktis terkunci pada satu baris bawaan. Selama begitu, "paket gratis"
// dan "paket berbayar" cuma istilah tanpa wujud di panel ini.
const showPlanCreate = ref(false);
const newPlanForm = useForm({
    name: '',
    slug: '',
    base_price: 0,
    included_seats: 1,
    extra_seat_price: 0,
    ai_daily_limit: '',
    is_adaptive_fallback: false,
    is_post_trial_target: false,
});

const openPlanCreate = () => {
    newPlanForm.reset();
    newPlanForm.clearErrors();
    showPlanCreate.value = true;
};

const submitNewPlan = () => {
    newPlanForm.transform(withNullableAiLimit).post('/platform/plans', {
        preserveScroll: true,
        onSuccess: () => {
            showPlanCreate.value = false;
            newPlanForm.reset();
        },
    });
};

// --- Aturan harga ---
// Satu form untuk tiga maksud yang berbeda, dan bedanya disebutkan terus terang
// di judul dialognya:
//   create — aturan baru
//   edit   — menyunting aturan yang BELUM berlaku, di tempat
//   revise — menerbitkan revisi aturan yang SUDAH berlaku, berlabel sama dengan
//            tanggal berlaku ke depan. Bukan menyunting: yang lama tetap ada
//            sebagai dasar harga periode yang sudah lewat.
const ruleMode = ref('create');
const ruleTarget = ref(null);
const showRuleForm = ref(false);

// Hari toko, bukan hari UTC ([BL-082]): `toISOString()` menggeser tanggalnya
// satu hari ke belakang sepanjang pukul 00.00–08.00 WITA.
const besok = () => businessDaysAhead(1);

const ruleForm = useForm({
    label: '',
    priority: 0,
    price: 0,
    effective_from: businessToday(),
    conditions: [],
});

const ruleDialog = computed(() => ({
    create: {
        title: 'Terbitkan aturan tarif',
        description: 'Berlaku sejak tanggal yang Anda pilih, dan tidak menyentuh periode sebelumnya.',
        submit: 'Terbitkan',
    },
    edit: {
        title: `Ubah aturan ${ruleTarget.value?.label ?? ''}`,
        description: 'Aturan ini belum berlaku, jadi masih bisa disunting di tempat tanpa meninggalkan versi lama.',
        submit: 'Simpan',
    },
    revise: {
        title: `Terbitkan revisi aturan ${ruleTarget.value?.label ?? ''}`,
        description: 'Aturan yang sudah berlaku tidak disunting. Revisi berlabel sama menggantikannya sejak tanggal berlaku, dan yang lama tetap tersimpan sebagai dasar harga periode sebelumnya.',
        submit: 'Terbitkan revisi',
    },
}[ruleMode.value]));

// Field diisikan satu per satu, bukan lewat `defaults()` + `reset()`: yang
// terakhir itu mengubah arti "reset" untuk seluruh sisa umur form, sehingga
// membuka dialog untuk satu aturan meninggalkan jejaknya pada dialog berikutnya.
const fillRuleForm = (values) => {
    Object.assign(ruleForm, values);
    ruleForm.clearErrors();
};

const openRuleCreate = () => {
    ruleMode.value = 'create';
    ruleTarget.value = null;
    fillRuleForm({
        label: '',
        priority: 0,
        price: 0,
        effective_from: besok(),
        conditions: [],
    });
    showRuleForm.value = true;
};

// Syaratnya disalin, bukan dirujuk: form yang menyunting array milik props akan
// mengubah tampilan tabel di belakang dialog sebelum apa pun tersimpan.
const openRuleEdit = (rule, mode) => {
    ruleMode.value = mode;
    ruleTarget.value = rule;
    fillRuleForm({
        label: rule.label,
        priority: rule.priority,
        price: rule.price,
        effective_from: mode === 'edit' ? rule.effective_from : besok(),
        conditions: rule.conditions.map((condition) => ({ ...condition })),
    });
    showRuleForm.value = true;
};

const addCondition = () => {
    const dimension = props.dimensions[0];

    ruleForm.conditions.push({
        dimension: dimension.name,
        operator: dimension.operators[0],
        value: dimension.options ? Object.keys(dimension.options)[0] : '',
    });
};

// Mengganti dimensi mengganti pula operator dan nilainya. Operator yang
// tertinggal dari dimensi sebelumnya bisa jadi tidak sah untuk dimensi yang
// baru — dan yang menolaknya nanti adalah server, setelah form dikirim.
const onDimensionChange = (condition) => {
    const dimension = dimensionByName.value[condition.dimension];

    condition.operator = dimension.operators[0];
    condition.value = dimension.options ? Object.keys(dimension.options)[0] : '';
};

const removeCondition = (index) => ruleForm.conditions.splice(index, 1);

const conditionError = (index, field) => ruleForm.errors[`conditions.${index}.${field}`];

const submitRule = () => {
    const onSuccess = () => {
        showRuleForm.value = false;
        ruleTarget.value = null;
    };

    if (ruleMode.value === 'edit') {
        ruleForm.put(`/platform/pricing-rules/${ruleTarget.value.id}`, { preserveScroll: true, onSuccess });

        return;
    }

    ruleForm.post('/platform/pricing-rules', { preserveScroll: true, onSuccess });
};

// --- Hentikan aturan ---
// Lewat konfirmasi, bukan langsung: menghentikan aturan yang sudah berlaku
// memindahkan tarif setiap tenant yang masuk kelompoknya, dan akibat sebesar
// itu tidak boleh berjarak satu klik tak sengaja.
const deletingRule = ref(null);
const deleteForm = useForm({});

const deleteMessage = computed(() => {
    const rule = deletingRule.value;

    if (rule === null) {
        return '';
    }

    if (!rule.is_effective) {
        return `Aturan ${rule.label} belum berlaku, jadi membatalkannya tidak mengubah tagihan siapa pun.`;
    }

    const penampung = fallbackPlan.value
        ? `paket ${fallbackPlan.value.name} (${formatRupiah(fallbackPlan.value.base_price)}/bulan)`
        : 'tidak ke mana-mana — belum ada paket penampung yang ditunjuk, dan tarif mereka akan kosong sampai ada';

    return `Aturan ${rule.label} berhenti berlaku sejak sekarang. Tenant yang masuk kelompok ini akan dinilai ulang: bila tak ada aturan lain yang cocok, tarifnya jatuh ke ${penampung}. Periode yang sedang berjalan tidak berubah, dan tagihan lama tetap menautnya.`;
});

const confirmDelete = () => {
    deleteForm.delete(`/platform/pricing-rules/${deletingRule.value.id}`, {
        preserveScroll: true,
        onFinish: () => { deletingRule.value = null; },
    });
};
</script>

<template>
    <Head title="Aturan Harga — Platform" />

    <PlatformLayout>
        <PageHeader
            title="Aturan Harga"
            description="Dua jalur harga, dua cara menetapkannya. Paket menetapkan tarif yang sama untuk semua; aturan menetapkan tarif yang mengikuti keadaan tiap tenant."
        />

        <Notice tone="warning" class="mb-6 max-w-3xl">
            Perubahan di halaman ini <span class="font-medium">tidak mengubah tagihan yang sedang berjalan</span>.
            Tenant tetap di tarif yang sudah disepakati sampai periode berikutnya, dan aturan baru hanya berlaku sejak
            tanggal yang Anda tetapkan. Setiap perubahan tercatat di jejak audit.
        </Notice>

        <!-- ── Paket: jalur Harga Tetap ─────────────────────────────────── -->
        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
            <div>
                <h3 class="text-sm font-semibold text-foreground">Paket — jalur Harga Tetap</h3>
                <p class="mt-1 text-xs text-muted-foreground leading-relaxed max-w-2xl">
                    Tarif yang sama untuk semua tenant yang memakainya, berapa pun omzetnya. Tidak ada data usaha yang
                    dibuka. Paket bertarif Rp 0 adalah paket gratis; paket bertarif di atas nol adalah paket berbayar.
                    Batas analisis AI harian ditetapkan per paket di sini.
                </p>
            </div>
            <Button size="sm" @click="openPlanCreate">
                <template #icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </template>
                Tambah Paket
            </Button>
        </div>

        <DataTable
            v-slot="{ cellClass }"
            :columns="planColumns"
            :count="plans.length"
            empty="Belum ada paket."
            class="mb-8"
        >
            <tr v-for="plan in plans" :key="plan.id" class="hover:bg-accent/30 transition-colors">
                <td :class="cellClass">
                    <p class="font-medium text-foreground">{{ plan.name }}</p>
                    <p class="text-xs text-muted-foreground font-mono">{{ plan.slug }}</p>
                    <StatusBadge
                        v-if="plan.is_adaptive_fallback"
                        class="mt-1.5"
                        label="Penampung jalur Adaptif"
                        tone="info"
                        title="Tenant jalur Harga Adaptif yang tidak cocok aturan mana pun ditagih dengan tarif paket ini"
                    />
                    <StatusBadge
                        v-if="plan.is_post_trial_target"
                        class="mt-1.5"
                        label="Tujuan setelah masa gratis"
                        tone="info"
                        title="Tenant yang masa gratisnya habis dipindahkan ke paket ini"
                    />
                </td>
                <td :class="cellClass">
                    <StatusBadge :label="planTier(plan).label" :tone="planTier(plan).tone" />
                </td>
                <td :class="[cellClass, 'text-right tabular-nums text-foreground']">
                    {{ plan.base_price === 0 ? 'Rp 0' : formatRupiah(plan.base_price) }}
                </td>
                <td :class="[cellClass, 'text-right tabular-nums text-foreground']">{{ plan.included_seats }}</td>
                <td :class="[cellClass, 'text-right tabular-nums text-foreground']">
                    <span :class="plan.ai_daily_limit === null ? 'text-muted-foreground' : ''">
                        {{ aiLimitLabel(plan) }}
                    </span>
                </td>
                <td :class="[cellClass, 'text-right']">
                    <Button size="sm" variant="secondary" :title="`Ubah paket ${plan.name}`" @click="openPlan(plan)">
                        <template #icon>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </template>
                        Ubah paket
                    </Button>
                </td>
            </tr>
        </DataTable>

        <!-- ── Blok kuota AI tambahan ───────────────────────────────────── -->
        <div class="mb-8 rounded-lg border border-border bg-card p-4">
            <h3 class="text-sm font-semibold text-foreground">Blok kuota AI tambahan</h3>
            <p class="mt-1 text-xs text-muted-foreground leading-relaxed max-w-2xl">
                Dijual seperti pengguna tambahan: komponen bulanan yang berulang, dibeli dan dilepas tenant dari
                halaman langganannya. Satu blok menaikkan plafon harian sebesar
                <span class="font-medium text-foreground">{{ aiBlockPrice.block_size }} analisis</span>, paling banyak
                {{ aiBlockPrice.max_blocks }} blok per langganan. Harganya seragam untuk semua paket — ongkos satu
                analisis tidak berbeda menurut paket pembelinya.
            </p>

            <Notice tone="warning" class="mt-3 max-w-2xl">
                Berbeda dari paket dan aturan di halaman ini, harga ini
                <span class="font-medium">tidak di-grandfather</span>: blok yang sudah dibeli tenant ikut harga baru
                mulai tagihan periode berikutnya. Tagihan yang sudah terbit tetap memegang tarif lamanya.
            </Notice>

            <form class="mt-4 flex flex-wrap items-end gap-3" @submit.prevent="submitAiBlockPrice">
                <FormField
                    label="Harga per blok / bulan"
                    class="w-56"
                    :error="aiBlockPriceForm.errors.block_price"
                >
                    <input v-model.number="aiBlockPriceForm.block_price" type="number" min="0" :class="inputClass" />
                </FormField>
                <Button type="submit" size="sm" :disabled="aiBlockPriceForm.processing">Simpan harga</Button>
                <p class="text-xs text-muted-foreground">
                    Berlaku sekarang: <span class="font-medium text-foreground">{{ formatRupiah(aiBlockPrice.value) }}</span>
                    <span v-if="!aiBlockPrice.is_custom"> — bawaan berkas config, belum pernah disetel dari sini.</span>
                </p>
            </form>
        </div>

        <!-- ── Aturan: jalur Harga Adaptif ──────────────────────────────── -->
        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
            <div>
                <h3 class="text-sm font-semibold text-foreground">Aturan tarif — jalur Harga Adaptif</h3>
                <p class="mt-1 text-xs text-muted-foreground leading-relaxed max-w-2xl">
                    Hanya berlaku untuk tenant yang menyetujui membuka omzet bulanannya. Tarifnya mengikuti keadaan
                    usaha mereka, bukan daftar harga tetap — karena itu jalur ini disebut adaptif, bukan bantuan.
                </p>
            </div>
            <Button size="sm" @click="openRuleCreate">
                <template #icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </template>
                Terbitkan Aturan
            </Button>
        </div>

        <DataTable
            v-slot="{ cellClass }"
            :columns="ruleColumns"
            :count="rules.length"
            empty="Belum ada aturan tarif."
        >
            <tr v-for="rule in rules" :key="rule.id" class="hover:bg-accent/30 transition-colors">
                <td :class="[cellClass, 'font-medium text-foreground']">{{ rule.label }}</td>
                <td :class="cellClass">
                    <ul v-if="rule.conditions.length" class="space-y-0.5">
                        <li v-for="(condition, index) in rule.conditions" :key="index" class="text-foreground">
                            {{ describeCondition(condition) }}
                        </li>
                    </ul>
                    <span v-else class="text-muted-foreground">tanpa syarat — semua tenant jalur adaptif</span>
                </td>
                <td :class="[cellClass, 'text-right tabular-nums text-muted-foreground']">{{ rule.priority }}</td>
                <td :class="[cellClass, 'text-right tabular-nums font-medium text-foreground']">
                    {{ formatRupiah(rule.price) }}
                </td>
                <td :class="cellClass">
                    <StatusBadge
                        v-if="rule.is_effective"
                        label="Berlaku"
                        tone="success"
                        title="Sudah menjadi dasar harga — perubahannya lewat revisi, bukan suntingan"
                    />
                    <StatusBadge
                        v-else
                        :label="`Mulai ${formatDate(rule.effective_from)}`"
                        tone="warning"
                        title="Belum berlaku — masih bisa disunting atau dibatalkan"
                    />
                </td>
                <td :class="[cellClass, 'text-right']">
                    <div class="flex justify-end gap-2">
                        <Button
                            size="sm"
                            variant="secondary"
                            :title="rule.is_effective
                                ? `Terbitkan revisi aturan ${rule.label} dengan tanggal berlaku ke depan`
                                : `Ubah aturan ${rule.label}`"
                            @click="openRuleEdit(rule, rule.is_effective ? 'revise' : 'edit')"
                        >
                            <template #icon>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </template>
                            {{ rule.is_effective ? 'Revisi' : 'Ubah' }}
                        </Button>
                        <Button
                            size="sm"
                            variant="destructiveSoft"
                            :title="rule.is_effective
                                ? `Hentikan aturan ${rule.label}`
                                : `Batalkan aturan ${rule.label} yang belum berlaku`"
                            @click="deletingRule = rule"
                        >
                            <template #icon>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </template>
                            {{ rule.is_effective ? 'Hentikan' : 'Batalkan' }}
                        </Button>
                    </div>
                </td>
            </tr>
        </DataTable>

        <!-- Prioritas & aturan main, ditulis di sebelah tabelnya karena di
             sinilah pertanyaannya muncul. -->
        <div class="mt-4 grid gap-4 lg:grid-cols-2 max-w-5xl">
            <div class="rounded-lg border border-border bg-card p-4">
                <h4 class="text-sm font-semibold text-foreground">Untuk apa prioritas?</h4>
                <div class="mt-2 space-y-2 text-xs text-muted-foreground leading-relaxed">
                    <p>
                        Satu tenant bisa memenuhi syarat beberapa aturan sekaligus. Prioritas menentukan
                        <span class="font-medium text-foreground">siapa yang menang</span> — angka tertinggi yang
                        dipakai, sisanya diabaikan. Ia sama sekali tidak memengaruhi tarif; ia hanya memilih aturan.
                    </p>
                    <p>
                        Karena itu <span class="font-medium text-foreground">aturan umum diberi angka rendah, aturan
                        khusus diberi angka tinggi</span>. Contoh: “omzet di bawah 2 juta → Rp 10.000” di prioritas 0,
                        dan “omzet di bawah 2 juta <span class="font-medium text-foreground">dan</span> tipe usaha
                        kuliner → Rp 8.000” di prioritas 10. Tenant kuliner kena yang kedua, tenant lain kena yang
                        pertama — tanpa perlu menuliskan “bukan kuliner” di aturan yang umum.
                    </p>
                    <p>
                        Prioritas sama dimenangkan tanggal berlaku terbaru. Itu jaring pengaman, bukan cara menyusun
                        aturan: dua aturan berprioritas sama yang saling tumpang-tindih sebaiknya diberi angka berbeda.
                    </p>
                </div>
            </div>

            <div class="rounded-lg border border-border bg-card p-4">
                <h4 class="text-sm font-semibold text-foreground">Kalau tidak ada aturan yang cocok</h4>
                <div class="mt-2 space-y-2 text-xs text-muted-foreground leading-relaxed">
                    <p v-if="fallbackPlan">
                        Tenant jalur Adaptif yang tidak cocok aturan mana pun — aturannya dihentikan, atau omzetnya di
                        atas kelompok teratas — ditagih dengan tarif paket
                        <span class="font-medium text-foreground">{{ fallbackPlan.name }}</span>
                        ({{ formatRupiah(fallbackPlan.base_price) }}/bulan). Perpindahannya berlaku pada periode
                        berikutnya; periode yang sedang berjalan sudah terkunci di harga yang disepakati.
                    </p>
                    <p v-else class="text-amber-700">
                        Belum ada paket penampung yang ditunjuk. Selama begitu, tenant jalur Adaptif yang tidak cocok
                        aturan mana pun tidak punya tarif sama sekali, dan tagihannya harus diketik manual. Tunjuk satu
                        paket lewat <span class="font-medium">Ubah paket → “Paket penampung jalur Adaptif”</span>.
                    </p>
                    <p v-if="postTrialPlan">
                        Tenant yang masa gratisnya habis dipindahkan ke paket
                        <span class="font-medium text-foreground">{{ postTrialPlan.name }}</span>
                        ({{ formatRupiah(postTrialPlan.base_price) }}/bulan), tujuh hari sebelum hari-H — bersamaan
                        dengan terbitnya tagihan berbayar pertamanya, supaya angkanya tiba sebagai pemberitahuan.
                    </p>
                    <p v-else class="text-amber-700">
                        Belum ada paket tujuan setelah masa gratis. Selama begitu, tenant yang masa gratisnya habis
                        tetap duduk di paket Rp 0: ia tidak ditagih apa pun, lalu jatuh ke masa tenggang dan
                        tertangguh tanpa pernah melihat satu tagihan. Tunjuk satu paket lewat
                        <span class="font-medium">Ubah paket → “Paket tujuan setelah masa gratis”</span>.
                    </p>
                    <p>
                        Aturan yang sudah berlaku tidak disunting di tempat — ia dasar harga periode yang sudah lewat.
                        Tombol <span class="font-medium text-foreground">Revisi</span> menerbitkan versi baru berlabel
                        sama dengan tanggal berlaku ke depan; yang lama tetap tersimpan sebagai riwayat.
                    </p>
                </div>
            </div>
        </div>

        <!-- ── Ubah paket ───────────────────────────────────────────────── -->
        <Modal
            :show="editingPlan !== null"
            :title="`Ubah paket ${editingPlan?.name ?? ''}`"
            description="Tenant yang sedang berjalan tetap di tarif lamanya sampai periode berikutnya."
            @close="editingPlan = null"
        >
            <form class="space-y-4" @submit.prevent="submitPlan">
                <FormField label="Nama" :error="planForm.errors.name">
                    <input v-model="planForm.name" type="text" :class="inputClass" />
                </FormField>

                <FormField
                    label="Tarif bulanan (Rp)"
                    hint="Isi 0 untuk menjadikannya paket gratis."
                    :error="planForm.errors.base_price"
                >
                    <input v-model.number="planForm.base_price" type="number" min="0" :class="inputClass" />
                </FormField>

                <FormField label="Pengguna termasuk" :error="planForm.errors.included_seats">
                    <input v-model.number="planForm.included_seats" type="number" min="1" :class="inputClass" />
                    <template #footnote>
                        Batas awal tiap tenant di paket ini. Angkanya naik sendiri saat mereka membayar penambahan.
                    </template>
                </FormField>

                <FormField
                    label="Analisis AI per hari"
                    :hint="`Kosongkan untuk mengikuti bawaan platform (${aiDailyDefault}/hari). Isi 0 bila paket ini tidak menyertakan AI.`"
                    :error="planForm.errors.ai_daily_limit"
                >
                    <input
                        v-model="planForm.ai_daily_limit"
                        type="number"
                        min="0"
                        max="1000"
                        :placeholder="`${aiDailyDefault} (bawaan)`"
                        :class="inputClass"
                    />
                    <template #footnote>
                        Hanya berlaku saat tenant memakai kunci AI bersama milik platform. Tenant yang mengisi kunci
                        API-nya sendiri membayar pemakaiannya sendiri dan tidak dijatah.
                    </template>
                </FormField>

                <FormField
                    label="Tarif pengguna tambahan (Rp)"
                    hint="Dipakai saat tenant membayar penambahan pengguna di luar jatah paket."
                    :error="planForm.errors.extra_seat_price"
                >
                    <input v-model.number="planForm.extra_seat_price" type="number" min="0" :class="inputClass" />
                </FormField>

                <label class="flex items-start gap-2 text-sm text-foreground">
                    <input
                        v-model="planForm.is_adaptive_fallback"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-ring"
                    />
                    <span>
                        Paket penampung jalur Adaptif
                        <span class="block text-xs text-muted-foreground">
                            Tenant jalur Harga Adaptif yang tidak cocok aturan tarif mana pun ditagih dengan tarif
                            paket ini. Hanya satu paket yang bisa memegang peran ini — menandai paket lain akan
                            melepaskannya dari yang sekarang.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-2 text-sm text-foreground">
                    <input
                        v-model="planForm.is_post_trial_target"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-ring"
                    />
                    <span>
                        Paket tujuan setelah masa gratis
                        <span class="block text-xs text-muted-foreground">
                            Tenant yang masa gratisnya habis dipindahkan ke paket ini, tujuh hari sebelum hari-H,
                            bersamaan dengan terbitnya tagihan berbayar pertamanya. Hanya satu paket yang bisa
                            memegang peran ini. Jangan tunjuk paket Rp 0 — itu mengembalikan tenant ke tempat yang
                            sama dan perpindahannya berhenti tanpa terlihat.
                        </span>
                    </span>
                </label>
            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="editingPlan = null">Batal</Button>
                    <Button :loading="planForm.processing" @click="submitPlan">Simpan</Button>
                </div>
            </template>
        </Modal>

        <!-- ── Paket baru ───────────────────────────────────────────────── -->
        <Modal
            :show="showPlanCreate"
            title="Tambah paket"
            description="Paket baru langsung ditawarkan ke tenant. Tenant yang sudah berjalan tidak dipindahkan."
            @close="showPlanCreate = false"
        >
            <form class="space-y-4" @submit.prevent="submitNewPlan">
                <FormField label="Nama" hint="Yang dibaca tenant, mis. “Premium”." :error="newPlanForm.errors.name">
                    <input v-model="newPlanForm.name" type="text" placeholder="Premium" :class="inputClass" />
                </FormField>

                <FormField
                    label="Slug"
                    hint="Pengenal tetap yang dirujuk kode. Tidak bisa diubah setelah dibuat."
                    :error="newPlanForm.errors.slug"
                >
                    <input v-model="newPlanForm.slug" type="text" placeholder="premium" :class="inputClass" />
                </FormField>

                <FormField
                    label="Tarif bulanan (Rp)"
                    hint="Isi 0 untuk paket gratis."
                    :error="newPlanForm.errors.base_price"
                >
                    <input v-model.number="newPlanForm.base_price" type="number" min="0" :class="inputClass" />
                </FormField>

                <FormField label="Pengguna termasuk" :error="newPlanForm.errors.included_seats">
                    <input v-model.number="newPlanForm.included_seats" type="number" min="1" :class="inputClass" />
                </FormField>

                <FormField
                    label="Analisis AI per hari"
                    :hint="`Kosongkan untuk mengikuti bawaan platform (${aiDailyDefault}/hari). Isi 0 bila paket ini tidak menyertakan AI.`"
                    :error="newPlanForm.errors.ai_daily_limit"
                >
                    <input
                        v-model="newPlanForm.ai_daily_limit"
                        type="number"
                        min="0"
                        max="1000"
                        :placeholder="`${aiDailyDefault} (bawaan)`"
                        :class="inputClass"
                    />
                </FormField>

                <FormField
                    label="Tarif pengguna tambahan (Rp)"
                    hint="Dipakai saat tenant membayar penambahan pengguna di luar jatah paket."
                    :error="newPlanForm.errors.extra_seat_price"
                >
                    <input v-model.number="newPlanForm.extra_seat_price" type="number" min="0" :class="inputClass" />
                </FormField>

                <label class="flex items-start gap-2 text-sm text-foreground">
                    <input
                        v-model="newPlanForm.is_adaptive_fallback"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-ring"
                    />
                    <span>
                        Paket penampung jalur Adaptif
                        <span class="block text-xs text-muted-foreground">
                            Menampung tenant jalur Adaptif yang tidak cocok aturan tarif mana pun.
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-2 text-sm text-foreground">
                    <input
                        v-model="newPlanForm.is_post_trial_target"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-ring"
                    />
                    <span>
                        Paket tujuan setelah masa gratis
                        <span class="block text-xs text-muted-foreground">
                            Menampung tenant yang masa gratisnya habis. Jangan tunjuk paket Rp 0.
                        </span>
                    </span>
                </label>
            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="showPlanCreate = false">Batal</Button>
                    <Button :loading="newPlanForm.processing" @click="submitNewPlan">Buat paket</Button>
                </div>
            </template>
        </Modal>

        <!-- ── Terbitkan / ubah aturan ──────────────────────────────────── -->
        <Modal
            :show="showRuleForm"
            :title="ruleDialog.title"
            :description="ruleDialog.description"
            max-width="max-w-2xl"
            @close="showRuleForm = false"
        >
            <form class="space-y-4" @submit.prevent="submitRule">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <FormField label="Nama aturan" :error="ruleForm.errors.label">
                        <input v-model="ruleForm.label" type="text" placeholder="Warung kecil" :class="inputClass" />
                        <template #footnote>
                            Nama yang sama menggantikan aturan sebelumnya, bukan menambah pesaingnya. Nama yang
                            menjelaskan siapa yang dikenainya lebih berguna daripada satu huruf.
                        </template>
                    </FormField>

                    <FormField label="Prioritas" :error="ruleForm.errors.priority">
                        <input v-model.number="ruleForm.priority" type="number" min="0" max="1000" :class="inputClass" />
                        <template #footnote>
                            Dipakai bila satu tenant cocok dengan beberapa aturan: yang tertinggi menang. Aturan khusus
                            diberi angka lebih tinggi daripada aturan umum.
                        </template>
                    </FormField>

                    <FormField label="Tarif bulanan (Rp)" :error="ruleForm.errors.price">
                        <input v-model.number="ruleForm.price" type="number" min="0" :class="inputClass" />
                    </FormField>

                    <FormField label="Berlaku mulai" :error="ruleForm.errors.effective_from">
                        <input v-model="ruleForm.effective_from" type="date" :class="inputClass" />
                    </FormField>
                </div>

                <!-- Syarat -->
                <div class="rounded-lg border border-border p-4">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm font-medium text-foreground">Syarat</span>
                        <Button size="sm" variant="soft" @click="addCondition">Tambah Syarat</Button>
                    </div>
                    <p class="text-xs text-muted-foreground mb-3 leading-relaxed">
                        Aturan berlaku hanya bila <span class="font-medium text-foreground">semua</span> syarat
                        terpenuhi. Tanpa syarat, ia cocok untuk semua tenant jalur adaptif.
                    </p>

                    <div v-if="ruleForm.conditions.length === 0" class="py-4 text-center text-xs text-muted-foreground">
                        Belum ada syarat — aturan ini akan cocok untuk semua tenant jalur adaptif.
                    </div>

                    <div
                        v-for="(condition, index) in ruleForm.conditions"
                        :key="index"
                        class="mb-3 last:mb-0 rounded-lg bg-accent/30 p-3"
                    >
                        <div class="flex flex-wrap items-start gap-2">
                            <div class="flex-1 min-w-[9rem]">
                                <select v-model="condition.dimension" :class="inputClass" @change="onDimensionChange(condition)">
                                    <option v-for="dimension in dimensions" :key="dimension.name" :value="dimension.name">
                                        {{ dimension.label }}
                                    </option>
                                </select>
                            </div>

                            <div class="w-28">
                                <select v-model="condition.operator" :class="inputClass">
                                    <option
                                        v-for="operator in dimensionByName[condition.dimension]?.operators ?? []"
                                        :key="operator"
                                        :value="operator"
                                    >
                                        {{ operatorLabels[operator] ?? operator }}
                                    </option>
                                </select>
                            </div>

                            <div class="flex-1 min-w-[9rem]">
                                <select
                                    v-if="dimensionByName[condition.dimension]?.options && condition.operator !== 'in'"
                                    v-model="condition.value"
                                    :class="inputClass"
                                >
                                    <option
                                        v-for="(optionLabel, optionValue) in dimensionByName[condition.dimension].options"
                                        :key="optionValue"
                                        :value="optionValue"
                                    >
                                        {{ optionLabel }}
                                    </option>
                                </select>
                                <input
                                    v-else
                                    v-model="condition.value"
                                    type="text"
                                    :placeholder="condition.operator === 'in' ? 'kuliner, retail' : 'nilai'"
                                    :class="inputClass"
                                />
                            </div>

                            <Button size="sm" variant="destructiveSoft" @click="removeCondition(index)">Hapus</Button>
                        </div>

                        <p
                            v-if="dimensionByName[condition.dimension]?.requires_consent"
                            class="mt-2 text-xs text-amber-700 leading-relaxed"
                        >
                            Dimensi ini hanya punya nilai untuk tenant yang menyetujui pembukaan datanya. Aturan ini
                            tidak akan berlaku bagi tenant jalur Harga Tetap.
                        </p>

                        <p v-if="conditionError(index, 'dimension')" class="mt-1 text-xs text-destructive">{{ conditionError(index, 'dimension') }}</p>
                        <p v-if="conditionError(index, 'operator')" class="mt-1 text-xs text-destructive">{{ conditionError(index, 'operator') }}</p>
                        <p v-if="conditionError(index, 'value')" class="mt-1 text-xs text-destructive">{{ conditionError(index, 'value') }}</p>
                    </div>
                </div>
            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="showRuleForm = false">Batal</Button>
                    <Button :loading="ruleForm.processing" @click="submitRule">{{ ruleDialog.submit }}</Button>
                </div>
            </template>
        </Modal>

        <!-- ── Konfirmasi penghentian aturan ────────────────────────────── -->
        <ConfirmDialog
            :show="deletingRule !== null"
            :title="deletingRule?.is_effective ? `Hentikan aturan ${deletingRule.label}?` : `Batalkan aturan ${deletingRule?.label}?`"
            :message="deleteMessage"
            :confirm-text="deletingRule?.is_effective ? 'Hentikan aturan' : 'Batalkan aturan'"
            :variant="deletingRule?.is_effective ? 'danger' : 'warning'"
            @confirm="confirmDelete"
            @cancel="deletingRule = null"
        />
    </PlatformLayout>
</template>
