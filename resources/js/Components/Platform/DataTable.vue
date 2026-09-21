<script setup>
/**
 * Kerangka tabel panel platform.
 *
 * Menyediakan kartu, gulir mendatar, kepala kolom, dan keadaan kosong. Isi
 * barisnya tetap milik halaman — yang diseragamkan di sini adalah metriknya,
 * lewat `cellClass` yang diteruskan ke slot supaya tiap sel memakai padding
 * yang sama tanpa halaman perlu mengingatnya.
 */
defineProps({
    /** @type {Array<{key: string, label: string, align?: 'left'|'right'|'center', width?: string}>} */
    columns: { type: Array, required: true },
    /** Jumlah baris yang akan digambar — dipakai memutuskan keadaan kosong. */
    count: { type: Number, required: true },
    empty: { type: String, default: 'Belum ada data.' },
});

const cellClass = 'px-5 py-4 text-sm align-top';

const alignClass = (align) => ({
    right: 'text-right',
    center: 'text-center',
}[align] ?? 'text-left');
</script>

<template>
    <div class="rounded-lg border border-border bg-card shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-accent/40 border-b border-border">
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            :style="column.width ? { width: column.width } : undefined"
                            :class="[
                                'px-5 py-3 text-xs font-semibold text-muted-foreground uppercase tracking-wider whitespace-nowrap',
                                alignClass(column.align),
                            ]"
                        >
                            {{ column.label }}
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-border">
                    <slot :cell-class="cellClass" />

                    <tr v-if="count === 0">
                        <td :colspan="columns.length" class="px-5 py-12 text-center text-sm text-muted-foreground">
                            <slot name="empty">{{ empty }}</slot>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
