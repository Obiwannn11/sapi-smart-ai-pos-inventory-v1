<script setup>
import { ref } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    count: { type: [Number, String], default: null },
    defaultOpen: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const open = ref(props.defaultOpen);

const toggle = () => {
    if (props.disabled) {
        return;
    }
    open.value = !open.value;
};
</script>

<template>
    <div class="rounded-lg border border-gray-200">
        <!-- Trigger -->
        <button
            type="button"
            :disabled="disabled"
            @click="toggle"
            :aria-expanded="open"
            class="w-full flex items-center justify-between gap-3 px-3 py-2.5 text-left transition-colors rounded-lg focus:outline-none focus:ring-2 focus:ring-ring"
            :class="disabled ? 'cursor-not-allowed opacity-60' : 'hover:bg-gray-50'"
        >
            <span class="flex items-center gap-2 min-w-0">
                <slot name="title">
                    <span class="text-sm font-medium text-gray-700 truncate">{{ title }}</span>
                </slot>
                <span
                    v-if="count !== null"
                    class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 text-xs font-medium flex-shrink-0"
                >
                    {{ count }}
                </span>
            </span>
            <svg
                class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0"
                :class="{ 'rotate-180': open }"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Content: max-height transition keeps the final state class-driven (never stuck) -->
        <div class="accordion-panel" :class="{ 'is-open': open }">
            <div class="px-3 pb-3 pt-1 border-t border-gray-100">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.accordion-panel {
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.25s ease;
}

.accordion-panel.is-open {
    /* Generous ceiling; product lists stay well under this. */
    max-height: 40rem;
}
</style>
