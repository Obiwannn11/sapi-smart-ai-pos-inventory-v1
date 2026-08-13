<script setup>
/**
 * SkeletonCard — one card-shaped placeholder. Covers the two card shapes the
 * app actually renders: a product card (square image on top, name, price) and
 * an alert card (leading icon, title, message).
 *
 * `media` and `icon` are mutually exclusive; media wins if both are passed.
 */
import Skeleton from '@/Components/Skeleton/Skeleton.vue';
import SkeletonText from '@/Components/Skeleton/SkeletonText.vue';

defineProps({
    /** Square image block on top — the POS product card. */
    media: { type: Boolean, default: false },
    /** Leading square beside the text — the badge/alert card. */
    icon: { type: Boolean, default: false },
    lines: { type: Number, default: 2 },
    /** Short bar under the text, for a price or a timestamp. */
    footer: { type: Boolean, default: false },
    padding: { type: String, default: 'p-3' },
    /**
     * Border width class of the card it stands in for — the POS product card
     * carries `border-2`. Passed as a class rather than a boolean so the value
     * is copied verbatim from the real card and cannot conflict with it.
     */
    borderWidth: { type: String, default: 'border' },
});
</script>

<template>
    <div class="bg-card rounded-xl border-border" :class="[padding, borderWidth]" aria-hidden="true">
        <Skeleton v-if="media" class="w-full aspect-square mb-2" rounded="lg" />

        <div class="flex items-start gap-3">
            <Skeleton v-if="icon && !media" class="w-9 h-9 shrink-0" rounded="lg" />

            <div class="flex-1 min-w-0 space-y-2">
                <SkeletonText :lines="lines" line-class="h-3.5" last-width="w-3/5" />
                <Skeleton v-if="footer" class="h-3 w-20" rounded="sm" />
            </div>
        </div>
    </div>
</template>
