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
import { formatDate, inputClass } from '@/support/platform';
import { businessToday } from '@/support/date';

const props = defineProps({
    policies: { type: Array, required: true },
    effective: { type: Object, required: true },
    configDefault: { type: Number, required: true },
    usageToday: { type: Object, required: true },
});

const MODES = {
    baseline: {
        label: 'Kuota bawaan',
        tone: 'neutral',
        // Kalimat ini yang membedakan kedua mode di kepala orang yang mengisinya.
        // Tanpa ia, "kuota bawaan 20" dan "promo 20" terlihat seperti hal yang sama.
        hint: 'Menggantikan angka bawaan platform. Hanya berlaku bagi tenant yang paketnya tidak menetapkan batas sendiri.',
        unit: 'analisis/hari',
    },
    bonus: {
        label: 'Promo',
        tone: 'info',
        hint: 'Ditambahkan DI ATAS batas yang sudah berlaku — termasuk untuk tenant yang paketnya sudah menetapkan batas sendiri.',
        unit: 'tambahan/hari',
    },
};

const columns = [
    { key: 'label', label: 'Kebijakan' },
    { key: 'mode', label: 'Jenis' },
    { key: 'limit', label: 'Jumlah', align: 'right' },
    { key: 'period', label: 'Berlaku' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: 'Aksi', align: 'right' },
];

// Angka yang benar-benar berlaku hari ini datang dari server, bukan dihitung
// ulang di sini — lihat alasannya di `AiQuotaController::index()`.
const baselineNow = computed(() => props.effective.baseline ?? props.configDefault);
const baselineFromConfig = computed(() => props.effective.baseline === null);

const policyStatus = (policy) => {
    if (policy.is_effective) {
        return { label: 'Berlaku', tone: 'success' };
    }

    return policy.has_ended
        ? { label: 'Sudah berakhir', tone: 'neutral' }
        : { label: 'Belum berlaku', tone: 'warning' };
};

const periodLabel = (policy) =>
    policy.effective_until
        ? `${formatDate(policy.effective_from)} – ${formatDate(policy.effective_until)}`
        : `${formatDate(policy.effective_from)} – tanpa batas akhir`;

// --- Form kebijakan ---
const showForm = ref(false);
const editing = ref(null);
// Hari toko, bukan hari UTC ([BL-082]).
const today = () => businessToday();

const form = useForm({
    label: '',
    mode: 'baseline',
    daily_limit: 5,
    effective_from: today(),
    effective_until: '',
});

const modeMeta = computed(() => MODES[form.mode] ?? MODES.baseline);

// Diisikan field per field, bukan lewat `defaults()` + `reset()`: yang terakhir
// itu mengubah arti "reset" untuk sisa umur form, sehingga membuka dialog untuk
// satu kebijakan meninggalkan jejaknya pada dialog berikutnya.
const fill = (values) => {
    Object.assign(form, values);
    form.clearErrors();
};

const openCreate = (mode) => {
    editing.value = null;
    fill({
        label: '',
        mode,
        daily_limit: mode === 'bonus' ? 5 : baselineNow.value,
        effective_from: today(),
        effective_until: '',
    });
    showForm.value = true;
};

const openEdit = (policy) => {
    editing.value = policy;
    fill({
        label: policy.label,
        mode: policy.mode,
        daily_limit: policy.daily_limit,
        effective_from: policy.effective_from,
        effective_until: policy.effective_until ?? '',
    });
    showForm.value = true;
};

// Kolom tanggal akhir yang dikosongkan berarti "tanpa batas akhir" dan harus
// sampai ke server sebagai null — string kosong akan ditolak validasi `date`.
const withNullableEnd = (data) => ({
    ...data,
    effective_until: data.effective_until === '' ? null : data.effective_until,
    daily_limit: Number(data.daily_limit),
});

const submit = () => {
    const onSuccess = () => {
        showForm.value = false;
        editing.value = null;
    };

    if (editing.value) {
        form.transform(withNullableEnd).put(`/platform/ai-quota/policies/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess,
        });

        return;
    }

    form.transform(withNullableEnd).post('/platform/ai-quota/policies', { preserveScroll: true, onSuccess });
};

// --- Hentikan kebijakan ---
const deleting = ref(null);
const deleteForm = useForm({});

const confirmDelete = () => {
    deleteForm.delete(`/platform/ai-quota/policies/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => { deleting.value = null; },
    });
};

// --- Reset pemakaian hari ini ---
// Berdiri sendiri, jauh dari form kebijakan, dan dengan konfirmasinya sendiri
// (`[BL-047]`(d)). Yang satu mengubah jatah, yang satu membelanjakannya ulang.
const showReset = ref(false);
const resetForm = useForm({});

