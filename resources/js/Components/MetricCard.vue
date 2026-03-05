<script setup>
const props = defineProps({
    title: { type: String, required: true },
    value: { type: [String, Number], required: true },
    subtitle: { type: String, default: null },
    icon: { type: String, default: 'chart' },
    color: { type: String, default: 'indigo' }, // indigo | green | blue | purple
});

const colorClasses = {
    indigo: { bg: 'bg-indigo-50', icon: 'text-indigo-600', ring: 'ring-indigo-100' },
    green: { bg: 'bg-green-50', icon: 'text-green-600', ring: 'ring-green-100' },
    blue: { bg: 'bg-blue-50', icon: 'text-blue-600', ring: 'ring-blue-100' },
    purple: { bg: 'bg-purple-50', icon: 'text-purple-600', ring: 'ring-purple-100' },
};

const c = colorClasses[props.color] || colorClasses.indigo;
</script>

<template>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 ring-1" :class="c.ring">
        <div class="flex items-start justify-between">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-500 truncate">{{ title }}</p>
                <p class="mt-2 text-2xl font-bold text-gray-900 truncate">{{ value }}</p>
                <p v-if="subtitle" class="mt-1 text-xs text-gray-400">{{ subtitle }}</p>
            </div>
            <div class="flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center" :class="c.bg">
                <!-- Currency -->
                <svg v-if="icon === 'currency'" class="w-5 h-5" :class="c.icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <!-- Receipt -->
                <svg v-else-if="icon === 'receipt'" class="w-5 h-5" :class="c.icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z" />
                </svg>
                <!-- Average -->
                <svg v-else-if="icon === 'average'" class="w-5 h-5" :class="c.icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <!-- Chart (default) -->
                <svg v-else class="w-5 h-5" :class="c.icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                </svg>
            </div>
        </div>
    </div>
</template>
