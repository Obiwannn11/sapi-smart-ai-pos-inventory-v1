<script setup>
import { ref, watch } from 'vue';
import { Deferred, Head, router } from '@inertiajs/vue3';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';
import PageHeader from '@/Components/Platform/PageHeader.vue';
import DataTable from '@/Components/Platform/DataTable.vue';
import StatusBadge from '@/Components/Platform/StatusBadge.vue';
import FormField from '@/Components/Platform/FormField.vue';
import Button from '@/Components/Button.vue';
import { inputClass } from '@/support/platform';
import Pagination from '@/Components/Pagination.vue';
import SkeletonTable from '@/Components/Skeleton/SkeletonTable.vue';

const props = defineProps({
    // Ditunda ([BL-037]) — null selama jejaknya masih dimuat.
    logs: { type: Object, default: null },
    filters: { type: Object, required: true },
    actions: { type: Array, required: true },
    retention: { type: Object, required: true },
});

const columns = [
    { key: 'at', label: 'Waktu' },
    { key: 'action', label: 'Aksi' },
    { key: 'actor', label: 'Oleh' },
    { key: 'subject', label: 'Subjek' },
    { key: 'ip', label: 'IP' },
];

const form = ref({
    action: props.filters.action ?? '',
    severity: props.filters.severity ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

let debounce = null;
watch(form, (value) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/platform/audit-logs', Object.fromEntries(Object.entries(value).filter(([, v]) => v !== '')), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }, 300);
}, { deep: true });

const reset = () => { form.value = { action: '', severity: '', from: '', to: '' }; };

const severity = (value) =>
    value === 'sensitive'
        ? { label: 'Sensitif', tone: 'warning' }
        : { label: 'Rutin', tone: 'neutral' };
</script>

<template>
    <Head title="Jejak Audit — Platform" />

    <PlatformLayout>
        <PageHeader title="Jejak Audit">
            <template #description>
                Kejadian <strong class="text-foreground">sensitif</strong> — perubahan akun, akses data bisnis klien,
                dan percobaan masuk yang gagal — selalu dicatat dan disimpan {{ retention.sensitive }} hari. Kejadian
                <strong class="text-foreground">rutin</strong> seperti membuka daftar dicatat sekali per sesi menengok,
                lalu dibuang setelah {{ retention.routine }} hari.
            </template>
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 mb-4">
            <FormField label="Aksi">
                <select v-model="form.action" :class="inputClass">
                    <option value="">Semua</option>
                    <option v-for="action in actions" :key="action" :value="action">{{ action }}</option>
                </select>
            </FormField>

            <FormField label="Derajat">
                <select v-model="form.severity" :class="inputClass">
                    <option value="">Semua</option>
                    <option value="sensitive">Sensitif</option>
                    <option value="routine">Rutin</option>
                </select>
            </FormField>

            <FormField label="Dari">
                <input v-model="form.from" type="date" :class="inputClass" />
            </FormField>

            <FormField label="Sampai">
                <input v-model="form.to" type="date" :class="inputClass" />
            </FormField>

            <div class="flex items-end">
                <Button variant="secondary" @click="reset">Reset</Button>
            </div>
        </div>

        <!-- Ditunda ([BL-037]): penyaringnya bekerja dengan permintaan baru
             tiap kali diubah, jadi kerangka ini juga penanda bahwa isi tabel
             sedang diganti. -->
        <Deferred data="logs">
            <template #fallback>
                <div class="rounded-lg border border-border bg-card shadow-sm overflow-hidden">
                    <SkeletonTable :rows="10" :columns="columns.length" label="Memuat jejak audit…" />
                </div>
            </template>

        <DataTable
            v-slot="{ cellClass }"
            :columns="columns"
            :count="logs.data.length"
            empty="Tidak ada catatan yang cocok dengan filter ini."
        >
            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-accent/30 transition-colors">
                <td :class="[cellClass, 'whitespace-nowrap text-muted-foreground tabular-nums']">{{ log.at }}</td>

                <td :class="cellClass">
                    <span class="font-medium text-foreground">{{ log.action }}</span>
                    <StatusBadge class="ml-1.5" :label="severity(log.severity).label" :tone="severity(log.severity).tone" />
                    <pre
                        v-if="log.meta"
                        class="mt-1 whitespace-pre-wrap break-all text-xs text-muted-foreground"
                    >{{ JSON.stringify(log.meta) }}</pre>
                </td>

                <td :class="[cellClass, 'text-foreground']">{{ log.actor }}</td>
                <td :class="[cellClass, 'text-muted-foreground']">{{ log.subject ?? '—' }}</td>
                <td :class="[cellClass, 'text-muted-foreground tabular-nums']">{{ log.ip ?? '—' }}</td>
            </tr>
        </DataTable>

        <Pagination :paginator="logs" tone="platform" unit="catatan" />
        </Deferred>
    </PlatformLayout>
</template>
