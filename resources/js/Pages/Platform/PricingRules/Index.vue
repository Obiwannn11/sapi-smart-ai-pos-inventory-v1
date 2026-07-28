<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

const props = defineProps({
    plans: { type: Array, required: true },
    rules: { type: Array, required: true },
    dimensions: { type: Array, required: true },
});

const formatRupiah = (value) =>
    value === null
        ? 'tanpa batas'
        : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);

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

// --- Paket ---
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

const inputClass =
    'w-full px-3 py-2 border border-border rounded-lg text-sm bg-card text-foreground focus:outline-none focus:ring-2 focus:ring-ring';
</script>

<template>
    <Head title="Aturan Harga — Platform" />

    <PlatformLayout>
        <template #header>Aturan Harga</template>

        <div class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 mb-6 max-w-3xl">
            <p class="text-sm text-foreground leading-relaxed">
                Perubahan di halaman ini <span class="font-medium">tidak mengubah tagihan yang sedang berjalan</span>.
                Tenant tetap di tarif yang sudah disepakati sampai periode berikutnya, dan aturan bracket baru hanya
                berlaku sejak tanggal yang Anda tetapkan. Setiap perubahan tercatat di jejak audit.
            </p>
        </div>

        <!-- Paket jalur normal -->
        <h2 class="text-sm font-semibold text-foreground mb-2">Paket (jalur harga normal)</h2>

        <div class="rounded-xl border border-border bg-card overflow-hidden mb-8">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-accent/50 text-left">
                        <tr class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3 text-right">Tarif Dasar</th>
                            <th class="px-4 py-3 text-right">Seat Termasuk</th>
                            <th class="px-4 py-3 text-right">Tarif Seat Tambahan</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="plan in plans" :key="plan.id" class="hover:bg-accent/30 transition-colors">
                            <td class="px-4 py-3">
                                <p class="font-medium text-foreground">{{ plan.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ plan.slug }}</p>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-foreground">{{ formatRupiah(plan.base_price) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-foreground">{{ plan.included_seats }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-foreground">{{ formatRupiah(plan.extra_seat_price) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="plan.is_active ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'"
                                >
                                    {{ plan.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button class="px-2.5 py-1.5 text-xs font-medium text-primary bg-primary/10 border border-primary/20 rounded-lg hover:bg-primary/20" @click="openPlan(plan)">
                                    Ubah
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Aturan harga bersyarat -->
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold text-foreground">Aturan harga</h2>
            <button class="px-3 py-1.5 bg-primary text-primary-foreground text-xs font-medium rounded-lg hover:bg-primary/90" @click="showRuleForm = true">
                Terbitkan Aturan
            </button>
        </div>

        <div class="rounded-xl border border-border bg-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-accent/50 text-left">
                        <tr class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <th class="px-4 py-3">Kelompok</th>
                            <th class="px-4 py-3">Syarat</th>
                            <th class="px-4 py-3 text-right">Prioritas</th>
                            <th class="px-4 py-3 text-right">Tarif</th>
                            <th class="px-4 py-3">Berlaku</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="rule in rules" :key="rule.id" class="hover:bg-accent/30 transition-colors">
                            <td class="px-4 py-3 font-medium text-foreground align-top">{{ rule.label }}</td>
                            <td class="px-4 py-3 align-top">
                                <ul v-if="rule.conditions.length" class="space-y-0.5">
                                    <li v-for="(condition, index) in rule.conditions" :key="index" class="text-foreground">
                                        {{ describeCondition(condition) }}
                                    </li>
                                </ul>
                                <span v-else class="text-muted-foreground">tanpa syarat — cocok untuk semua</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground align-top">{{ rule.priority }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-foreground align-top">{{ formatRupiah(rule.price) }}</td>
                            <td class="px-4 py-3 align-top">
                                <span class="text-muted-foreground tabular-nums">{{ rule.effective_from }}</span>
                                <span
                                    v-if="!rule.is_effective"
                                    class="ml-1.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-500/15 text-amber-700"
                                >
                                    menunggu
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right align-top">
                                <button
                                    v-if="!rule.is_effective"
                                    class="px-2.5 py-1.5 text-xs font-medium text-destructive bg-destructive/10 border border-destructive/20 rounded-lg hover:bg-destructive/20"
                                    @click="removeRule(rule)"
                                >
                                    Batalkan
                                </button>
                                <span v-else class="text-xs text-muted-foreground">terkunci</span>
                            </td>
                        </tr>

                        <tr v-if="rules.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                Belum ada aturan harga.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 space-y-2 text-xs text-muted-foreground leading-relaxed max-w-2xl">
            <p>
                Yang menang adalah aturan dengan <span class="font-medium text-foreground">prioritas tertinggi</span>
                yang <span class="font-medium text-foreground">seluruh</span> syaratnya terpenuhi. Aturan umum sebaiknya
                berprioritas rendah, supaya aturan yang lebih khusus bisa mendahuluinya.
            </p>
            <p>
                Aturan yang sudah berlaku tidak bisa dihapus — ia adalah dasar harga periode yang sudah lewat. Untuk
                mengubah tarif, terbitkan aturan baru dengan kelompok yang sama dan tanggal berlaku ke depan.
            </p>
        </div>

        <!-- Ubah paket -->
        <Teleport to="body">
            <div v-if="editingPlan" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="editingPlan = null" />
                <div class="relative w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold text-foreground mb-4">Ubah Paket</h3>

                    <form class="space-y-4" @submit.prevent="submitPlan">
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">Nama</label>
                            <input v-model="planForm.name" type="text" :class="inputClass" />
                            <p v-if="planForm.errors.name" class="mt-1 text-xs text-destructive">{{ planForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">Tarif dasar (Rp)</label>
                            <input v-model.number="planForm.base_price" type="number" min="0" :class="inputClass" />
                            <p v-if="planForm.errors.base_price" class="mt-1 text-xs text-destructive">{{ planForm.errors.base_price }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">Seat termasuk</label>
                            <input v-model.number="planForm.included_seats" type="number" min="1" :class="inputClass" />
                            <p v-if="planForm.errors.included_seats" class="mt-1 text-xs text-destructive">{{ planForm.errors.included_seats }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">Tarif seat tambahan (Rp)</label>
                            <input v-model.number="planForm.extra_seat_price" type="number" min="0" :class="inputClass" />
                            <p v-if="planForm.errors.extra_seat_price" class="mt-1 text-xs text-destructive">{{ planForm.errors.extra_seat_price }}</p>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-foreground">
                            <input v-model="planForm.is_active" type="checkbox" class="h-4 w-4 rounded border-border text-primary focus:ring-2 focus:ring-ring" />
                            Paket aktif
                        </label>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="px-4 py-2 text-sm font-medium text-foreground border border-border rounded-lg hover:bg-accent/40" @click="editingPlan = null">Batal</button>
                            <button type="submit" :disabled="planForm.processing" class="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 disabled:opacity-50">
                                {{ planForm.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Terbitkan aturan -->
        <Teleport to="body">
            <div v-if="showRuleForm" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="showRuleForm = false" />
                <div class="relative w-full max-w-2xl max-h-[85vh] overflow-y-auto rounded-xl border border-border bg-card p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold text-foreground mb-1">Terbitkan Aturan Harga</h3>
                    <p class="text-sm text-muted-foreground mb-4">
                        Berlaku sejak tanggal yang Anda pilih, dan tidak menyentuh periode sebelumnya.
                    </p>

                    <form class="space-y-4" @submit.prevent="submitRule">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-foreground mb-1">Kelompok</label>
                                <input v-model="ruleForm.label" type="text" placeholder="A" :class="inputClass" />
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Kelompok yang sama menggantikan aturan sebelumnya, bukan menambah pesaingnya.
                                </p>
                                <p v-if="ruleForm.errors.label" class="mt-1 text-xs text-destructive">{{ ruleForm.errors.label }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-foreground mb-1">Prioritas</label>
                                <input v-model.number="ruleForm.priority" type="number" min="0" max="1000" :class="inputClass" />
                                <p class="mt-1 text-xs text-muted-foreground">Makin tinggi makin didahulukan.</p>
                                <p v-if="ruleForm.errors.priority" class="mt-1 text-xs text-destructive">{{ ruleForm.errors.priority }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-foreground mb-1">Tarif (Rp)</label>
                                <input v-model.number="ruleForm.price" type="number" min="0" :class="inputClass" />
                                <p v-if="ruleForm.errors.price" class="mt-1 text-xs text-destructive">{{ ruleForm.errors.price }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-foreground mb-1">Berlaku mulai</label>
                                <input v-model="ruleForm.effective_from" type="date" :class="inputClass" />
                                <p v-if="ruleForm.errors.effective_from" class="mt-1 text-xs text-destructive">{{ ruleForm.errors.effective_from }}</p>
                            </div>
                        </div>

                        <!-- Syarat -->
                        <div class="rounded-lg border border-border p-4">
                            <div class="flex items-center justify-between mb-1">
                                <label class="text-sm font-medium text-foreground">Syarat</label>
                                <button
                                    type="button"
                                    class="px-2.5 py-1.5 text-xs font-medium text-primary bg-primary/10 border border-primary/20 rounded-lg hover:bg-primary/20"
                                    @click="addCondition"
                                >
                                    Tambah Syarat
                                </button>
                            </div>
                            <p class="text-xs text-muted-foreground mb-3">
                                Aturan berlaku hanya bila <span class="font-medium text-foreground">semua</span> syarat
                                terpenuhi. Tanpa syarat, ia cocok untuk semua tenant.
                            </p>

                            <div v-if="ruleForm.conditions.length === 0" class="py-4 text-center text-xs text-muted-foreground">
                                Belum ada syarat — aturan ini akan cocok untuk semua tenant.
                            </div>

                            <div
                                v-for="(condition, index) in ruleForm.conditions"
                                :key="index"
                                class="mb-3 last:mb-0 rounded-lg bg-accent/30 p-3"
                            >
                                <div class="flex flex-wrap items-start gap-2">
                                    <div class="flex-1 min-w-[9rem]">
                                        <select
                                            v-model="condition.dimension"
                                            :class="inputClass"
                                            @change="onDimensionChange(condition)"
                                        >
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

                                    <button
                                        type="button"
                                        class="px-2.5 py-2 text-xs font-medium text-destructive bg-destructive/10 border border-destructive/20 rounded-lg hover:bg-destructive/20"
                                        @click="removeCondition(index)"
                                    >
                                        Hapus
                                    </button>
                                </div>

                                <p
                                    v-if="dimensionByName[condition.dimension]?.requires_consent"
                                    class="mt-2 text-xs text-amber-700"
                                >
                                    Dimensi ini hanya punya nilai untuk tenant yang menyetujui pembukaan datanya. Aturan
                                    ini tidak akan berlaku bagi tenant jalur normal.
                                </p>

                                <p v-if="conditionError(index, 'dimension')" class="mt-1 text-xs text-destructive">{{ conditionError(index, 'dimension') }}</p>
                                <p v-if="conditionError(index, 'operator')" class="mt-1 text-xs text-destructive">{{ conditionError(index, 'operator') }}</p>
                                <p v-if="conditionError(index, 'value')" class="mt-1 text-xs text-destructive">{{ conditionError(index, 'value') }}</p>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="px-4 py-2 text-sm font-medium text-foreground border border-border rounded-lg hover:bg-accent/40" @click="showRuleForm = false">Batal</button>
                            <button type="submit" :disabled="ruleForm.processing" class="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 disabled:opacity-50">
                                {{ ruleForm.processing ? 'Menyimpan...' : 'Terbitkan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </PlatformLayout>
</template>
