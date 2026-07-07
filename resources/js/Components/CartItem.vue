<script setup>
import { ref, nextTick } from 'vue';

const props = defineProps({
    item: Object,
    index: Number,
});

const emit = defineEmits(['updateQty', 'remove', 'updateNotes']);

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

const showNotes = ref(!!props.item.notes);

const increment = () => {
    emit('updateQty', props.index, props.item.qty + 1);
};

const decrement = () => {
    if (props.item.qty > 1) {
        emit('updateQty', props.index, props.item.qty - 1);
    }
};

// --- Direct qty input (double-click / long-press) ---
const isEditingQty = ref(false);
const qtyInput = ref(null);
const qtyDraft = ref('');
let longPressTimer = null;

const startEditQty = () => {
    qtyDraft.value = String(props.item.qty);
    isEditingQty.value = true;
    nextTick(() => {
        qtyInput.value?.focus();
        qtyInput.value?.select();
    });
};

const commitQty = () => {
    if (!isEditingQty.value) return;
    isEditingQty.value = false;
    const parsed = parseInt(qtyDraft.value, 10);
    if (!Number.isNaN(parsed) && parsed >= 1 && parsed !== props.item.qty) {
        emit('updateQty', props.index, parsed);
    }
};

const cancelEditQty = () => {
    isEditingQty.value = false;
};

const startLongPress = () => {
    longPressTimer = setTimeout(startEditQty, 450);
};

const cancelLongPress = () => {
    if (longPressTimer) {
        clearTimeout(longPressTimer);
        longPressTimer = null;
    }
};

const remove = () => {
    emit('remove', props.index);
};

const toggleNotes = () => {
    showNotes.value = !showNotes.value;
    if (!showNotes.value) {
        emit('updateNotes', props.index, '');
    }
};

const onNotesChange = (e) => {
    emit('updateNotes', props.index, e.target.value);
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
                <p class="text-xs text-gray-400">@ {{ formatCurrency(item.unit_price) }} x {{ item.qty }}</p>

                <!-- Modifiers -->
                <div v-if="item.modifiers && item.modifiers.length > 0" class="mt-1 space-y-0.5">
                    <p v-for="mod in item.modifiers" :key="mod.id" class="text-xs text-primary">
                        + {{ mod.name }}
                        <span v-if="Number(mod.extra_price) > 0" class="text-gray-400">({{ formatCurrency(mod.extra_price) }})</span>
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-1 flex-shrink-0">
                <!-- Toggle notes -->
                <button @click="toggleNotes" class="text-gray-300 hover:text-primary transition" title="Catatan">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                </button>
                <!-- Remove -->
                <button @click="remove" class="text-gray-300 hover:text-red-500 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Notes input -->
        <div v-if="showNotes" class="mt-2">
            <input
                :value="item.notes || ''"
                @input="onNotesChange"
                type="text"
                placeholder="Catatan: ekstra susu, tanpa gula, dll"
                class="w-full px-2 py-1.5 text-xs border border-gray-200 rounded-md focus:ring-1 focus:ring-ring focus:border-ring bg-gray-50"
            />
        </div>

        <!-- Item notes display (if has notes but input hidden) -->
        <p v-if="!showNotes && item.notes" class="text-xs text-amber-600 mt-1 italic">
            📝 {{ item.notes }}
        </p>

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
                <input
                    v-if="isEditingQty"
                    ref="qtyInput"
                    v-model="qtyDraft"
                    type="number"
                    min="1"
                    inputmode="numeric"
                    @blur="commitQty"
                    @keydown.enter.prevent="commitQty"
                    @keydown.esc.prevent="cancelEditQty"
                    class="w-12 h-7 px-1 text-sm font-semibold text-gray-800 text-center border border-primary rounded-md focus:ring-1 focus:ring-ring focus:border-ring [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                />
                <span
                    v-else
                    @dblclick="startEditQty"
                    @touchstart.passive="startLongPress"
                    @touchend="cancelLongPress"
                    @touchmove="cancelLongPress"
                    @touchcancel="cancelLongPress"
                    title="Klik 2x atau tahan untuk isi jumlah"
                    class="text-sm font-semibold text-gray-800 w-8 text-center cursor-pointer select-none rounded hover:bg-gray-100"
                >{{ item.qty }}</span>
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
