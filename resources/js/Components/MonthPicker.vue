<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { businessMonth, parseDateOnly } from '@/support/date';

const props = defineProps({
    modelValue: String, // YYYY-MM
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const container = ref(null);
const popup = ref(null);
const popupStyle = ref({});

const PANEL_HEIGHT = 260;

const computePosition = () => {
    if (!container.value) return;
    const rect = container.value.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;
    const openAbove = spaceBelow < PANEL_HEIGHT && spaceAbove > spaceBelow;

    const top = openAbove
        ? rect.top - PANEL_HEIGHT - 8
        : rect.bottom + 8;

    const left = Math.min(rect.left, window.innerWidth - 288 - 8);

    popupStyle.value = { position: 'fixed', top: top + 'px', left: left + 'px', zIndex: 9999 };
};

watch(open, (isOpen) => {
    if (isOpen) {
        window.addEventListener('scroll', computePosition, true);
        window.addEventListener('resize', computePosition);
    } else {
        window.removeEventListener('scroll', computePosition, true);
        window.removeEventListener('resize', computePosition);
    }
});

// Bulan TOKO ([BL-082]); lihat DatePicker untuk alasan yang sama.
const currentMonthStr = businessMonth();
const now = parseDateOnly(`${currentMonthStr}-01`);

const parseMonth = (str) => {
    if (!str) return { year: now.getFullYear(), month: now.getMonth() };
    const [y, m] = str.split('-').map(Number);
    return { year: y, month: m - 1 };
};

const toStr = ({ year, month }) => [year, String(month + 1).padStart(2, '0')].join('-');

// Tahun yang sedang dilihat di panel — terpisah dari bulan yang dipilih,
// supaya pengguna bisa melihat-lihat tahun lalu tanpa mengubah pilihan.
const viewYear = ref(parseMonth(props.modelValue).year);

watch(() => props.modelValue, (value) => {
    viewYear.value = parseMonth(value).year;
});

const toggleOpen = () => {
    if (!open.value) {
        viewYear.value = parseMonth(props.modelValue).year;
        computePosition();
    }
    open.value = !open.value;
};

const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

const formattedMonth = computed(() => {
    const { year, month } = parseMonth(props.modelValue);
    return new Date(year, month, 1).toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
});

// Bulan yang belum terjadi tidak bisa dipilih: laporannya pasti kosong, dan
// layar kosong tanpa sebab terbaca seperti aplikasi yang rusak.
const isFuture = (monthIndex) => toStr({ year: viewYear.value, month: monthIndex }) > currentMonthStr;

const isSelected = (monthIndex) => toStr({ year: viewYear.value, month: monthIndex }) === props.modelValue;

const isCurrent = (monthIndex) => toStr({ year: viewYear.value, month: monthIndex }) === currentMonthStr;

const selectMonth = (monthIndex) => {
    if (isFuture(monthIndex)) return;
    emit('update:modelValue', toStr({ year: viewYear.value, month: monthIndex }));
    open.value = false;
};

const shift = (delta) => {
    const { year, month } = parseMonth(props.modelValue);
    const target = new Date(year, month + delta, 1);
    const next = toStr({ year: target.getFullYear(), month: target.getMonth() });
    if (next > currentMonthStr) return;
    emit('update:modelValue', next);
};

const canGoNext = computed(() => {
    const { year, month } = parseMonth(props.modelValue);
    const target = new Date(year, month + 1, 1);
    return toStr({ year: target.getFullYear(), month: target.getMonth() }) <= currentMonthStr;
});

const handleClickOutside = (e) => {
    const insideContainer = container.value && container.value.contains(e.target);
    const insidePopup = popup.value && popup.value.contains(e.target);
    if (!insideContainer && !insidePopup) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', handleClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', handleClickOutside));
</script>

<template>
    <div ref="container" class="inline-flex items-center gap-1">
        <!-- Bulan sebelumnya -->
        <button
            type="button"
            @click="shift(-1)"
            class="p-2 bg-white border border-gray-300 rounded-lg text-gray-500 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-ring transition-colors shadow-sm"
            aria-label="Bulan sebelumnya"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
        </button>

        <!-- Trigger -->
        <button
            type="button"
            @click="toggleOpen"
            class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-ring transition-colors shadow-sm"
        >
            <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span class="font-medium capitalize">{{ formattedMonth }}</span>
            <svg
                class="w-3.5 h-3.5 text-gray-400 transition-transform flex-shrink-0"
                :class="{ 'rotate-180': open }"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Bulan berikutnya -->
        <button
            type="button"
            @click="shift(1)"
            :disabled="!canGoNext"
            class="p-2 bg-white border border-gray-300 rounded-lg text-gray-500 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-ring transition-colors shadow-sm disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white"
            aria-label="Bulan berikutnya"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>

        <!-- Panel pilih bulan -->
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
                    ref="popup"
                    :style="popupStyle"
                    class="bg-white rounded-xl shadow-lg border border-gray-200 p-3 w-72 origin-top-left"
                >
                    <!-- Navigasi tahun -->
                    <div class="flex items-center justify-between mb-3">
                        <button
                            type="button"
                            @click="viewYear--"
                            class="p-1.5 hover:bg-gray-100 rounded-lg transition-colors text-gray-500"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <span class="text-sm font-semibold text-gray-800">{{ viewYear }}</span>
                        <button
                            type="button"
                            @click="viewYear++"
                            :disabled="viewYear >= now.getFullYear()"
                            class="p-1.5 hover:bg-gray-100 rounded-lg transition-colors text-gray-500 disabled:opacity-30 disabled:hover:bg-transparent"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                    </div>

                    <!-- Grid 12 bulan -->
                    <div class="grid grid-cols-3 gap-1.5">
                        <button
                            v-for="(name, index) in monthNames"
                            :key="name"
                            type="button"
                            @click="selectMonth(index)"
                            :disabled="isFuture(index)"
                            :class="[
                                'py-2 text-xs rounded-lg transition-colors',
                                isSelected(index)
                                    ? 'bg-primary text-primary-foreground font-semibold'
                                    : isFuture(index)
                                        ? 'text-gray-300 cursor-not-allowed'
                                        : isCurrent(index)
                                            ? 'font-semibold text-primary ring-1 ring-primary/40 hover:bg-primary/10'
                                            : 'text-gray-700 hover:bg-gray-100',
                            ]"
                        >
                            {{ name }}
                        </button>
                    </div>

                    <!-- Pintasan: bulan ini -->
                    <div class="mt-3 pt-2.5 border-t border-gray-100">
                        <button
                            type="button"
                            @click="emit('update:modelValue', currentMonthStr); open = false"
                            class="w-full text-xs text-center text-primary hover:text-primary/80 font-medium py-0.5 transition-colors"
                        >
                            Bulan Ini
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
