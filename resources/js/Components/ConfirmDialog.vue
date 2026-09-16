<script setup>
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Konfirmasi' },
    message: { type: String, default: 'Tindakan ini tidak bisa dibatalkan.' },
    confirmText: { type: String, default: 'Hapus' },
    cancelText: { type: String, default: 'Batal' },
    variant: { type: String, default: 'danger' }, // danger | warning
});

const emit = defineEmits(['confirm', 'cancel']);

const isVisible = ref(false);

watch(() => props.show, (val) => {
    isVisible.value = val;
});

onMounted(() => {
    isVisible.value = props.show;
});

const confirm = () => {
    emit('confirm');
};

const cancel = () => {
    emit('cancel');
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isVisible" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50" @click="cancel" />

                <!-- Dialog -->
                <div class="relative bg-card border border-border rounded-xl shadow-2xl max-w-md w-full p-6">
                    <div class="flex items-start gap-4">
                        <!-- Icon -->
                        <div :class="[
                            'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center',
                            variant === 'danger' ? 'bg-destructive/10' : 'bg-warning/10'
                        ]">
                            <svg class="w-5 h-5" :class="variant === 'danger' ? 'text-destructive' : 'text-warning-foreground'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>

                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-foreground">{{ title }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground leading-relaxed">{{ message }}</p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            @click="cancel"
                            class="px-4 py-2 text-sm font-medium text-foreground bg-card border border-border rounded-lg hover:bg-accent/50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ring"
                        >
                            {{ cancelText }}
                        </button>
                        <button
                            @click="confirm"
                            :class="[
                                'px-4 py-2 text-sm font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2',
                                variant === 'danger'
                                    ? 'bg-destructive text-destructive-foreground hover:bg-destructive/90 focus:ring-destructive'
                                    : 'bg-warning text-warning-foreground hover:bg-warning/90 focus:ring-ring'
                            ]"
                        >
                            {{ confirmText }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
