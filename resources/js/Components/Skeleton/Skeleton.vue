<script setup>
/**
 * Skeleton — the single grey block every other Skeleton* component is built
 * from. Colour, radius and the pulse live here and nowhere else, so a change
 * to the loading language is a one-file change.
 *
 * Size is deliberately NOT a prop: the caller passes it through classes
 * (`<Skeleton class="h-4 w-32" />`) because a skeleton's height and width must
 * copy the content it stands in for. See DESIGN.md §6, "The Same-Space Rule".
 *
 * `rounded="none"` leaves the corners to the caller, for the cases that need a
 * one-sided radius (chart bars, rounded only at the top).
 */

const RADII = {
    none: '',
    sm: 'rounded-sm',
    md: 'rounded-md',
    lg: 'rounded-lg',
    xl: 'rounded-xl',
    full: 'rounded-full',
};

defineProps({
    /** One of the keys of RADII above. Anything else falls back to `md`. */
    rounded: { type: String, default: 'md' },
});
</script>

<template>
    <div
        class="bg-muted animate-pulse motion-reduce:animate-none"
        :class="RADII[rounded] ?? RADII.md"
        aria-hidden="true"
    />
</template>
