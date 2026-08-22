<script setup>
import { computed } from 'vue';
import { Line } from 'vue-chartjs';
import { BUSINESS_TZ } from '@/support/date';
import {
    Chart as ChartJS,
    CategoryScale,
    Filler,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';

ChartJS.register(LineElement, PointElement, CategoryScale, LinearScale, Filler, Tooltip);

const DEFAULT_PRIMARY_COLOR = '#1f9d78';

const props = defineProps({
    // [{ date: 'YYYY-MM-DD', revenue, count }] — deret HARUS lengkap, termasuk
    // hari nol. Tanggal yang bolong akan tersambung jadi garis lurus dan
    // terbaca seolah hari itu tetap ramai.
    data: { type: Array, default: () => [] },
    title: { type: String, default: 'Tren Pendapatan' },
    emptyLabel: { type: String, default: 'Belum ada data transaksi' },
});

const getThemeColor = (variableName, fallback = DEFAULT_PRIMARY_COLOR) => {
    if (typeof window === 'undefined') return fallback;

    const value = getComputedStyle(document.documentElement).getPropertyValue(variableName).trim();
    return value || fallback;
};

/**
 * Versi tembus pandang dari sebuah warna tema.
 *
 * Warna tema ditulis dalam oklch(), yang tidak bisa disisipi alpha lewat
 * manipulasi string. Jadi warnanya dilukis ke kanvas 1×1 lalu piksel itu
 * dibaca kembali: apa pun format yang dipahami browser masuk, rgba keluar.
 */
const withAlpha = (color, alpha) => {
    if (typeof document === 'undefined') return color;

    try {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = color;
        ctx.fillRect(0, 0, 1, 1);
        const [r, g, b] = ctx.getImageData(0, 0, 1, 1).data;

        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    } catch {
        return color;
    }
};

const chartData = computed(() => {
    const primary = getThemeColor('--color-primary');
    const brand = getThemeColor('--color-brand', primary);

    return {
        labels: props.data.map((d) => new Date(d.date).getDate()),
        datasets: [
            {
                label: 'Pendapatan',
                data: props.data.map((d) => Number(d.revenue)),
                borderColor: primary,
                backgroundColor: withAlpha(primary, 0.12),
                fill: true,
                tension: 0.3,
                borderWidth: 2,
                // Titik disembunyikan sampai disentuh: 31 bulatan di satu garis
                // membuat bentuk trennya sendiri jadi sulit dilihat.
                pointRadius: 0,
                pointHoverRadius: 5,
                pointHoverBackgroundColor: brand,
                pointHoverBorderColor: '#FFFFFF',
                pointHoverBorderWidth: 2,
                pointHitRadius: 12,
            },
        ],
    };
});

const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                title: (items) => {
                    const item = props.data[items[0].dataIndex];
                    if (!item) return '';

                    return new Date(item.date).toLocaleDateString('id-ID', {
                        timeZone: BUSINESS_TZ,
                        weekday: 'long',
                        day: 'numeric',
                        month: 'long',
                    });
                },
                label: (ctx) => 'Rp ' + Number(ctx.raw).toLocaleString('id-ID'),
                afterLabel: (ctx) => {
                    const item = props.data[ctx.dataIndex];
                    if (!item) return '';

                    return item.count > 0 ? `${item.count} transaksi` : 'tidak ada penjualan';
                },
            },
        },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: {
                callback: (value) => {
                    if (value >= 1_000_000) return 'Rp ' + (value / 1_000_000).toFixed(1) + 'jt';
                    if (value >= 1_000) return 'Rp ' + (value / 1_000).toFixed(0) + 'rb';
                    return 'Rp ' + value;
                },
                font: { size: 11 },
                color: '#9CA3AF',
            },
            grid: { color: '#F3F4F6' },
            border: { display: false },
        },
        x: {
            ticks: {
                font: { size: 11 },
                color: '#6B7280',
                autoSkip: true,
                maxRotation: 0,
                maxTicksLimit: 16,
            },
            grid: { display: false },
            border: { display: false },
        },
    },
}));

const hasSales = computed(() => props.data.some((d) => Number(d.revenue) > 0));
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">{{ title }}</h3>

        <div v-if="hasSales" class="h-64">
            <Line :data="chartData" :options="chartOptions" />
        </div>

        <!-- Kanvas kosong tanpa keterangan akan dianggap rusak, bukan kosong. -->
        <div v-else class="h-64 flex flex-col items-center justify-center gap-1 text-center">
            <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            </svg>
            <p class="text-sm text-gray-400">{{ emptyLabel }}</p>
        </div>
    </div>
</template>
