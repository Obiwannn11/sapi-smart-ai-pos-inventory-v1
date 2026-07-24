<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

defineProps({
    plans: { type: Array, required: true },
    rules: { type: Array, required: true },
});

const formatRupiah = (value) =>
    value === null
        ? 'tanpa batas'
        : new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);

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

// --- Aturan bracket ---
const showRuleForm = ref(false);
const ruleForm = useForm({
    label: '',
    min_revenue: 0,
    max_revenue: null,
    price: 0,
    effective_from: new Date().toISOString().slice(0, 10),
});

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

        <!-- Bracket jalur subsidi -->
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold text-foreground">Bracket omzet (jalur subsidi)</h2>
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
                            <th class="px-4 py-3 text-right">Omzet Dari</th>
                            <th class="px-4 py-3 text-right">Sampai</th>
                            <th class="px-4 py-3 text-right">Tarif</th>
                            <th class="px-4 py-3">Berlaku</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="rule in rules" :key="rule.id" class="hover:bg-accent/30 transition-colors">
                            <td class="px-4 py-3 font-medium text-foreground">{{ rule.label }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-foreground">{{ formatRupiah(rule.min_revenue) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-muted-foreground">{{ formatRupiah(rule.max_revenue) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums font-medium text-foreground">{{ formatRupiah(rule.price) }}</td>
                            <td class="px-4 py-3">
                                <span class="text-muted-foreground tabular-nums">{{ rule.effective_from }}</span>
                                <span
                                    v-if="!rule.is_effective"
                                    class="ml-1.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-500/15 text-amber-700"
                                >
                                    menunggu
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
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
                                Belum ada aturan bracket.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-4 text-xs text-muted-foreground leading-relaxed max-w-2xl">
            Aturan yang sudah berlaku tidak bisa dihapus — ia adalah dasar harga periode yang sudah lewat. Untuk
            mengubah tarif, terbitkan aturan baru dengan tanggal berlaku ke depan.
        </p>

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
                <div class="relative w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-2xl">
                    <h3 class="text-lg font-semibold text-foreground mb-1">Terbitkan Aturan Bracket</h3>
                    <p class="text-sm text-muted-foreground mb-4">
                        Berlaku sejak tanggal yang Anda pilih, dan tidak menyentuh periode sebelumnya.
                    </p>

                    <form class="space-y-4" @submit.prevent="submitRule">
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">Kelompok</label>
                            <input v-model="ruleForm.label" type="text" placeholder="A" :class="inputClass" />
                            <p v-if="ruleForm.errors.label" class="mt-1 text-xs text-destructive">{{ ruleForm.errors.label }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">Omzet dari (Rp)</label>
                            <input v-model.number="ruleForm.min_revenue" type="number" min="0" :class="inputClass" />
                            <p v-if="ruleForm.errors.min_revenue" class="mt-1 text-xs text-destructive">{{ ruleForm.errors.min_revenue }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-foreground mb-1">Sampai (Rp — kosongkan bila tanpa batas)</label>
                            <input v-model.number="ruleForm.max_revenue" type="number" min="0" :class="inputClass" />
                            <p v-if="ruleForm.errors.max_revenue" class="mt-1 text-xs text-destructive">{{ ruleForm.errors.max_revenue }}</p>
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
