<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue: String, // YYYY-MM-DD
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const container = ref(null);

const today = new Date();
today.setHours(0, 0, 0, 0);

const todayStr = [
    today.getFullYear(),
    String(today.getMonth() + 1).padStart(2, '0'),
    String(today.getDate()).padStart(2, '0'),
].join('-');

const parseDate = (str) => {
    if (!str) return new Date(today);
    const [y, m, d] = str.split('-').map(Number);
    return new Date(y, m - 1, d);
};

const initView = () => {
    const d = parseDate(props.modelValue);
    return { year: d.getFullYear(), month: d.getMonth() };
};

const currentView = ref(initView());

const toggleOpen = () => {
    if (!open.value) currentView.value = initView();
    open.value = !open.value;
};

const prevMonth = () => {
    let { year, month } = currentView.value;
    month--;
    if (month < 0) { month = 11; year--; }
    currentView.value = { year, month };
};

const nextMonth = () => {
    let { year, month } = currentView.value;
    month++;
    if (month > 11) { month = 0; year++; }
    currentView.value = { year, month };
};

const monthLabel = computed(() => {
    const d = new Date(currentView.value.year, currentView.value.month, 1);
    return d.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
});

const toStr = (d) => [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
].join('-');

// Day grid starting Monday
const days = computed(() => {
    const { year, month } = currentView.value;
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    // Sunday=0 → shift so Monday=0
    let startDow = (firstDay.getDay() + 6) % 7;

    const grid = [];

    // Leading days from previous month
    const prevLast = new Date(year, month, 0);
    for (let i = startDow - 1; i >= 0; i--) {
        grid.push({ date: new Date(year, month - 1, prevLast.getDate() - i), current: false });
    }

    // Current month days
    for (let i = 1; i <= lastDay.getDate(); i++) {
        grid.push({ date: new Date(year, month, i), current: true });
    }

    // Trailing days to complete last row
    const rem = grid.length % 7;
    if (rem !== 0) {
        for (let i = 1; i <= 7 - rem; i++) {
            grid.push({ date: new Date(year, month + 1, i), current: false });
        }
    }

    return grid;
});

const selectDay = (day) => {
    emit('update:modelValue', toStr(day.date));
    open.value = false;
};

const isSelected = (day) => toStr(day.date) === props.modelValue;
const isToday = (day) => toStr(day.date) === todayStr;

const formattedDate = computed(() => {
    if (!props.modelValue) return 'Pilih Tanggal';
    return parseDate(props.modelValue).toLocaleDateString('id-ID', {
        weekday: 'short',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const dayHeaders = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

const handleClickOutside = (e) => {
    if (container.value && !container.value.contains(e.target)) {
        open.value = false;
    }
};

onMounted(() => document.addEventListener('mousedown', handleClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', handleClickOutside));
</script>

<template>
    <div ref="container" class="relative inline-block">
        <!-- Trigger button -->
        <button
            type="button"
            @click="toggleOpen"
            class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-ring transition-colors shadow-sm"
        >
            <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span class="font-medium">{{ formattedDate }}</span>
            <svg
                class="w-3.5 h-3.5 text-gray-400 transition-transform flex-shrink-0"
                :class="{ 'rotate-180': open }"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <!-- Calendar dropdown -->
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
                class="absolute left-0 top-full mt-2 z-50 bg-white rounded-xl shadow-lg border border-gray-200 p-3 w-72 origin-top-left"
            >
                <!-- Month navigation -->
                <div class="flex items-center justify-between mb-3">
                    <button
                        type="button"
                        @click="prevMonth"
                        class="p-1.5 hover:bg-gray-100 rounded-lg transition-colors text-gray-500"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <span class="text-sm font-semibold text-gray-800 capitalize">{{ monthLabel }}</span>
                    <button
                        type="button"
                        @click="nextMonth"
                        class="p-1.5 hover:bg-gray-100 rounded-lg transition-colors text-gray-500"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <!-- Day-of-week headers -->
                <div class="grid grid-cols-7 mb-1">
                    <div
                        v-for="h in dayHeaders"
                        :key="h"
                        class="text-xs font-medium text-gray-400 text-center py-1"
                    >
                        {{ h }}
                    </div>
                </div>

                <!-- Day grid -->
                <div class="grid grid-cols-7 gap-y-0.5">
                    <button
                        v-for="(day, i) in days"
                        :key="i"
                        type="button"
                        @click="selectDay(day)"
                        :class="[
                            'w-9 h-9 mx-auto text-xs rounded-full flex items-center justify-center transition-colors',
                            isSelected(day)
                                ? 'bg-primary text-primary-foreground font-semibold'
                                : isToday(day) && day.current
                                    ? 'font-semibold text-primary ring-1 ring-primary/40 hover:bg-primary/10'
                                    : day.current
                                        ? 'text-gray-700 hover:bg-gray-100'
                                        : 'text-gray-300 hover:bg-gray-50 cursor-default',
                        ]"
                    >
                        {{ day.date.getDate() }}
                    </button>
                </div>

                <!-- Quick: Today -->
                <div class="mt-3 pt-2.5 border-t border-gray-100">
                    <button
                        type="button"
                        @click="selectDay({ date: today, current: true })"
                        class="w-full text-xs text-center text-primary hover:text-primary/80 font-medium py-0.5 transition-colors"
                    >
                        Hari Ini
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
