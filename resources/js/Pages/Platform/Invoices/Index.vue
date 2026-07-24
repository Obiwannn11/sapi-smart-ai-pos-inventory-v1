<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

const props = defineProps({
    invoices: { type: Object, required: true },
    filters: { type: Object, required: true },
    tenants: { type: Array, required: true },
    statuses: { type: Array, required: true },
});

const statusLabels = {
    unpaid: 'Belum bayar',
    awaiting_verification: 'Menunggu diperiksa',
    paid: 'Lunas',
    rejected: 'Ditolak',
};

const statusClasses = {
    unpaid: 'bg-muted text-muted-foreground',
    awaiting_verification: 'bg-amber-500/15 text-amber-700',
    paid: 'bg-primary/10 text-primary',
    rejected: 'bg-destructive/10 text-destructive',
};

const formatRupiah = (value) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value ?? 0);

const filterStatus = (status) => {
    router.get('/platform/invoices', status ? { status } : {}, { preserveState: true, replace: true });
};

// --- Terbitkan tagihan ---
const showCreate = ref(false);

const createForm = useForm({
    tenant_id: '',
    period: new Date().toISOString().slice(0, 7),
    amount: '',
    due_date: '',
});

const submitCreate = () => {
    createForm.post('/platform/invoices', {
        preserveScroll: true,
        onSuccess: () => {
            showCreate.value = false;
            createForm.reset();
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
</script>

<template>
    <Head title="Pembayaran — Platform" />

    <PlatformLayout>
        <template #header>Pembayaran</template>

        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div class="flex flex-wrap gap-1.5">
                <button
                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                    :class="!filters.status ? 'bg-primary text-primary-foreground' : 'bg-card border border-border text-muted-foreground hover:bg-accent/40'"
                    @click="filterStatus('')"
                >
                    Semua
                </button>
                <button
                    v-for="status in statuses"
                    :key="status"
                    class="px-3 py-1.5 rounded-lg text-xs font-medium transition-colors"
                    :class="filters.status === status ? 'bg-primary text-primary-foreground' : 'bg-card border border-border text-muted-foreground hover:bg-accent/40'"
                    @click="filterStatus(status)"
                >
                    {{ statusLabels[status] }}
                </button>
            </div>

            <button
                class="px-4 py-2 bg-primary text-primary-foreground text-sm font-medium rounded-lg hover:bg-primary/90 transition-colors"
                @click="showCreate = true"
            >
                Terbitkan Tagihan
            </button>
        </div>

        <div class="rounded-xl border border-border bg-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-accent/50 text-left">
                        <tr class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <th class="px-4 py-3">Tenant</th>
                            <th class="px-4 py-3">Periode</th>
                            <th class="px-4 py-3 text-right">Nominal</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Jatuh Tempo</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="invoice in invoices.data" :key="invoice.id" class="hover:bg-accent/30 transition-colors">
                            <td class="px-4 py-3 font-medium text-foreground">{{ invoice.tenant?.name ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground tabular-nums">{{ invoice.period }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-foreground">{{ formatRupiah(invoice.amount) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="statusClasses[invoice.status]"
                                >
                                    {{ statusLabels[invoice.status] }}
                                </span>
                                <p v-if="invoice.rejection_reason" class="mt-1 text-xs text-muted-foreground">
                                    {{ invoice.rejection_reason }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ invoice.due_date }}</td>
                            <td class="px-4 py-3 text-right">
                                <div v-if="invoice.status !== 'paid'" class="flex items-center justify-end gap-2">
                                    <button
                                        class="px-2.5 py-1.5 text-xs font-medium text-primary bg-primary/10 border border-primary/20 rounded-lg hover:bg-primary/20 transition-colors"
                                        @click="verify(invoice)"
                                    >
                                        Terima
                                    </button>
                                    <button
                                        class="px-2.5 py-1.5 text-xs font-medium text-destructive bg-destructive/10 border border-destructive/20 rounded-lg hover:bg-destructive/20 transition-colors"
                                        @click="openReject(invoice)"
                                    >
                                        Tolak
                                    </button>
                                </div>
                                <span v-else class="text-xs text-muted-foreground">
                                    {{ invoice.verifier ? `oleh ${invoice.verifier}` : 'lunas' }}
                                </span>
                            </td>
                        </tr>

                        <tr v-if="invoices.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                Belum ada tagihan.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-4 text-xs text-muted-foreground leading-relaxed max-w-2xl">
            Menerima dan menolak bukti bayar tercatat di jejak audit sebagai kejadian sensitif. Menolak bukti tidak
            menonaktifkan akun staf tenant — hanya menahan penambahan berikutnya.
        </p>

        <!-- Terbitkan tagihan -->
        <Teleport to="body">
            <div v-if="showCreate" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="showCreate = false" />
                <div class="relative w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold text-foreground mb-4">Terbitkan Tagihan</h3>

                    <form class="space-y-4" @submit.prevent="submitCreate">
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">Tenant</label>
                            <select
                                v-model="createForm.tenant_id"
                                class="w-full px-3 py-2 border border-border rounded-lg text-sm bg-card text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            >
                                <option value="">Pilih tenant</option>
                                <option v-for="tenant in tenants" :key="tenant.id" :value="tenant.id">{{ tenant.name }}</option>
                            </select>
                            <p v-if="createForm.errors.tenant_id" class="mt-1 text-xs text-destructive">{{ createForm.errors.tenant_id }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">Periode (YYYY-MM)</label>
                            <input
                                v-model="createForm.period"
                                type="text"
                                placeholder="2026-08"
                                class="w-full px-3 py-2 border border-border rounded-lg text-sm bg-card text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                            <p v-if="createForm.errors.period" class="mt-1 text-xs text-destructive">{{ createForm.errors.period }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">Nominal (Rp)</label>
                            <input
                                v-model="createForm.amount"
                                type="number"
                                min="0"
                                class="w-full px-3 py-2 border border-border rounded-lg text-sm bg-card text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                            <p v-if="createForm.errors.amount" class="mt-1 text-xs text-destructive">{{ createForm.errors.amount }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">Jatuh tempo</label>
                            <input
                                v-model="createForm.due_date"
                                type="date"
                                class="w-full px-3 py-2 border border-border rounded-lg text-sm bg-card text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                            <p v-if="createForm.errors.due_date" class="mt-1 text-xs text-destructive">{{ createForm.errors.due_date }}</p>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" class="px-4 py-2 text-sm font-medium text-foreground border border-border rounded-lg hover:bg-accent/40" @click="showCreate = false">
                                Batal
                            </button>
                            <button type="submit" :disabled="createForm.processing" class="px-4 py-2 text-sm font-medium bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 disabled:opacity-50">
                                {{ createForm.processing ? 'Menyimpan...' : 'Terbitkan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Tolak bukti bayar -->
        <Teleport to="body">
            <div v-if="rejectTarget" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="rejectTarget = null" />
                <div class="relative w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold text-foreground mb-1">Tolak Bukti Bayar</h3>
                    <p class="text-sm text-muted-foreground mb-4">
                        Alasannya dikirim ke tenant, jadi tulis yang bisa mereka tindaklanjuti.
                    </p>

                    <form class="space-y-4" @submit.prevent="submitReject">
                        <div>
                            <textarea
                                v-model="rejectForm.reason"
                                rows="3"
                                placeholder="Mis. nominal transfer kurang Rp 15.000 dari tagihan."
                                class="w-full px-3 py-2 border border-border rounded-lg text-sm bg-card text-foreground focus:outline-none focus:ring-2 focus:ring-ring"
                            />
                            <p v-if="rejectForm.errors.reason" class="mt-1 text-xs text-destructive">{{ rejectForm.errors.reason }}</p>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button type="button" class="px-4 py-2 text-sm font-medium text-foreground border border-border rounded-lg hover:bg-accent/40" @click="rejectTarget = null">
                                Batal
                            </button>
                            <button type="submit" :disabled="rejectForm.processing" class="px-4 py-2 text-sm font-medium bg-destructive text-white rounded-lg hover:bg-destructive/90 disabled:opacity-50">
                                {{ rejectForm.processing ? 'Menyimpan...' : 'Tolak' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </PlatformLayout>
</template>
