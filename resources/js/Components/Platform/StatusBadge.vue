<script setup>
import { computed } from 'vue';

/**
 * Lencana keadaan.
 *
 * Nadanya dipilih pemanggil, bukan ditebak dari teksnya: label yang sama bisa
 * berarti kabar baik di satu halaman dan peringatan di halaman lain.
 */
const props = defineProps({
    label: { type: String, required: true },
    tone: { type: String, default: 'neutral' }, // neutral | success | warning | danger | info
    title: { type: String, default: '' },
});

const tones = {
    neutral: 'bg-muted text-muted-foreground',
    success: 'bg-primary/10 text-primary',
    warning: 'bg-amber-500/15 text-amber-700',
    danger: 'bg-destructive/10 text-destructive',
    info: 'bg-sky-500/15 text-sky-700',
};

const toneClass = computed(() => tones[props.tone] ?? tones.neutral);
</script>

<template>
    <span
        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap"
        :class="toneClass"
        :title="title || undefined"
    >
        {{ label }}
    </span>
</template>
