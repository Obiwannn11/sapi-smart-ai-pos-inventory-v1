<script setup>
/**
 * SkeletonChart — bar placeholders at the chart's real height, so the panel
 * below it does not travel up the page when the chart renders.
 *
 * The bar heights come from a fixed pattern: a loading state should suggest
 * "a chart goes here", not invent a trend the data may contradict.
 */
import Skeleton from '@/Components/Skeleton/Skeleton.vue';

defineProps({
    bars: { type: Number, default: 7 },
    /** Match the height class of the chart canvas it stands in for. */
    heightClass: { type: String, default: 'h-64' },
    /**
     * Set this ONLY when this skeleton is the outermost one in its loading
     * region — it then announces itself to screen readers. Nested inside a
     * SkeletonPanel it must stay unset: the panel already announces, and two
     * live regions for one section read the same wait out twice.
     */
    label: { type: String, default: null },
});

const BAR_HEIGHTS = [46, 68, 54, 82, 60, 92, 50];

const barHeight = (bar) => `${BAR_HEIGHTS[(bar - 1) % BAR_HEIGHTS.length]}%`;
</script>

<template>
    <div :role="label ? 'status' : null" :aria-busy="label ? 'true' : null">
        <div class="flex items-end gap-2" :class="heightClass">
            <Skeleton
                v-for="bar in bars"
                :key="bar"
                rounded="none"
                class="flex-1 rounded-t-md"
                :style="{ height: barHeight(bar) }"
            />
        </div>
        <span v-if="label" class="sr-only">{{ label }}</span>
    </div>
</template>
