<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { businessToday, businessDaysAgo, parseDateOnly } from '@/support/date';

/**
 * Satu kontrol untuk satu rentang tanggal.
 *
 * Menggantikan dua `DatePicker` berdampingan. Dua kolom "Dari" dan "Sampai"
 * memaksa pemakainya membuka dua kalender, mengingat tanggal pertama sambil
 * memilih yang kedua, dan tidak satu pun dari keduanya tahu bahwa yang lain
 * ada — rentang terbalik baru ketahuan setelah tombol kirim ditekan dan server
 * menolaknya. Di sini keduanya dipilih di kalender yang sama, dan urutannya
 * dibetulkan sendiri.
 *
 * Nilainya tetap dua tanggal terpisah (`from` dan `to`) supaya muatan yang
 * dikirim ke server tidak berubah sama sekali.
 */
const props = defineProps({
    from: String, // YYYY-MM-DD
    to: String,   // YYYY-MM-DD
    block: { type: Boolean, default: false },
    // Pintasan yang paling sering dipakai. Dimatikan bila layarnya sudah
    // punya pilihan periodenya sendiri.
    presets: { type: Boolean, default: true },
});

const emit = defineEmits(['update:from', 'update:to', 'change']);

const open = ref(false);
const container = ref(null);
const popup = ref(null);
const popupStyle = ref({});

const CALENDAR_HEIGHT = 380;
const CALENDAR_WIDTH = 288;

const computePosition = () => {
    if (!container.value) return;
    const rect = container.value.getBoundingClientRect();
    const spaceBelow = window.innerHeight - rect.bottom;
    const spaceAbove = rect.top;
    const openAbove = spaceBelow < CALENDAR_HEIGHT && spaceAbove > spaceBelow;

    const top = openAbove ? rect.top - CALENDAR_HEIGHT - 8 : rect.bottom + 8;
    const left = Math.min(rect.left, window.innerWidth - CALENDAR_WIDTH - 8);

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

// Hari TOKO, bukan hari perangkat ([BL-082]) — alasan yang sama seperti di
// DatePicker.
const todayStr = businessToday();
const today = parseDateOnly(todayStr);

const parseDate = (str) => {
    if (!str) return new Date(today);
    const [y, m, d] = str.split('-').map(Number);
    return new Date(y, m - 1, d);
};

const toStr = (d) => [
    d.getFullYear(),
    String(d.getMonth() + 1).padStart(2, '0'),
    String(d.getDate()).padStart(2, '0'),
].join('-');

const initView = () => {
    const d = parseDate(props.from);
    return { year: d.getFullYear(), month: d.getMonth() };
};

const currentView = ref(initView());

/**
 * Ujung yang sedang ditunggu. Klik pertama menaruh awal rentang dan
 * mengosongkan ujungnya; klik kedua menutupnya. Selama menunggu klik kedua,
 * kotak yang dilewati kursor ikut tersorot supaya rentangnya terlihat sebelum
 * dikunci.
 */
const pendingStart = ref(null);
const hovered = ref(null);

const toggleOpen = () => {
    if (!open.value) {
        currentView.value = initView();
        pendingStart.value = null;
        hovered.value = null;
        computePosition();
    }
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

const days = computed(() => {
    const { year, month } = currentView.value;
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDow = (firstDay.getDay() + 6) % 7;

    const grid = [];
    const prevLast = new Date(year, month, 0);

    for (let i = startDow - 1; i >= 0; i--) {
        grid.push({ date: new Date(year, month - 1, prevLast.getDate() - i), current: false });
    }
    for (let i = 1; i <= lastDay.getDate(); i++) {
        grid.push({ date: new Date(year, month, i), current: true });
    }
    const rem = grid.length % 7;
    if (rem !== 0) {
        for (let i = 1; i <= 7 - rem; i++) {
            grid.push({ date: new Date(year, month + 1, i), current: false });
        }
    }

    return grid;
});

const applyRange = (start, end) => {
    // Urutannya dibetulkan di sini, bukan dilarang di kalender: memilih 20
    // lalu 14 jelas berarti "14 sampai 20", dan menolaknya cuma memaksa
    // pemakainya mengulang dari awal.
    const [from, to] = start <= end ? [start, end] : [end, start];
    emit('update:from', from);
    emit('update:to', to);
    emit('change', { from, to });
};

const selectDay = (day) => {
    const value = toStr(day.date);

    if (!pendingStart.value) {
        pendingStart.value = value;
        hovered.value = value;
        return;
    }

    applyRange(pendingStart.value, value);
    pendingStart.value = null;
    hovered.value = null;
    open.value = false;
};

const applyPreset = (days) => {
    applyRange(businessDaysAgo(days - 1), todayStr);
    pendingStart.value = null;
    hovered.value = null;
    open.value = false;
};

/** Batas rentang yang sedang digambar: yang tersimpan, atau yang sedang dipilih. */
const bounds = computed(() => {
    if (pendingStart.value) {
        const other = hovered.value ?? pendingStart.value;
        return pendingStart.value <= other
            ? { from: pendingStart.value, to: other }
            : { from: other, to: pendingStart.value };
    }

    return { from: props.from, to: props.to };
});

const isEdge = (value) => value === bounds.value.from || value === bounds.value.to;
const isInside = (value) => {
    const { from, to } = bounds.value;
    return Boolean(from && to) && value > from && value < to;
};
const isToday = (day) => toStr(day.date) === todayStr;

const formatEdge = (value, withYear) => parseDate(value).toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'short',
    ...(withYear ? { year: 'numeric' } : {}),
});

