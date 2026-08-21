<script setup>
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';
import SkeletonTable from '@/Components/Skeleton/SkeletonTable.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    // Ditunda ([BL-037]) — null selama daftar sesi kasnya masih dimuat.
    cashDrawers: { type: Object, default: null }, // paginated
    // TIDAK ditunda: satu-satunya bagian halaman ini yang menuntut tindakan
    // ([BL-087]).
    pendingMovements: { type: Array, default: () => [] },
});

const reviewing = ref(null);

const review = (movement, decision) => {
    if (reviewing.value) return;
    reviewing.value = movement.id;

    router.post(`/owner/cash-drawer-movements/${movement.id}/${decision}`, {}, {
        preserveScroll: true,
        onFinish: () => { reviewing.value = null; },
    });
};

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const formatDateTime = (datetime) => {
    if (!datetime) return '-';
    return new Date(datetime).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <Head title="Riwayat Sesi Kas" />

    <div class="max-w-6xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Riwayat Sesi Kas</h1>
            <p class="text-sm text-gray-500 mt-1">Semua sesi kas kasir</p>
        </div>

        <!-- Menunggu keputusan pemilik ([BL-087]).
             Di ATAS daftar dan tidak ditunda: ini satu-satunya bagian halaman
             ini yang menuntut tindakan, dan tindakan yang muncul belakangan
             akan terlewat oleh pemilik yang sudah selesai membaca. -->
        <div v-if="pendingMovements.length" class="bg-white rounded-xl shadow-sm border border-warning/40 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">Uang Keluar Menunggu Persetujuan</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    Sampai Anda menyetujuinya, nominal ini <strong>belum</strong> mengurangi uang yang seharusnya ada di laci —
                    jadi ia tidak bisa dipakai menutupi selisih.
                </p>
            </div>

            <ul class="divide-y divide-gray-50">
                <li v-for="movement in pendingMovements" :key="movement.id" class="px-5 py-3 flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm text-gray-800">
                            <span class="font-semibold font-mono">
                                {{ movement.type === 'payout' ? '−' : '+' }}{{ formatCurrency(movement.amount) }}
                            </span>
                            — {{ movement.reason }}
                        </p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ movement.user?.name || 'Kasir' }} · dicatat {{ formatDateTime(movement.created_at) }}
                            · sesi dibuka {{ formatDateTime(movement.cash_drawer?.opened_at) }}
                        </p>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <button
                            type="button"
                            @click="review(movement, 'reject')"
                            :disabled="reviewing === movement.id"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg border border-border text-foreground hover:bg-muted transition disabled:opacity-50"
                        >
                            Tolak
                        </button>
                        <button
                            type="button"
                            @click="review(movement, 'approve')"
                            :disabled="reviewing === movement.id"
                            class="px-3 py-1.5 text-xs font-medium rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 transition disabled:opacity-50"
                        >
                            Setujui
                        </button>
                    </div>
                </li>
            </ul>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <Deferred data="cashDrawers">
                <template #fallback>
                    <SkeletonTable :rows="8" :columns="8" label="Memuat riwayat sesi kas…" />
                </template>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left py-3 px-4 text-gray-500 font-medium">Kasir</th>
                            <th class="text-left py-3 px-4 text-gray-500 font-medium">Buka Kas</th>
                            <th class="text-left py-3 px-4 text-gray-500 font-medium">Tutup Kas</th>
                            <th class="text-right py-3 px-4 text-gray-500 font-medium">Modal</th>
                            <th class="text-right py-3 px-4 text-gray-500 font-medium">Expected</th>
                            <th class="text-right py-3 px-4 text-gray-500 font-medium">Aktual</th>
                            <th class="text-right py-3 px-4 text-gray-500 font-medium">Selisih</th>
                            <!-- [BL-090]: apakah kasir sudah membaca angka
                                 "seharusnya di laci" sebelum menghitung. Bukan
                                 pelanggaran — konteks untuk membaca selisih di
                                 sebelahnya. -->
                            <th class="text-center py-3 px-4 text-gray-500 font-medium">Angka Dibuka</th>
                            <th class="text-center py-3 px-4 text-gray-500 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="cd in cashDrawers.data"
                            :key="cd.id"
                            class="border-b border-gray-50 hover:bg-gray-50 transition-colors"
                        >
                            <td class="py-3 px-4 font-medium text-gray-800">{{ cd.user?.name || '-' }}</td>
                            <td class="py-3 px-4 text-gray-600">{{ formatDateTime(cd.opened_at) }}</td>
                            <td class="py-3 px-4 text-gray-600">{{ formatDateTime(cd.closed_at) }}</td>
                            <td class="py-3 px-4 text-right text-gray-700">{{ formatCurrency(cd.opening_amount) }}</td>
                            <td class="py-3 px-4 text-right text-gray-700">
                                {{ cd.expected_amount !== null ? formatCurrency(cd.expected_amount) : '-' }}
                            </td>
                            <td class="py-3 px-4 text-right text-gray-700">
                                {{ cd.closing_amount !== null ? formatCurrency(cd.closing_amount) : '-' }}
                            </td>
                            <td class="py-3 px-4 text-right font-semibold" :class="cd.difference < 0 ? 'text-destructive' : cd.difference > 0 ? 'text-success' : 'text-muted-foreground'">
                                {{ cd.difference !== null ? formatCurrency(cd.difference) : '-' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span
                                    v-if="cd.reveals_count > 0"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-warning/10 text-warning-foreground"
                                    :title="`Pertama dibuka ${formatDateTime(cd.reveals_min_revealed_at)}`"
                                >
                                    {{ cd.reveals_count }}×
                                </span>
                                <span v-else class="text-xs text-muted-foreground">—</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <!-- Sesi yang ditutup sistem ([BL-088]) dibedakan
                                     dari yang ditutup kasir: uang fisiknya tidak
                                     pernah dihitung siapa pun, jadi barisnya
                                     bukan pertanggungjawaban melainkan sesuatu
                                     yang perlu ditinjau. -->
                                <span
                                    v-if="cd.closed_by_system"
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-destructive/10 text-destructive"
                                    title="Ditutup otomatis setelah lewat batas umur sesi — uang fisiknya tidak pernah dihitung"
                                >
                                    Ditutup sistem
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="cd.closed_at ? 'bg-muted text-muted-foreground' : 'bg-success/10 text-success'"
                                >
                                    {{ cd.closed_at ? 'Closed' : 'Open' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="cashDrawers.data?.length === 0" class="px-5 py-8 text-center text-sm text-gray-400">
                Belum ada sesi kas
            </div>

            <!-- Pagination -->
            <div v-if="cashDrawers.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                <p class="text-xs text-gray-500">
                    Menampilkan {{ cashDrawers.from }}–{{ cashDrawers.to }} dari {{ cashDrawers.total }}
                </p>
                <div class="flex gap-1">
                    <Link
                        v-for="link in cashDrawers.links"
                        :key="link.label"
                        :href="link.url || '#'"
                        :class="[
                            'px-3 py-1.5 text-xs rounded-lg transition-colors',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : link.url
                                    ? 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                    : 'bg-gray-50 text-gray-300 cursor-not-allowed'
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
            </Deferred>
        </div>
    </div>
</template>
