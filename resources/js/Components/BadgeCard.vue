<script setup>
import { ref } from 'vue';

const props = defineProps({
    type: { type: String, required: true }, // low_stock | out_of_stock | dead_stock | near_expiry
    severity: { type: String, required: true }, // danger | warning | info
    title: { type: String, required: true },
    count: { type: Number, required: true },
    message: { type: String, required: true },
    items: { type: Array, default: () => [] },
});

const expanded = ref(false);

const severityClasses = {
    danger: {
        bg: 'bg-red-50',
        border: 'border-red-200',
        icon: 'text-red-500',
        badge: 'bg-red-100 text-red-800',
        title: 'text-red-800',
        msg: 'text-red-600',
    },
    warning: {
        bg: 'bg-amber-50',
        border: 'border-amber-200',
        icon: 'text-amber-500',
        badge: 'bg-amber-100 text-amber-800',
        title: 'text-amber-800',
        msg: 'text-amber-600',
    },
    info: {
        bg: 'bg-blue-50',
        border: 'border-blue-200',
        icon: 'text-blue-500',
        badge: 'bg-blue-100 text-blue-800',
        title: 'text-blue-800',
        msg: 'text-blue-600',
    },
};

const c = severityClasses[props.severity] || severityClasses.info;
</script>

<template>
    <div class="rounded-xl border p-4 cursor-pointer transition-all" :class="[c.bg, c.border]" @click="expanded = !expanded">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <!-- Icon -->
                <div class="flex-shrink-0">
                    <!-- Out of stock / Danger -->
                    <svg v-if="severity === 'danger'" class="w-5 h-5" :class="c.icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <!-- Warning -->
                    <svg v-else-if="severity === 'warning'" class="w-5 h-5" :class="c.icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <!-- Info -->
                    <svg v-else class="w-5 h-5" :class="c.icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold" :class="c.title">{{ title }}</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="c.badge">
                            {{ count }}
                        </span>
                    </div>
                    <p class="text-xs mt-0.5" :class="c.msg">{{ message }}</p>
                </div>
            </div>

            <!-- Expand icon -->
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': expanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>

        <!-- Expanded items -->
        <Transition
            enter-active-class="transition-all duration-200 ease-out"
            enter-from-class="max-h-0 opacity-0"
            enter-to-class="max-h-96 opacity-100"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="max-h-96 opacity-100"
            leave-to-class="max-h-0 opacity-0"
        >
            <div v-if="expanded" class="mt-3 space-y-1.5 overflow-hidden">
                <div
                    v-for="item in items"
                    :key="item.id"
                    class="flex items-center justify-between bg-white/70 rounded-lg px-3 py-2 text-sm"
                >
                    <div>
                        <span class="font-medium text-gray-800">{{ item.product_name }}</span>
                        <span class="text-gray-400 mx-1">—</span>
                        <span class="text-gray-600">{{ item.variant_name }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="font-medium" :class="item.stock <= 0 ? 'text-red-600' : 'text-amber-600'">
                            Stok: {{ item.stock }}
                        </span>
                        <span v-if="item.expiry_date" class="text-gray-500">
                            Exp: {{ item.expiry_date }}
                        </span>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
