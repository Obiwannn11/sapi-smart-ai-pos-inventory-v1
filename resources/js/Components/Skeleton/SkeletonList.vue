<script setup>
/**
 * SkeletonList — the row list shape: leading icon, two lines of text, and a
 * right-aligned pair (amount over timestamp). Used for "Transaksi Terbaru" and
 * every other panel that lists records one per row.
 *
 * Rows carry the same padding and divider as the real ones, so the panel keeps
 * its height and the divider positions do not shift.
 */
import Skeleton from '@/Components/Skeleton/Skeleton.vue';

defineProps({
    rows: { type: Number, default: 5 },
    /** Leading square — the icon tile on the left of each row. */
    leading: { type: Boolean, default: true },
    /** Right-aligned pair: a value over a smaller meta line. */
    trailing: { type: Boolean, default: true },
    rowClass: { type: String, default: 'px-5 py-3' },
    /**
     * Set this ONLY when this skeleton is the outermost one in its loading
     * region — it then announces itself to screen readers. Nested inside a
     * SkeletonPanel it must stay unset: the panel already announces, and two
     * live regions for one section read the same wait out twice.
     */
    label: { type: String, default: null },
});
</script>

<template>
    <div :role="label ? 'status' : null" :aria-busy="label ? 'true' : null">
        <div
            v-for="row in rows"
            :key="row"
            class="flex items-center justify-between gap-3 border-b border-border/60 last:border-0"
            :class="rowClass"
        >
            <div class="flex items-center gap-3 min-w-0">
                <Skeleton v-if="leading" class="w-8 h-8 shrink-0" rounded="lg" />
                <div class="space-y-2">
                    <Skeleton class="h-3.5 w-32" rounded="sm" />
                    <Skeleton class="h-3 w-20" rounded="sm" />
                </div>
            </div>

            <div v-if="trailing" class="flex flex-col items-end gap-2 shrink-0">
                <Skeleton class="h-3.5 w-24" rounded="sm" />
                <Skeleton class="h-3 w-16" rounded="sm" />
            </div>
        </div>

        <span v-if="label" class="sr-only">{{ label }}</span>
    </div>
</template>