const confirmReset = () => {
    resetForm.post('/platform/ai-quota/reset', {
        preserveScroll: true,
        onFinish: () => { showReset.value = false; },
    });
};
</script>

<template>
    <Head title="Kuota AI — Platform" />

    <PlatformLayout>
        <PageHeader
            title="Kuota AI"
            description="Jatah analisis AI harian bagi tenant yang memakai kunci API bersama milik aplikasi. Tenant yang mengisi kunci API-nya sendiri tidak dijatah sama sekali dan tidak terpengaruh halaman ini."
        >
            <Button size="sm" variant="secondary" @click="openCreate('bonus')">
                <template #icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </template>
                Buat Promo
            </Button>
            <Button size="sm" @click="openCreate('baseline')">
                <template #icon>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </template>
                Ubah Kuota Bawaan
            </Button>
        </PageHeader>

        <Notice tone="warning" class="mb-6 max-w-3xl">
            Menaikkan kuota di sini <span class="font-medium">menaikkan tagihan kunci API bersama</span> yang Anda
            tanggung sendiri, bukan tagihan tenant. Setiap perubahan tercatat di jejak audit.
        </Notice>

        <!-- ── Yang berlaku hari ini ────────────────────────────────────── -->
        <div class="grid gap-4 sm:grid-cols-3 mb-8">
            <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Kuota bawaan</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">
                    {{ baselineNow }}<span class="ml-1 text-sm font-normal text-muted-foreground">/hari</span>
                </p>
                <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                    <template v-if="baselineFromConfig">
                        Bawaan berkas config — belum ada kebijakan yang menggantikannya.
                    </template>
                    <template v-else>
                        Dari kebijakan <span class="font-medium text-foreground">{{ effective.baseline_label }}</span>.
                        Tanpa itu, {{ configDefault }}/hari.
                    </template>
                </p>
            </div>

            <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Promo berjalan</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">
                    <template v-if="effective.bonus > 0">
                        +{{ effective.bonus }}<span class="ml-1 text-sm font-normal text-muted-foreground">/hari</span>
                    </template>
                    <span v-else class="text-muted-foreground">—</span>
                </p>
                <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                    <template v-if="effective.bonus > 0">
                        <span class="font-medium text-foreground">{{ effective.bonus_label }}</span> — berlaku untuk
                        semua tenant yang paketnya menyertakan AI.
                    </template>
                    <template v-else>Tidak ada promo yang sedang berjalan.</template>
                </p>
            </div>

            <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wider text-muted-foreground">Pemakaian hari ini</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-foreground">
                    {{ usageToday.analyses }}<span class="ml-1 text-sm font-normal text-muted-foreground">analisis</span>
                </p>
                <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                    Oleh {{ usageToday.tenants }} tenant, kunci bersama saja. Rincian per tenant sengaja tidak
                    ditampilkan di panel ini.
                </p>
            </div>
        </div>

        <!-- ── Kebijakan ────────────────────────────────────────────────── -->
        <div class="mb-3">
            <h3 class="text-sm font-semibold text-foreground">Kebijakan</h3>
            <p class="mt-1 text-xs text-muted-foreground leading-relaxed max-w-2xl">
                Urutan yang menentukan jatah seorang tenant: batas paketnya, lalu kuota bawaan yang berlaku, lalu
                bawaan berkas config — dan promo yang sedang berjalan menambah di atas hasilnya. Saat dua kebijakan
                sejenis tumpang tindih, yang <span class="font-medium">paling baru mulai berlaku</span> yang dipakai.
            </p>
        </div>

        <DataTable
            v-slot="{ cellClass }"
            :columns="columns"
            :count="policies.length"
            empty="Belum ada kebijakan — yang berlaku adalah bawaan berkas config."
        >
            <tr v-for="policy in policies" :key="policy.id" class="hover:bg-accent/30 transition-colors">
                <td :class="[cellClass, 'font-medium text-foreground']">{{ policy.label }}</td>
                <td :class="cellClass">
                    <StatusBadge :label="MODES[policy.mode].label" :tone="MODES[policy.mode].tone" />
                </td>
                <td :class="[cellClass, 'text-right tabular-nums font-medium text-foreground']">
                    {{ policy.mode === 'bonus' ? `+${policy.daily_limit}` : policy.daily_limit }}
                    <span class="ml-1 text-xs font-normal text-muted-foreground">/hari</span>
                </td>
                <td :class="[cellClass, 'text-foreground whitespace-nowrap']">{{ periodLabel(policy) }}</td>
                <td :class="cellClass">
                    <StatusBadge :label="policyStatus(policy).label" :tone="policyStatus(policy).tone" />
                </td>
                <td :class="[cellClass, 'text-right']">
                    <div class="flex items-center justify-end gap-2">
                        <Button size="sm" variant="secondary" :title="`Ubah ${policy.label}`" @click="openEdit(policy)">
                            Ubah
                        </Button>
                        <Button size="sm" variant="destructive" :title="`Hentikan ${policy.label}`" @click="deleting = policy">
                            Hentikan
                        </Button>
                    </div>
                </td>
            </tr>
        </DataTable>

        <!-- ── Reset pemakaian ──────────────────────────────────────────── -->
        <div class="mt-8 rounded-lg border border-border bg-card p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 max-w-2xl">
                    <h3 class="text-sm font-semibold text-foreground">Kembalikan kuota hari ini</h3>
                    <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                        Menolkan hitungan pemakaian hari ini untuk seluruh tenant, sehingga jatah mereka utuh kembali
                        sampai tengah malam. Tidak mengubah angka kuota mana pun, dan
                        <span class="font-medium text-foreground">tidak bisa dibatalkan</span>.
                    </p>
                </div>
                <Button
                    variant="destructive"
                    size="sm"
                    :disabled="usageToday.tenants === 0"
                    :title="usageToday.tenants === 0 ? 'Belum ada pemakaian hari ini' : undefined"
                    @click="showReset = true"
                >
                    Reset Semua
                </Button>
            </div>
        </div>

        <!-- ── Dialog kebijakan ─────────────────────────────────────────── -->
        <Modal
            :show="showForm"
            :title="editing ? `Ubah ${editing.label}` : (form.mode === 'bonus' ? 'Buat promo' : 'Ubah kuota bawaan')"
            :description="modeMeta.hint"
            max-width="max-w-lg"
            @close="showForm = false"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <FormField label="Nama kebijakan" :error="form.errors.label" hint="Muncul di jejak audit dan di layar tenant saat promonya berjalan.">
                    <input v-model="form.label" type="text" :class="inputClass" maxlength="60" placeholder="mis. Promo Agustus" />
                </FormField>

                <FormField label="Jenis" :error="form.errors.mode">
                    <select v-model="form.mode" :class="inputClass">
                        <option v-for="(meta, value) in MODES" :key="value" :value="value">{{ meta.label }}</option>
                    </select>
                    <template #footnote>{{ modeMeta.hint }}</template>
                </FormField>

                <FormField :label="`Jumlah (${modeMeta.unit})`" :error="form.errors.daily_limit">
                    <input v-model="form.daily_limit" type="number" min="0" max="1000" :class="inputClass" />
                    <template #footnote>
                        <template v-if="form.mode === 'baseline'">
                            Nol berarti tenant tanpa batas paket sendiri tidak mendapat analisis AI sama sekali.
                        </template>
                        <template v-else>
                            Tidak berlaku untuk paket yang batas AI-nya nol — paket yang sengaja tidak menjual AI tetap
                            tidak menjualnya selama promo.
                        </template>
                    </template>
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Berlaku sejak" :error="form.errors.effective_from">
                        <input v-model="form.effective_from" type="date" :class="inputClass" />
                    </FormField>

                    <FormField label="Berlaku sampai" :error="form.errors.effective_until">
                        <input v-model="form.effective_until" type="date" :class="inputClass" />
                        <template #footnote>Kosongkan untuk tanpa batas akhir.</template>
                    </FormField>
                </div>

                <p v-if="form.mode === 'bonus' && form.effective_until === ''" class="text-xs text-amber-700">
                    Promo tanpa tanggal akhir akan berjalan terus sampai Anda menghentikannya sendiri.
                </p>

                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="secondary" @click="showForm = false">Batal</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ editing ? 'Simpan' : 'Terbitkan' }}
                    </Button>
                </div>
            </form>
        </Modal>

        <ConfirmDialog
            :show="deleting !== null"
            title="Hentikan kebijakan"
            :message="deleting ? `Kebijakan ${deleting.label} berhenti berlaku seketika. Jatah kembali mengikuti lapis di bawahnya.` : ''"
            confirm-text="Hentikan"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />

        <ConfirmDialog
            :show="showReset"
            title="Kembalikan kuota hari ini"
            :message="`Hitungan pemakaian hari ini untuk ${usageToday.tenants} tenant akan dinolkan. Tindakan ini tidak bisa dibatalkan.`"
            confirm-text="Reset Semua"
            @confirm="confirmReset"
            @cancel="showReset = false"
        />
    </PlatformLayout>
</template>
