<script setup>
import { ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import Button from '@/Components/Button.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Varian' },
    // Inertia useForm instance with: name, sku, price, cost_price, stock, expiry_date (+ errors, processing)
    form: { type: Object, required: true },
    submitLabel: { type: String, default: 'Simpan' },
});

const emit = defineEmits(['submit', 'close']);

const priceDisplay = ref('');
const costDisplay = ref('');

const formatNumber = (value) => {
    const num = Number(String(value).replace(/\D/g, ''));
    if (!num) {
        return '';
    }
    return num.toLocaleString('id-ID');
};

// Sync the formatted display whenever the modal opens with the current form values.
watch(() => props.show, (val) => {
    if (val) {
        priceDisplay.value = Number(props.form.price) > 0 ? formatNumber(props.form.price) : '';
        costDisplay.value = Number(props.form.cost_price) > 0 ? formatNumber(props.form.cost_price) : '';
    }
});

const onPriceInput = (e) => {
    const num = Number(e.target.value.replace(/\D/g, '')) || 0;
    props.form.price = num;
    priceDisplay.value = num > 0 ? formatNumber(num) : '';
};
const onPriceFocus = (e) => {
    const num = Number(props.form.price) || 0;
    e.target.value = num > 0 ? String(num) : '';
    e.target.select();
};
const onPriceBlur = (e) => {
    const num = Number(props.form.price) || 0;
    priceDisplay.value = num > 0 ? formatNumber(num) : '';
    e.target.value = priceDisplay.value;
};

const onCostInput = (e) => {
    const num = Number(e.target.value.replace(/\D/g, '')) || 0;
    props.form.cost_price = num;
    costDisplay.value = num > 0 ? formatNumber(num) : '';
};
const onCostFocus = (e) => {
    const num = Number(props.form.cost_price) || 0;
    e.target.value = num > 0 ? String(num) : '';
    e.target.select();
};
const onCostBlur = (e) => {
    const num = Number(props.form.cost_price) || 0;
    costDisplay.value = num > 0 ? formatNumber(num) : '';
    e.target.value = costDisplay.value;
};

const inputClass =
    'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent';
</script>

<template>
    <Modal :show="show" :title="title" max-width="max-w-2xl" @close="emit('close')">
        <form @submit.prevent="emit('submit')" class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="col-span-2 md:col-span-1">
                <label class="block text-xs font-medium text-gray-600 mb-1">Nama Varian *</label>
                <input v-model="form.name" type="text" placeholder="Contoh: Default" :class="inputClass" />
                <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">SKU</label>
                <input v-model="form.sku" type="text" placeholder="Opsional" :class="inputClass" />
                <p v-if="form.errors.sku" class="mt-1 text-xs text-red-600">{{ form.errors.sku }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Harga Jual *</label>
                <input
                    type="text"
                    inputmode="numeric"
                    :value="priceDisplay"
                    @input="onPriceInput"
                    @focus="onPriceFocus"
                    @blur="onPriceBlur"
                    placeholder="0"
                    :class="inputClass"
                />
                <p v-if="form.errors.price" class="mt-1 text-xs text-red-600">{{ form.errors.price }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Harga Modal *</label>
                <input
                    type="text"
                    inputmode="numeric"
                    :value="costDisplay"
                    @input="onCostInput"
                    @focus="onCostFocus"
                    @blur="onCostBlur"
                    placeholder="0"
                    :class="inputClass"
                />
                <p v-if="form.errors.cost_price" class="mt-1 text-xs text-red-600">{{ form.errors.cost_price }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Stok *</label>
                <input v-model="form.stock" type="number" min="0" placeholder="0" :class="inputClass" />
                <p v-if="form.errors.stock" class="mt-1 text-xs text-red-600">{{ form.errors.stock }}</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tanggal Expired</label>
                <input v-model="form.expiry_date" type="date" :class="inputClass" />
                <p v-if="form.errors.expiry_date" class="mt-1 text-xs text-red-600">{{ form.errors.expiry_date }}</p>
            </div>
        </form>

        <template #footer>
            <div class="flex justify-end gap-3">
                <Button variant="secondary" type="button" @click="emit('close')">Batal</Button>
                <Button variant="primary" type="button" :loading="form.processing" @click="emit('submit')">
                    {{ form.processing ? 'Menyimpan...' : submitLabel }}
                </Button>
            </div>
        </template>
    </Modal>
</template>
