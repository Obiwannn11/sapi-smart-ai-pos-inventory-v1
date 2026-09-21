<script setup>
/**
 * SkeletonTable — a real <table> of placeholder cells, so the browser
 * distributes the columns the same way it will once the rows arrive.
 *
 * Cell widths cycle through a fixed pattern rather than being random: a
 * skeleton that reshuffles on every render draws the eye to itself instead of
 * to the page that is loading.
 */
import Skeleton from '@/Components/Skeleton/Skeleton.vue';

defineProps({
    rows: { type: Number, default: 6 },
    columns: { type: Number, default: 5 },
    header: { type: Boolean, default: true },
    /**
     * Set this ONLY when this skeleton is the outermost one in its loading
     * region — it then announces itself to screen readers. Nested inside a
     * SkeletonPanel it must stay unset: the panel already announces, and two
     * live regions for one section read the same wait out twice.
     */
    label: { type: String, default: null },
});

const CELL_WIDTHS = ['w-24', 'w-16', 'w-20', 'w-28', 'w-14', 'w-20'];

const cellWidth = (column) => CELL_WIDTHS[(column - 1) % CELL_WIDTHS.length];
</script>

<template>
    <div class="overflow-x-auto" :role="label ? 'status' : null" :aria-busy="label ? 'true' : null">
        <table class="min-w-full">
            <thead v-if="header" class="bg-muted/60">
                <tr>
                    <th v-for="column in columns" :key="column" class="py-3 px-4 text-left">
                        <Skeleton class="h-3 w-16" rounded="sm" />
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in rows"
                    :key="row"
                    class="border-b border-border/60 last:border-0"
                >
                    <td v-for="column in columns" :key="column" class="py-3 px-4">
                        <Skeleton class="h-3.5" :class="cellWidth(column)" rounded="sm" />
                    </td>
                </tr>
            </tbody>
        </table>
        <span v-if="label" class="sr-only">{{ label }}</span>
    </div>
</template>
