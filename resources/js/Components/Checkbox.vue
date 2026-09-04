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
    // Baris yang keterangannya panjang perlu kotaknya sejajar baris PERTAMA,
    // bukan di tengah tumpukan teks — centang yang melayang di tengah paragraf
    // terbaca seperti milik kalimat yang sedang disejajarinya.
    align: { type: String, default: 'center' },
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
    <!-- Kontrolnya bukan <input>, jadi peran dan keadaannya harus dinyatakan
         sendiri: tanpa ini pembaca layar hanya menemukan teks biasa, dan
         keyboard sama sekali tidak bisa mencapainya. -->
    <div
        role="checkbox"
        :aria-checked="isChecked"
        :aria-disabled="disabled || undefined"
        :tabindex="disabled ? -1 : 0"
        :class="[
            'flex gap-3 select-none transition-colors rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
            align === 'start' ? 'items-start' : 'items-center',
            disabled ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer',
            variant === 'card'
                ? [
                    'p-3 border',
                    isChecked ? 'border-primary bg-primary/10' : 'border-gray-200',
                    disabled ? 'bg-gray-50' : (isChecked ? '' : 'hover:border-gray-300 hover:bg-gray-50'),
                ]
                : '',
        ]"
        @click="toggle"
        @keydown.space.prevent="toggle"
        @keydown.enter.prevent="toggle"
    >
        <!-- Custom check box -->
        <span
            :class="[
                'flex-shrink-0 w-5 h-5 rounded-md border flex items-center justify-center transition-all',
                align === 'start' ? 'mt-0.5' : '',
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
    </div>
</template>
