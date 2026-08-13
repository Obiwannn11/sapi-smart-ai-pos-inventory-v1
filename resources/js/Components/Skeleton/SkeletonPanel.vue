<script setup>
/**
 * SkeletonPanel — the card-shaped shell that every deferred section on a page
 * loads inside. It reproduces the resting panel box (`bg-card`, border, xl
 * radius, ambient shadow) plus a placeholder heading, so the section keeps its
 * outline and its heading position while the data is still in flight.
 *
 * Pass `flush` when the body runs edge to edge — a table or a list of rows —
 * so the heading takes its own bordered header instead of shared padding.
 */
import Skeleton from '@/Components/Skeleton/Skeleton.vue';

defineProps({
    /** Placeholder bar where the section heading will land. */
    title: { type: Boolean, default: true },
    /** Second, smaller bar on the right — for headings that carry a "Lihat Semua →" link. */
    action: { type: Boolean, default: false },
    /** Body runs edge to edge (tables, row lists) instead of sitting in p-5. */
    flush: { type: Boolean, default: false },
    /** Announced to screen readers, which never see the grey blocks. */
    label: { type: String, default: 'Memuat…' },
});
</script>

<template>
    <section
        class="bg-card rounded-xl shadow-sm border border-border overflow-hidden"
        :class="flush ? '' : 'p-5'"
        role="status"
        aria-busy="true"
    >
        <div
            v-if="title"
            class="flex items-center justify-between gap-3"
            :class="flush ? 'px-5 py-4 border-b border-border' : 'mb-4'"
        >
            <Skeleton class="h-4 w-40" rounded="sm" />
            <Skeleton v-if="action" class="h-3 w-24" rounded="sm" />
        </div>

        <slot />

        <span class="sr-only">{{ label }}</span>
    </section>
</template>
