<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

/**
 * Shared, design-system-aligned error screen used by the Admin and User
 * error pages. Copy and accent colour are derived from the HTTP status code.
 */
const props = defineProps({
    status: { type: Number, default: 500 },
    // Where the primary action button navigates ("home" for that audience).
    homeHref: { type: String, required: true },
    homeLabel: { type: String, required: true },
    // Larger touch targets for the cashier/POS (user) context.
    large: { type: Boolean, default: false },
});

const content = {
    400: {
        title: 'Permintaan Tidak Valid',
        description:
            'Permintaan Anda tidak dapat kami proses. Periksa kembali data yang dikirim, lalu coba lagi.',
        tone: 'warning',
    },
    500: {
        title: 'Terjadi Kesalahan',
        description:
            'Sistem sedang mengalami gangguan. Kami sedang menanganinya — silakan coba beberapa saat lagi.',
        tone: 'destructive',
    },
};

const fallback = {
    title: 'Terjadi Kesalahan',
    description: 'Sesuatu tidak berjalan sebagaimana mestinya. Silakan coba lagi.',
    tone: 'destructive',
};

const current = computed(() => content[props.status] ?? fallback);
const isWarning = computed(() => current.value.tone === 'warning');

const badgeClasses = computed(() =>
    isWarning.value
        ? 'bg-warning/10 text-warning border-warning/20'
        : 'bg-destructive/10 text-destructive border-destructive/20',
);

const reload = () => {
    if (typeof window !== 'undefined') {
        window.location.reload();
    }
};
</script>

<template>
    <Head :title="`${status} — ${current.title}`" />

    <main
        class="min-h-screen bg-background flex flex-col items-center justify-center px-6 py-14"
        role="main"
    >
        <!-- Brand wordmark -->
        <Link
            :href="homeHref"
            class="absolute top-6 left-6 flex items-baseline gap-1.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded-md"
        >
            <span class="text-lg font-bold text-foreground tracking-tight leading-none">SAPI</span>
            <span class="text-[0.6rem] font-semibold text-muted-foreground uppercase tracking-widest">POS</span>
        </Link>

        <div class="w-full max-w-md text-center">

            <!-- Status glyph -->
            <div class="relative flex justify-center">
                <span
                    class="absolute -top-8 text-[9rem] font-black leading-none tracking-tighter
                           text-foreground/[0.035] select-none pointer-events-none"
                    aria-hidden="true"
                >{{ status }}</span>

                <div
                    :class="[
                        'relative z-10 flex items-center justify-center w-16 h-16 rounded-2xl border',
                        badgeClasses,
                    ]"
                >
                    <!-- Warning triangle (4xx) -->
                    <svg
                        v-if="isWarning"
                        class="w-8 h-8"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.75"
                            d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                        />
                    </svg>
                    <!-- Server error (5xx) -->
                    <svg
                        v-else
                        class="w-8 h-8"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.75"
                            d="M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2M5 12h14M8 8h.01M8 16h.01"
                        />
                    </svg>
                </div>
            </div>

            <!-- Status code eyebrow -->
            <p class="mt-6 text-xs font-semibold uppercase tracking-widest text-muted-foreground font-mono">
                Kesalahan {{ status }}
            </p>

            <!-- Title -->
            <h1 class="mt-2 text-2xl font-bold text-foreground tracking-tight">
                {{ current.title }}
            </h1>

            <!-- Description -->
            <p class="mt-3 text-sm text-muted-foreground leading-relaxed">
                {{ current.description }}
            </p>

            <!-- Actions -->
            <div
                :class="[
                    'mt-8 flex flex-col sm:flex-row items-stretch sm:items-center justify-center gap-3',
                ]"
            >
                <Link
                    :href="homeHref"
                    :class="[
                        'inline-flex items-center justify-center gap-2 font-semibold rounded-lg',
                        'bg-primary text-primary-foreground hover:bg-primary/90 active:bg-primary/80',
                        'transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                        large ? 'px-6 py-3 text-base' : 'px-5 py-2.5 text-sm',
                    ]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 22V12h6v10" />
                    </svg>
                    {{ homeLabel }}
                </Link>

                <button
                    type="button"
                    @click="reload"
                    :class="[
                        'inline-flex items-center justify-center gap-2 font-medium rounded-lg',
                        'bg-card text-foreground border border-border hover:bg-muted',
                        'transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
                        large ? 'px-6 py-3 text-base' : 'px-5 py-2.5 text-sm',
                    ]"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    Muat Ulang
                </button>
            </div>
        </div>

        <!-- Footer meta -->
        <p class="absolute bottom-6 text-xs text-muted-foreground/70">
            SAPI POS · Kelola kasir, stok, dan laporan dari satu tempat.
        </p>
    </main>
</template>
