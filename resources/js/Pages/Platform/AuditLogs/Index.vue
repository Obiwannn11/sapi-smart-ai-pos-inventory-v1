<script setup>
import { Head, router, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import PlatformLayout from '@/Layouts/PlatformLayout.vue';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, required: true },
    actions: { type: Array, required: true },
    retention: { type: Object, required: true },
});

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

const severityLabel = (s) => (s === 'sensitive' ? 'Sensitif' : 'Rutin');
</script>

<template>
    <Head title="Jejak Audit — Platform" />

    <PlatformLayout>
        <template #header>Jejak Audit</template>

        <p class="mb-4 max-w-3xl text-sm text-muted-foreground leading-relaxed">
            Kejadian <strong class="text-foreground">sensitif</strong> — perubahan akun, akses data bisnis klien, dan percobaan masuk yang gagal — selalu dicatat dan disimpan
            {{ retention.sensitive }} hari. Kejadian <strong class="text-foreground">rutin</strong> seperti membuka daftar dicatat sekali per sesi menengok, lalu dibuang setelah
            {{ retention.routine }} hari.
        </p>

        <!-- Filter -->
        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">Aksi</label>
                <select v-model="form.action" class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm">
                    <option value="">Semua</option>
                    <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">Derajat</label>
                <select v-model="form.severity" class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm">
                    <option value="">Semua</option>
                    <option value="sensitive">Sensitif</option>
                    <option value="routine">Rutin</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">Dari</label>
                <input v-model="form.from" type="date" class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
            </div>
            <div>
                <label class="block text-xs font-medium text-muted-foreground mb-1">Sampai</label>
                <input v-model="form.to" type="date" class="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" />
            </div>
            <div class="flex items-end">
                <button type="button" class="rounded-lg border border-border px-3.5 py-2 text-sm text-muted-foreground hover:text-foreground transition-colors" @click="reset">
                    Reset
                </button>
            </div>
        </div>

        <!-- Tabel -->
        <div class="rounded-xl border border-border bg-card overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-accent/50 text-left">
                        <tr class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Aksi</th>
                            <th class="px-4 py-3">Oleh</th>
                            <th class="px-4 py-3">Subjek</th>
                            <th class="px-4 py-3">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="log in logs.data" :key="log.id" class="hover:bg-accent/30 transition-colors align-top">
                            <td class="px-4 py-3 whitespace-nowrap text-muted-foreground tabular-nums">{{ log.at }}</td>
                            <td class="px-4 py-3">
                                <span class="font-medium text-foreground">{{ log.action }}</span>
                                <span
                                    class="ml-2 rounded px-1.5 py-0.5 text-[0.65rem] font-semibold uppercase tracking-wide"
                                    :class="log.severity === 'sensitive'
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                                        : 'bg-accent text-muted-foreground'"
                                >{{ severityLabel(log.severity) }}</span>
                                <pre v-if="log.meta" class="mt-1 whitespace-pre-wrap break-all text-xs text-muted-foreground">{{ JSON.stringify(log.meta) }}</pre>
                            </td>
                            <td class="px-4 py-3 text-foreground">{{ log.actor }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ log.subject ?? '—' }}</td>
                            <td class="px-4 py-3 text-muted-foreground tabular-nums">{{ log.ip ?? '—' }}</td>
                        </tr>

                        <tr v-if="logs.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-muted-foreground">
                                Tidak ada catatan yang cocok dengan filter ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginasi -->
        <div v-if="logs.links.length > 3" class="mt-4 flex flex-wrap gap-1">
            <component
                :is="link.url ? Link : 'span'"
                v-for="(link, i) in logs.links"
                :key="i"
                :href="link.url"
                preserve-scroll
                class="rounded-lg px-3 py-1.5 text-sm"
                :class="link.active
                    ? 'bg-slate-800 text-white'
                    : link.url ? 'text-muted-foreground hover:bg-accent' : 'text-muted-foreground/40'"
                v-html="link.label"
            />
        </div>
    </PlatformLayout>
</template>
