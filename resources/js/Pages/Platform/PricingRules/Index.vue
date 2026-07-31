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
import { formatRupiah, formatDate, inputClass } from '@/support/platform';

const props = defineProps({
    plans: { type: Array, required: true },
    rules: { type: Array, required: true },
    dimensions: { type: Array, required: true },
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
//
// Sebelumnya kolom ini berbunyi "Aktif / Nonaktif", yang menjawab pertanyaan
// yang tidak sedang ditanyakan siapa pun. Yang ingin diketahui saat menatap
// daftar paket adalah golongan mana ini, dan apakah ia masih ditawarkan.
const planTier = (plan) =>
    plan.base_price === 0
        ? { label: 'Gratis', tone: 'neutral' }
        : { label: 'Berbayar', tone: 'success' };

const planAvailability = (plan) =>
    plan.is_active
        ? { label: 'Ditawarkan', tone: 'success' }
        : { label: 'Tidak ditawarkan', tone: 'neutral' };

const planColumns = [
    { key: 'name', label: 'Paket' },
    { key: 'tier', label: 'Golongan' },
    { key: 'base', label: 'Tarif bulanan', align: 'right' },
    { key: 'seats', label: 'Pengguna termasuk', align: 'right' },
    { key: 'extra', label: 'Tarif pengguna tambahan', align: 'right' },
    { key: 'availability', label: 'Ketersediaan' },
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
    is_active: true,
});

const openPlan = (plan) => {
    planForm.name = plan.name;
    planForm.base_price = plan.base_price;
    planForm.included_seats = plan.included_seats;
    planForm.extra_seat_price = plan.extra_seat_price;
    planForm.is_active = plan.is_active;
    planForm.clearErrors();
    editingPlan.value = plan;
};

const submitPlan = () => {
    planForm.put(`/platform/plans/${editingPlan.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editingPlan.value = null; },
    });
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
});

const openPlanCreate = () => {
    newPlanForm.reset();
    newPlanForm.clearErrors();
    showPlanCreate.value = true;
};

const submitNewPlan = () => {
    newPlanForm.post('/platform/plans', {
        preserveScroll: true,
        onSuccess: () => {
            showPlanCreate.value = false;
            newPlanForm.reset();
        },
    });
};

// --- Aturan harga ---
const showRuleForm = ref(false);
const ruleForm = useForm({
    label: '',
    priority: 0,
    price: 0,
    effective_from: new Date().toISOString().slice(0, 10),
    conditions: [],
});

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
    ruleForm.post('/platform/pricing-rules', {
        preserveScroll: true,
        onSuccess: () => {
            showRuleForm.value = false;
            ruleForm.reset();
        },
    });
};

const deleteForm = useForm({});
const removeRule = (rule) => deleteForm.delete(`/platform/pricing-rules/${rule.id}`, { preserveScroll: true });
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
                </p>
            </div>
            <Button size="sm" @click="openPlanCreate">Tambah Paket</Button>
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
                </td>
                <td :class="cellClass">
                    <StatusBadge :label="planTier(plan).label" :tone="planTier(plan).tone" />
                </td>
                <td :class="[cellClass, 'text-right tabular-nums text-foreground']">
                    {{ plan.base_price === 0 ? 'Rp 0' : formatRupiah(plan.base_price) }}
                </td>
                <td :class="[cellClass, 'text-right tabular-nums text-foreground']">{{ plan.included_seats }}</td>
                <td :class="[cellClass, 'text-right tabular-nums text-foreground']">
                    {{ formatRupiah(plan.extra_seat_price) }}
                </td>
                <td :class="cellClass">
                    <StatusBadge
                        :label="planAvailability(plan).label"
                        :tone="planAvailability(plan).tone"
                        title="Apakah paket ini masih ditawarkan ke tenant baru"
                    />
                </td>
                <td :class="[cellClass, 'text-right']">
                    <Button size="sm" variant="soft" @click="openPlan(plan)">Ubah</Button>
                </td>
            </tr>
        </DataTable>

        <!-- ── Aturan: jalur Harga Adaptif ──────────────────────────────── -->
        <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
            <div>
                <h3 class="text-sm font-semibold text-foreground">Aturan tarif — jalur Harga Adaptif</h3>
                <p class="mt-1 text-xs text-muted-foreground leading-relaxed max-w-2xl">
                    Hanya berlaku untuk tenant yang menyetujui membuka omzet bulanannya. Tarifnya mengikuti keadaan
                    usaha mereka, bukan daftar harga tetap — karena itu jalur ini disebut adaptif, bukan bantuan.
                </p>
            </div>
            <Button size="sm" @click="showRuleForm = true">Terbitkan Aturan</Button>
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
                        title="Sudah menjadi dasar harga — tidak bisa dihapus"
                    />
                    <StatusBadge
                        v-else
                        :label="`Mulai ${formatDate(rule.effective_from)}`"
                        tone="warning"
                        title="Belum berlaku — masih bisa dibatalkan"
                    />
                </td>
                <td :class="[cellClass, 'text-right']">
                    <Button v-if="!rule.is_effective" size="sm" variant="destructiveSoft" @click="removeRule(rule)">
                        Batalkan
                    </Button>
                    <span v-else class="text-xs text-muted-foreground">terkunci</span>
                </td>
            </tr>
        </DataTable>

        <div class="mt-4 space-y-2 text-xs text-muted-foreground leading-relaxed max-w-2xl">
            <p>
                Yang menang adalah aturan dengan <span class="font-medium text-foreground">prioritas tertinggi</span>
                yang <span class="font-medium text-foreground">seluruh</span> syaratnya terpenuhi. Aturan umum sebaiknya
                berprioritas rendah, supaya aturan yang lebih khusus bisa mendahuluinya.
            </p>
            <p>
                Aturan yang sudah berlaku tidak bisa dihapus — ia adalah dasar harga periode yang sudah lewat. Untuk
                mengubah tarif, terbitkan aturan baru dengan nama yang sama dan tanggal berlaku ke depan.
            </p>
        </div>

        <!-- ── Ubah paket ───────────────────────────────────────────────── -->
        <Modal :show="editingPlan !== null" title="Ubah paket" @close="editingPlan = null">
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

                <FormField label="Tarif pengguna tambahan (Rp)" :error="planForm.errors.extra_seat_price">
                    <input v-model.number="planForm.extra_seat_price" type="number" min="0" :class="inputClass" />
                </FormField>

                <label class="flex items-start gap-2 text-sm text-foreground">
                    <input
                        v-model="planForm.is_active"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-ring"
                    />
                    <span>
                        Masih ditawarkan
                        <span class="block text-xs text-muted-foreground">
                            Mematikannya menyembunyikan paket dari tenant baru. Tenant yang sudah memakainya tidak
                            dipindahkan ke mana pun.
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

                <FormField label="Tarif pengguna tambahan (Rp)" :error="newPlanForm.errors.extra_seat_price">
                    <input v-model.number="newPlanForm.extra_seat_price" type="number" min="0" :class="inputClass" />
                </FormField>
            </form>

            <template #footer>
                <div class="flex justify-end gap-3">
                    <Button variant="secondary" @click="showPlanCreate = false">Batal</Button>
                    <Button :loading="newPlanForm.processing" @click="submitNewPlan">Buat paket</Button>
                </div>
            </template>
        </Modal>

        <!-- ── Terbitkan aturan ─────────────────────────────────────────── -->
        <Modal
            :show="showRuleForm"
            title="Terbitkan aturan tarif"
            description="Berlaku sejak tanggal yang Anda pilih, dan tidak menyentuh periode sebelumnya."
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
                        <template #footnote>Makin tinggi makin didahulukan.</template>
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
                    <Button :loading="ruleForm.processing" @click="submitRule">Terbitkan</Button>
                </div>
            </template>
        </Modal>
    </PlatformLayout>
</template>
