<script setup>
/**
 * Kartu isi panel platform.
 *
 * Satu-satunya tempat metrik kartu ditulis — radius, border, bayangan, dan
 * padding kepala. Halaman yang menuliskannya sendiri adalah bagaimana satu
 * bagian aplikasi pelan-pelan terlihat berbeda dari bagian lainnya.
 */
defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    // Untuk isi yang mengatur paddingnya sendiri — tabel, daftar bergaris.
    flush: { type: Boolean, default: false },
});
</script>

<template>
    <section class="rounded-lg border border-border bg-card shadow-sm overflow-hidden">
        <header
            v-if="title || $slots.actions"
            class="flex flex-wrap items-start justify-between gap-3 px-5 py-4"
            :class="$slots.default ? 'border-b border-border' : ''"
        >
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-foreground">{{ title }}</h3>
                <p v-if="description || $slots.description" class="mt-1 text-xs text-muted-foreground leading-relaxed max-w-xl">
                    <slot name="description">{{ description }}</slot>
                </p>
            </div>

            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 flex-shrink-0">
                <slot name="actions" />
            </div>
        </header>

        <div v-if="$slots.default" :class="flush ? '' : 'px-5 py-4'">
            <slot />
        </div>

        <footer v-if="$slots.footer" class="border-t border-border bg-accent/30 px-5 py-3">
            <slot name="footer" />
        </footer>
    </section>
</template>
