<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [Boolean, Array], default: false },
    value: { type: [String, Number, Boolean], default: null },
    label: { type: String, default: '' },
    description: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    // 'card' renders a bordered, selectable card; 'inline' is a plain checkbox row.
    variant: { type: String, default: 'inline' },
});

const emit = defineEmits(['update:modelValue']);

const isChecked = computed(() => {
    if (Array.isArray(props.modelValue)) {
        return props.modelValue.includes(props.value);
    }
    return !!props.modelValue;
});

const toggle = () => {
    if (props.disabled) {
        return;
    }

    if (Array.isArray(props.modelValue)) {
        const next = [...props.modelValue];
        const idx = next.indexOf(props.value);
        if (idx >= 0) {
            next.splice(idx, 1);
        } else {
            next.push(props.value);
        }
        emit('update:modelValue', next);
    } else {
        emit('update:modelValue', !props.modelValue);
    }
};
</script>

<template>
    <label
        :class="[
            'flex items-center gap-3 cursor-pointer select-none transition-colors',
            disabled ? 'opacity-50 cursor-not-allowed' : '',
            variant === 'card'
                ? [
                    'p-3 border rounded-lg',
                    isChecked ? 'border-primary bg-primary/10' : 'border-gray-200 hover:border-gray-300',
                ]
                : '',
        ]"
        @click.prevent="toggle"
    >
        <!-- Custom check box -->
        <span
            :class="[
                'flex-shrink-0 w-5 h-5 rounded-md border flex items-center justify-center transition-all',
                isChecked
                    ? 'bg-primary border-primary text-primary-foreground'
                    : 'bg-white border-gray-300',
            ]"
        >
            <svg
                v-show="isChecked"
                class="w-3.5 h-3.5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
            </svg>
        </span>

        <span v-if="label || description || $slots.default" class="min-w-0">
            <slot>
                <span class="block text-sm font-medium text-gray-700">{{ label }}</span>
                <span v-if="description" class="block text-xs text-gray-500 mt-0.5">{{ description }}</span>
            </slot>
        </span>
    </label>
</template>
