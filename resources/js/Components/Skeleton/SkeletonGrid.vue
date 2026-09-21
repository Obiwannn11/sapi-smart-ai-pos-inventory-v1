<script setup>
/**
 * SkeletonGrid — repeats a card placeholder across the same grid the real
 * content will use. The `columns` string is passed verbatim so it can be copied
 * from the grid it replaces; that copy is what keeps the page from jumping when
 * the data lands.
 *
 * The default slot receives each index, so a caller with a card shape of its
 * own can supply it instead of the built-in SkeletonCard.
 */
import SkeletonCard from '@/Components/Skeleton/SkeletonCard.vue';

defineProps({
    count: { type: Number, default: 8 },
    columns: { type: String, default: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4' },
    gap: { type: String, default: 'gap-3' },
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
    <div class="grid items-start" :class="[columns, gap]" :role="label ? 'status' : null" :aria-busy="label ? 'true' : null">
        <template v-for="index in count" :key="index">
            <slot :index="index - 1">
                <SkeletonCard />
            </slot>
        </template>
        <span v-if="label" class="sr-only">{{ label }}</span>
    </div>
</template>
