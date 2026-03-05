<script setup>
const props = defineProps({
    product: Object,
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

// Cek apakah ada variant yg punya stok > 0
const hasStock = () => {
    return props.product.variants?.some(v => v.stock > 0);
};

// Price range
const priceRange = () => {
    const prices = (props.product.variants || []).map(v => Number(v.price));
    if (prices.length === 0) return '-';
    const min = Math.min(...prices);
    const max = Math.max(...prices);
    return min === max ? formatCurrency(min) : `${formatCurrency(min)} - ${formatCurrency(max)}`;
};

const handleClick = () => {
    if (!hasStock()) return;
    emit('select', props.product);
};
</script>

<template>
    <button
        @click="handleClick"
        :disabled="!hasStock()"
        :class="[
            'relative bg-white rounded-xl border-2 p-3 text-left transition-all duration-150 w-full',
            hasStock()
                ? 'border-gray-200 hover:border-indigo-400 hover:shadow-md cursor-pointer active:scale-[0.97]'
                : 'border-gray-100 opacity-60 cursor-not-allowed'
        ]"
    >
        <!-- Badge Habis -->
        <span v-if="!hasStock()"
              class="absolute top-2 right-2 inline-flex px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-700">
            Habis
        </span>

        <!-- Product Image -->
        <div class="w-full aspect-square bg-gray-100 rounded-lg mb-2 overflow-hidden flex items-center justify-center">
            <img v-if="product.image"
                 :src="`/storage/${product.image}`"
                 :alt="product.name"
                 class="w-full h-full object-cover" />
            <svg v-else class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
        </div>

        <!-- Info -->
        <div>
            <p class="text-sm font-semibold text-gray-800 leading-tight truncate">{{ product.name }}</p>
            <p v-if="product.category" class="text-xs text-gray-400 mt-0.5">{{ product.category.name }}</p>
            <p class="text-xs font-medium text-indigo-600 mt-1">{{ priceRange() }}</p>
        </div>

        <!-- Variant count badge -->
        <span v-if="product.variants && product.variants.length > 1"
              class="absolute top-2 left-2 inline-flex px-1.5 py-0.5 rounded text-[10px] font-medium bg-indigo-100 text-indigo-700">
            {{ product.variants.length }} varian
        </span>
    </button>
</template>
