<script setup>
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Konfirmasi' },
    message: { type: String, default: 'Apakah Anda yakin ingin melakukan tindakan ini?' },
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
                <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full p-6">
                    <div class="flex items-start gap-4">
                        <!-- Icon -->
                        <div :class="[
                            'flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center',
                            variant === 'danger' ? 'bg-red-100' : 'bg-yellow-100'
                        ]">
                            <svg class="w-5 h-5" :class="variant === 'danger' ? 'text-red-600' : 'text-yellow-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>

                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ message }}</p>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            @click="cancel"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            {{ cancelText }}
                        </button>
                        <button
                            @click="confirm"
                            :class="[
                                'px-4 py-2 text-sm font-medium text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2',
                                variant === 'danger'
                                    ? 'bg-red-600 hover:bg-red-700 focus:ring-red-500'
                                    : 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500'
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
