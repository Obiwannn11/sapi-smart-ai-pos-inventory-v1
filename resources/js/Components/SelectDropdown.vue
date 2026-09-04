<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number, Boolean, null], default: '' },
    options: { type: Array, default: () => [] },
    optionLabel: { type: String, default: 'label' },
    optionValue: { type: String, default: 'value' },
    placeholder: { type: String, default: 'Pilih...' },
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    searchable: { type: Boolean, default: false },
    clearable: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

const open = ref(false);
const container = ref(null);
const searchInput = ref(null);
const popupStyle = ref({});
const search = ref('');

const MAX_HEIGHT = 300;

// Normalize options so both primitives and objects work.
const normalized = computed(() =>
    props.options.map((opt) => {
        if (opt !== null && typeof opt === 'object') {
            return { value: opt[props.optionValue], label: opt[props.optionLabel] };
        }
        return { value: opt, label: String(opt) };
    })
);

const filtered = computed(() => {
    if (!props.searchable || !search.value.trim()) {
        return normalized.value;
    }
    const q = search.value.trim().toLowerCase();
    return normalized.value.filter((o) => String(o.label).toLowerCase().includes(q));
});

// Yang menentukan "sudah memilih" adalah ADA TIDAKNYA opsi yang cocok, bukan
// isi nilainya. Sebagian daftar memakai '' sebagai pilihan sah — "Default",
// "Semua" — dan menganggapnya kosong membuat label pilihan itu tidak pernah
// bisa tampil, padahal ia sedang terpilih.
const selectedOption = computed(
    () => normalized.value.find((o) => o.value === props.modelValue) ?? null
);

const selectedLabel = computed(() => selectedOption.value?.label ?? '');

const hasSelection = computed(() => selectedOption.value !== null);

const computePosition = () => {
    if (!container.value) {
        return;
    }
    const rect = container.value.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;
    const openAbove = spaceBelow < MAX_HEIGHT && spaceAbove > spaceBelow;

    popupStyle.value = {
        position: 'fixed',
        top: openAbove ? 'auto' : rect.bottom + 6 + 'px',
        bottom: openAbove ? window.innerHeight - rect.top + 6 + 'px' : 'auto',
        left: rect.left + 'px',
        width: rect.width + 'px',
        zIndex: 9999,
    };
};

watch(open, (isOpen) => {
    if (isOpen) {
        window.addEventListener('scroll', computePosition, true);
        window.addEventListener('resize', computePosition);
    } else {
        search.value = '';
        window.removeEventListener('scroll', computePosition, true);
        window.removeEventListener('resize', computePosition);
    }
});

const toggleOpen = () => {
    if (props.disabled) {
        return;
    }
    if (!open.value) {
        computePosition();
        open.value = true;
        if (props.searchable) {
            nextTick(() => searchInput.value?.focus());
        }
    } else {
        open.value = false;
    }
};

const selectOption = (option) => {
    emit('update:modelValue', option.value);
    emit('change', option.value);
    open.value = false;
};

const clearSelection = () => {
    emit('update:modelValue', '');
    emit('change', '');
    open.value = false;
};

const isSelected = (option) => option.value === props.modelValue;

const handleEscape = (e) => {
    if (e.key === 'Escape' && open.value) {
        open.value = false;
    }
};

const handleClickOutside = (e) => {
    if (container.value && !container.value.contains(e.target) && !e.target.closest('[data-select-popup]')) {
        open.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', handleClickOutside);
    document.addEventListener('keydown', handleEscape);
});
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', handleClickOutside);
    document.removeEventListener('keydown', handleEscape);
    window.removeEventListener('scroll', computePosition, true);
    window.removeEventListener('resize', computePosition);
});
</script>

<template>
    <div ref="container">
        <label v-if="label" class="block text-sm font-medium text-gray-700 mb-1">{{ label }}</label>

        <!-- Trigger -->
        <button
            type="button"
            :disabled="disabled"
            role="combobox"
            aria-haspopup="listbox"
            :aria-expanded="open"
            @click="toggleOpen"
            :class="[
                'w-full flex items-center gap-2 px-3 py-2 bg-white border rounded-lg text-sm text-left transition-colors focus:outline-none focus:ring-2 focus:ring-ring',
                error ? 'border-destructive/50' : 'border-gray-300',
                disabled ? 'opacity-60 cursor-not-allowed bg-gray-50' : 'hover:bg-gray-50 cursor-pointer',
            ]"
        >
            <span :class="['flex-1 truncate', hasSelection ? 'text-gray-800' : 'text-gray-400']">
                {{ hasSelection ? selectedLabel : placeholder }}
            </span>

            <span
                v-if="clearable && hasSelection && !disabled"
                role="button"
                tabindex="0"
                aria-label="Kosongkan pilihan"
                @click.stop="clearSelection"
                @keydown.enter.stop.prevent="clearSelection"
                @keydown.space.stop.prevent="clearSelection"
                class="text-gray-300 hover:text-gray-500 flex-shrink-0 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </span>

            <svg
                class="w-3.5 h-3.5 text-gray-400 transition-transform flex-shrink-0"
                :class="{ 'rotate-180': open }"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <p v-if="error" class="mt-1 text-xs text-destructive">{{ error }}</p>

        <!-- Dropdown -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0 scale-95 -translate-y-1"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-95 -translate-y-1"
            >
                <div
                    v-if="open"
                    data-select-popup
                    role="listbox"
                    :style="popupStyle"
                    class="bg-white rounded-xl shadow-lg border border-gray-200 py-1.5 origin-top"
                >
                    <!-- Search -->
                    <div v-if="searchable" class="px-2 pb-1.5">
                        <input
                            ref="searchInput"
                            v-model="search"
                            type="text"
                            placeholder="Cari..."
                            class="w-full px-2.5 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                    </div>

                    <div class="max-h-60 overflow-y-auto">
                        <button
                            v-for="option in filtered"
                            :key="String(option.value)"
                            type="button"
                            role="option"
                            :aria-selected="isSelected(option)"
                            @click="selectOption(option)"
                            :class="[
                                'w-full flex items-center justify-between gap-2 px-3 py-2 text-sm text-left transition-colors',
                                isSelected(option)
                                    ? 'bg-primary/10 text-primary font-medium'
                                    : 'text-gray-700 hover:bg-gray-100',
                            ]"
                        >
                            <span class="truncate">{{ option.label }}</span>
                            <svg
                                v-if="isSelected(option)"
                                class="w-4 h-4 flex-shrink-0"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>

                        <div v-if="filtered.length === 0" class="px-3 py-4 text-center text-sm text-gray-400">
                            Tidak ada pilihan
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
