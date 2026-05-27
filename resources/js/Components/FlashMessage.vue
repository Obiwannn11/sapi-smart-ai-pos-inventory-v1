<script setup>
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { useFlash } from '@/composables/useFlash';

const page = usePage();
const { message, type, visible, show, dismiss } = useFlash();

// Bridge server-side Inertia flash into the shared composable state.
watch(() => page.props.flash, (f) => {
    if (f?.success) show(f.success, 'success');
    else if (f?.error) show(f.error, 'error');
    else if (f?.warning) show(f.warning, 'warning');
}, { immediate: true, deep: true });
</script>

<template>
    <Transition
        enter-active-class="transition-all duration-300 ease-out"
        enter-from-class="translate-y-[-100%] opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-[-100%] opacity-0"
    >
        <div
            v-if="visible"
            :class="[
                'fixed top-4 right-4 z-[200] max-w-sm w-full px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 border bg-card',
                type === 'success' && 'border-primary/40 text-primary',
                type === 'error'   && 'border-destructive/40 text-destructive',
                type === 'warning' && 'border-warning/50 text-warning-foreground',
            ]"
            role="alert"
            aria-live="assertive"
        >
            <!-- Success icon -->
            <svg v-if="type === 'success'" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <!-- Error icon -->
            <svg v-else-if="type === 'error'" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <!-- Warning icon -->
            <svg v-else class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.834-1.964-.834-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>

            <p class="text-sm font-medium flex-1">{{ message }}</p>

            <button
                @click="dismiss"
                class="flex-shrink-0 opacity-50 hover:opacity-80 transition-opacity"
                aria-label="Tutup notifikasi"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </Transition>
</template>
