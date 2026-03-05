<script setup>
import { usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const page = usePage();

const flash = computed(() => page.props.flash);
const visible = ref(false);
const message = ref('');
const type = ref('success');

watch(flash, (f) => {
    if (f?.success) {
        message.value = f.success;
        type.value = 'success';
        visible.value = true;
        autoHide();
    } else if (f?.error) {
        message.value = f.error;
        type.value = 'error';
        visible.value = true;
        autoHide();
    }
}, { immediate: true, deep: true });

let timer = null;
const autoHide = () => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        visible.value = false;
    }, 4000);
};

const dismiss = () => {
    visible.value = false;
    clearTimeout(timer);
};
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
                'fixed top-4 right-4 z-[200] max-w-sm w-full px-4 py-3 rounded-lg shadow-lg flex items-center gap-3',
                type === 'success' ? 'bg-green-50 border border-green-200 text-green-800' : 'bg-red-50 border border-red-200 text-red-800'
            ]"
        >
            <!-- Success icon -->
            <svg v-if="type === 'success'" class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <!-- Error icon -->
            <svg v-else class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>

            <p class="text-sm font-medium flex-1">{{ message }}</p>

            <button @click="dismiss" class="flex-shrink-0 text-gray-400 hover:text-gray-600">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </Transition>
</template>
