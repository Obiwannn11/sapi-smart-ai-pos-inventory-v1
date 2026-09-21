<script setup>
/**
 * SkeletonText — a stack of line bars standing in for a paragraph or for the
 * two-or-three lines of text inside a row or card.
 *
 * The last line is short on purpose: an even block of equal-width bars reads
 * as a table, not as prose.
 */
import Skeleton from '@/Components/Skeleton/Skeleton.vue';

defineProps({
    lines: { type: Number, default: 3 },
    /** Tailwind height class for each bar — match the text size it replaces. */
    lineClass: { type: String, default: 'h-3' },
    /** Width of the closing line. Set to `w-full` for a block with no ragged end. */
    lastWidth: { type: String, default: 'w-2/5' },
});
</script>

<template>
    <div class="space-y-2">
        <Skeleton
            v-for="line in lines"
            :key="line"
            rounded="sm"
            :class="[lineClass, line === lines && lines > 1 ? lastWidth : 'w-full']"
        />
    </div>
</template>
