<script setup>
const props = defineProps({
    item: Object,
    index: Number,
});

const emit = defineEmits(['updateQty', 'remove']);

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const increment = () => {
    emit('updateQty', props.index, props.item.qty + 1);
};

const decrement = () => {
    if (props.item.qty > 1) {
        emit('updateQty', props.index, props.item.qty - 1);
    }
};

const remove = () => {
    emit('remove', props.index);
};

// Hitung subtotal item (unit_price + modifiers) × qty
const subtotal = () => {
    let price = Number(props.item.unit_price);
    if (props.item.modifiers && props.item.modifiers.length > 0) {
        price += props.item.modifiers.reduce((sum, m) => sum + Number(m.extra_price), 0);
    }
    return price * props.item.qty;
};
</script>

<template>
    <div class="bg-white rounded-lg border border-gray-200 p-3">
        <div class="flex items-start justify-between gap-2">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">{{ item.variant_name }}</p>
                <p class="text-xs text-gray-400">@ {{ formatCurrency(item.unit_price) }}</p>

                <!-- Modifiers -->
                <div v-if="item.modifiers && item.modifiers.length > 0" class="mt-1 space-y-0.5">
                    <p v-for="mod in item.modifiers" :key="mod.id" class="text-xs text-indigo-500">
                        + {{ mod.name }}
                        <span v-if="Number(mod.extra_price) > 0" class="text-gray-400">({{ formatCurrency(mod.extra_price) }})</span>
                    </p>
                </div>
            </div>

            <!-- Remove -->
            <button @click="remove" class="text-gray-300 hover:text-red-500 transition flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Qty + Subtotal -->
        <div class="flex items-center justify-between mt-3">
            <div class="flex items-center gap-2">
                <button
                    @click="decrement"
                    :disabled="item.qty <= 1"
                    class="w-7 h-7 flex items-center justify-center rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100 disabled:opacity-30 disabled:cursor-not-allowed transition"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                    </svg>
                </button>
                <span class="text-sm font-semibold text-gray-800 w-8 text-center">{{ item.qty }}</span>
                <button
                    @click="increment"
                    class="w-7 h-7 flex items-center justify-center rounded-md border border-gray-300 text-gray-600 hover:bg-gray-100 transition"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
            </div>
            <p class="text-sm font-semibold text-gray-800">{{ formatCurrency(subtotal()) }}</p>
        </div>
    </div>
</template>
