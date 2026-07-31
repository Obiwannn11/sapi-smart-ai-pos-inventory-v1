<script setup>
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold text-foreground leading-tight">{{ title }}</h2>
            <p v-if="description || $slots.description" class="mt-1 text-sm text-muted-foreground leading-relaxed max-w-2xl">
                <slot name="description">{{ description }}</slot>
            </p>
        </div>

        <div v-if="$slots.default" class="flex flex-wrap items-center gap-2 flex-shrink-0">
            <slot />
        </div>
    </div>
</template>
