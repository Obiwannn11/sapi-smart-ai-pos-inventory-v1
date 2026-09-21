<script setup>
defineProps({
    label: { type: String, required: true },
    /** Keterangan pendek DI ATAS field — dibaca sebelum orang mengisi. */
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
});
</script>

<template>
    <div>
        <label class="block text-sm font-medium text-foreground mb-1">{{ label }}</label>
        <p v-if="hint" class="mb-1.5 text-xs text-muted-foreground leading-relaxed">{{ hint }}</p>

        <slot />

        <p v-if="error" class="mt-1 text-xs text-destructive">{{ error }}</p>
        <p v-else-if="$slots.footnote" class="mt-1 text-xs text-muted-foreground leading-relaxed">
            <slot name="footnote" />
        </p>
    </div>
</template>
