<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], default: '—' },
    hint: { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-lg border border-border bg-card shadow-sm px-5 py-4">
        <p class="text-xs font-medium text-muted-foreground uppercase tracking-wider">{{ label }}</p>
        <p class="mt-1.5 text-2xl font-bold text-foreground tabular-nums leading-tight">
            <slot>{{ value }}</slot>
        </p>
        <p v-if="hint || $slots.hint" class="mt-1 text-xs text-muted-foreground leading-relaxed">
            <slot name="hint">{{ hint }}</slot>
        </p>
    </div>
</template>
