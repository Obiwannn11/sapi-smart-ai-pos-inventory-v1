<script setup>
import { computed } from 'vue';

/**
 * Kotak keterangan di atas atau di bawah isi halaman.
 *
 * Panel platform banyak memuat kalimat yang menjelaskan AKIBAT sebuah tindakan
 * — apa yang tercatat di audit, apa yang tidak ikut berubah. Sebelumnya tiap
 * halaman menuliskan kotaknya sendiri dan tak ada dua yang sama.
 */
const props = defineProps({
    tone: { type: String, default: 'muted' }, // muted | warning | danger | info
});

const tones = {
    muted: 'border-border bg-accent/30 text-muted-foreground',
    warning: 'border-amber-500/40 bg-amber-500/10 text-foreground',
    danger: 'border-destructive/30 bg-destructive/10 text-foreground',
    info: 'border-primary/30 bg-primary/5 text-foreground',
};

const toneClass = computed(() => tones[props.tone] ?? tones.muted);
</script>

<template>
    <div class="rounded-lg border px-4 py-3" :class="toneClass">
        <p class="text-sm leading-relaxed">
            <slot />
        </p>
    </div>
</template>
