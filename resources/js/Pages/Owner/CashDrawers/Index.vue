<script setup>
import { Head, Link } from '@inertiajs/vue3';
import OwnerLayout from '@/Layouts/OwnerLayout.vue';

defineOptions({ layout: OwnerLayout });

const props = defineProps({
    cashDrawers: Object, // paginated
});

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

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
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
                            <td class="py-3 px-4 text-right font-semibold" :class="cd.difference < 0 ? 'text-red-600' : cd.difference > 0 ? 'text-green-600' : 'text-gray-600'">
                                {{ cd.difference !== null ? formatCurrency(cd.difference) : '-' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="cd.closed_at ? 'bg-gray-100 text-gray-700' : 'bg-green-100 text-green-800'"
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
                                ? 'bg-indigo-600 text-white'
                                : link.url
                                    ? 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                    : 'bg-gray-50 text-gray-300 cursor-not-allowed'
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
