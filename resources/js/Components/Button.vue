<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary', // primary | secondary | ghost | soft | destructive | destructiveSoft
    },
    size: {
        type: String,
        default: 'md', // sm | md | lg
    },
    type: { type: String, default: 'button' },
    href: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    block: { type: Boolean, default: false },
});

const base =
    'inline-flex items-center justify-center gap-2 font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ring disabled:opacity-50 disabled:cursor-not-allowed';

// Ditulis dengan token tema, bukan warna mentah. Panel kasir, owner, dan
// platform memakai komponen yang SAMA — begitu salah satunya butuh warna
// mentah, tombol yang sama mulai terlihat berbeda antar panel.
const variants = {
    primary: 'bg-primary text-primary-foreground hover:bg-primary/90',
    secondary: 'bg-card text-foreground border border-border hover:bg-accent/50',
    ghost: 'text-muted-foreground hover:text-foreground hover:bg-muted',
    soft: 'text-primary bg-primary/10 border border-primary/20 hover:bg-primary/20',
    destructive: 'bg-destructive text-destructive-foreground hover:bg-destructive/90',
    destructiveSoft: 'text-destructive bg-destructive/10 border border-destructive/20 hover:bg-destructive/20',
};

const sizes = {
    sm: 'px-2.5 py-1.5 text-xs',
    md: 'px-4 py-2 text-sm',
    lg: 'px-6 py-2.5 text-sm',
};

const classes = computed(() => [
    base,
    variants[props.variant] ?? variants.primary,
    sizes[props.size] ?? sizes.md,
    props.block ? 'w-full' : '',
]);

const isDisabled = computed(() => props.disabled || props.loading);
</script>

<template>
    <Link
        v-if="href"
        :href="href"
        :class="classes"
    >
        <slot name="icon" />
        <slot />
    </Link>
    <button
        v-else
        :type="type"
        :disabled="isDisabled"
        :class="classes"
    >
        <svg
            v-if="loading"
            class="w-4 h-4 animate-spin"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
        <slot v-else name="icon" />
        <slot />
    </button>
</template>
