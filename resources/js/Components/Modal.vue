<script setup>
import { watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    maxWidth: { type: String, default: 'max-w-md' }, // max-w-sm | max-w-md | max-w-lg | max-w-2xl ...
    closeOnBackdrop: { type: Boolean, default: true },
});

const emit = defineEmits(['close']);

const close = () => {
    emit('close');
};

const onBackdrop = () => {
    if (props.closeOnBackdrop) {
        close();
    }
};

const onKeydown = (e) => {
    if (e.key === 'Escape' && props.show) {
        close();
    }
};

// Kunci scroll body saat modal terbuka
watch(() => props.show, (val) => {
    document.body.style.overflow = val ? 'hidden' : '';
});

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    if (props.show) {
        document.body.style.overflow = 'hidden';
    }
});

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
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
            <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50" @click="onBackdrop" />

                <!-- Modal -->
                <div
                    :class="[
                        'relative bg-white rounded-xl shadow-2xl w-full max-h-[85vh] overflow-y-auto',
                        maxWidth,
                    ]"
                >
                    <!-- Header -->
                    <div v-if="title || $slots.header" class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                        <slot name="header">
                            <h3 class="text-lg font-semibold text-gray-800">{{ title }}</h3>
                        </slot>
                        <button type="button" @click="close" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="p-6">
                        <slot />
                    </div>

                    <!-- Footer -->
                    <div v-if="$slots.footer" class="sticky bottom-0 bg-white border-t border-gray-200 px-6 py-4">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
