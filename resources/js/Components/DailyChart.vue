<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    BarElement,
    CategoryScale,
    LinearScale,
    Tooltip,
    Legend,
} from 'chart.js';

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend);

const props = defineProps({
    data: { type: Array, default: () => [] }, // [{ date, count, revenue }]
});

const chartData = computed(() => {
    const labels = props.data.map((d) => {
        const date = new Date(d.date);
        return date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
    });

    return {
        labels,
        datasets: [
            {
                label: 'Pendapatan',
                data: props.data.map((d) => Number(d.revenue)),
                backgroundColor: 'rgba(79, 70, 229, 0.8)',
                borderRadius: 6,
                borderSkipped: false,
                barThickness: 28,
            },
        ],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => 'Rp ' + Number(ctx.raw).toLocaleString('id-ID'),
                afterLabel: (ctx) => {
                    const item = props.data[ctx.dataIndex];
                    return item ? `${item.count} transaksi` : '';
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
            ticks: { font: { size: 11 }, color: '#6B7280' },
            grid: { display: false },
            border: { display: false },
        },
    },
};
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Trend Pendapatan 7 Hari</h3>
        <div v-if="data.length > 0" class="h-64">
            <Bar :data="chartData" :options="chartOptions" />
        </div>
        <div v-else class="h-64 flex items-center justify-center text-gray-400 text-sm">
            Belum ada data transaksi
        </div>
    </div>
</template>