const formattedRange = computed(() => {
    if (!props.from || !props.to) return 'Pilih Rentang Tanggal';

    // Tahunnya ditulis sekali kalau keduanya di tahun yang sama — "1 Sep – 30
    // Sep 2026" terbaca lebih cepat daripada tahun yang diulang dua kali.
    const sameYear = props.from.slice(0, 4) === props.to.slice(0, 4);

    return `${formatEdge(props.from, !sameYear)} – ${formatEdge(props.to, true)}`;
});

const dayHeaders = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

const handleClickOutside = (e) => {
    const insideContainer = container.value && container.value.contains(e.target);
    const insidePopup = popup.value && popup.value.contains(e.target);
    if (!insideContainer && !insidePopup) {
        open.value = false;
        pendingStart.value = null;
        hovered.value = null;
    }
};

onMounted(() => document.addEventListener('mousedown', handleClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', handleClickOutside));
</script>

<template>
    <div ref="container" :class="block ? 'block w-full' : 'inline-block'">
        <button
            type="button"
            class="items-center gap-2 px-3 py-2 bg-white border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-ring transition-colors shadow-sm"
            :class="block ? 'flex w-full' : 'inline-flex'"
            @click="toggleOpen"
        >
            <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span class="font-medium whitespace-nowrap" :class="{ 'flex-1 text-left truncate': block }">
                {{ formattedRange }}
            </span>
            <svg
                class="w-3.5 h-3.5 text-gray-400 transition-transform flex-shrink-0"
                :class="{ 'rotate-180': open }"
                fill="none" stroke="currentColor" viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

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
                <!-- Kalimat yang memberi tahu klik berikutnya apa artinya.
                     Tanpanya, kalender rentang terasa seperti kalender biasa
                     yang tidak mau menutup. -->
                <p class="mb-2 text-xs text-gray-500">
                    {{ pendingStart ? 'Pilih tanggal akhir' : 'Pilih tanggal awal' }}
                </p>

                <div class="flex items-center justify-between mb-3">
                    <button
                        type="button"
                        class="p-1.5 hover:bg-gray-100 rounded-lg transition-colors text-gray-500"
                        @click="prevMonth"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <span class="text-sm font-semibold text-gray-800 capitalize">{{ monthLabel }}</span>
                    <button
                        type="button"
                        class="p-1.5 hover:bg-gray-100 rounded-lg transition-colors text-gray-500"
                        @click="nextMonth"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <div class="grid grid-cols-7 mb-1">
                    <div
                        v-for="h in dayHeaders"
                        :key="h"
                        class="text-xs font-medium text-gray-400 text-center py-1"
                    >
                        {{ h }}
                    </div>
                </div>

                <div class="grid grid-cols-7 gap-y-0.5">
                    <button
                        v-for="(day, i) in days"
                        :key="i"
                        type="button"
                        class="h-9 w-full text-xs flex items-center justify-center transition-colors"
                        :class="[
                            isInside(toStr(day.date)) ? 'bg-primary/10' : '',
                            isEdge(toStr(day.date))
                                ? 'bg-primary text-primary-foreground font-semibold rounded-full'
                                : isToday(day) && day.current
                                    ? 'font-semibold text-primary rounded-full ring-1 ring-primary/40 hover:bg-primary/10'
                                    : day.current
                                        ? 'text-gray-700 rounded-full hover:bg-gray-100'
                                        : 'text-gray-300 rounded-full hover:bg-gray-50',
                        ]"
                        @click="selectDay(day)"
                        @mouseenter="pendingStart && (hovered = toStr(day.date))"
                    >
                        {{ day.date.getDate() }}
                    </button>
                </div>

                <div v-if="presets" class="mt-3 pt-2.5 border-t border-gray-100 flex items-center justify-between gap-2">
                    <button
                        v-for="preset in [{ label: '7 hari', days: 7 }, { label: '30 hari', days: 30 }, { label: '90 hari', days: 90 }]"
                        :key="preset.days"
                        type="button"
                        class="flex-1 text-xs text-center text-primary hover:text-primary/80 font-medium py-0.5 transition-colors"
                        @click="applyPreset(preset.days)"
                    >
                        {{ preset.label }}
                    </button>
                </div>
            </div>
        </Transition>
        </Teleport>
    </div>
</template>
