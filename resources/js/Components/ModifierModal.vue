<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    product: Object,
    /**
     * Baris keranjang yang sedang diubah, bila modal dibuka lewat tombol "Ubah"
     * dan bukan lewat kartu produk. Bentuknya `{ variant_id, modifiers, qty }`:
     * pilihan yang sudah ada dicentang lebih dulu, jadi kasir hanya menyentuh
     * yang berubah — bukan menyusun ulang pesanan dari nol.
     */
    initial: { type: Object, default: null },
});

const emit = defineEmits(['close', 'confirm']);

const selectedVariantId = ref(null);
const selectedModifiers = ref({}); // key: group_id → value: modifier_id (single) or [ids] (multiple)

const isEditing = computed(() => props.initial !== null);

const formatCurrency = (value) => {
    return 'Rp ' + Number(value).toLocaleString('id-ID');
};

/**
 * Kebalikan dari `flatModifiers`: daftar rata milik sebuah baris keranjang
 * dikembalikan ke bentuk "satu jawaban per grup" yang dipakai kontrolnya.
 */
const groupSelectionsFrom = (modifiers) => {
    const chosen = new Set((modifiers || []).map((m) => m.id));
    const result = {};

    for (const group of props.product?.modifier_groups || []) {
        const ids = (group.modifiers || []).filter((m) => chosen.has(m.id)).map((m) => m.id);
        if (ids.length === 0) {
            continue;
        }
        result[group.id] = group.is_multiple ? ids : ids[0];
    }

    return result;
};

const seedSelection = () => {
    if (!props.product) {
        return;
    }

    if (props.initial) {
        selectedVariantId.value = props.initial.variant_id ?? null;
        selectedModifiers.value = groupSelectionsFrom(props.initial.modifiers);

        return;
    }

    // Auto-select first variant if only one
    selectedVariantId.value = props.product.variants?.length === 1
        ? props.product.variants[0].id
        : null;
    selectedModifiers.value = {};
};

// Disemai saat modal DIBUKA, bukan hanya saat produknya berganti: mengubah dua
// baris produk yang sama berturut-turut tidak mengganti `product`, dan modal
// yang tidak menyemai ulang akan menampilkan centang milik baris sebelumnya.
watch([() => props.show, () => props.product], ([show]) => {
    if (show) {
        seedSelection();
    }
}, { immediate: true });

const selectedVariant = computed(() => {
    if (!props.product?.variants) return null;
    return props.product.variants.find(v => v.id === selectedVariantId.value);
});

// Flatten selected modifiers into array
const flatModifiers = computed(() => {
    const result = [];
    if (!props.product?.modifier_groups) return result;

    for (const group of props.product.modifier_groups) {
        const sel = selectedModifiers.value[group.id];
        if (!sel) continue;

        if (Array.isArray(sel)) {
            for (const mId of sel) {
                const mod = group.modifiers.find(m => m.id === mId);
                if (mod) result.push({ id: mod.id, name: mod.name, extra_price: Number(mod.extra_price) });
            }
        } else {
            const mod = group.modifiers.find(m => m.id === sel);
            if (mod) result.push({ id: mod.id, name: mod.name, extra_price: Number(mod.extra_price) });
        }
    }
    return result;
});

/**
 * Harga yang benar-benar akan ditagih ([BL-018]).
 *
 * `effective_price` dihitung SERVER dan ikut di props katalog. Menghitungnya
 * ulang di sini berarti rumus diskon — pendalaman seiring tanggal, jepitan
 * lantai margin, pembulatan ke atas — punya tempat kedua yang perlahan
 * menyimpang dari yang pertama.
 *
 * Fallback ke `price` bukan basa-basi: snapshot katalog offline yang dipanen
 * sebelum [BL-018] mendarat tidak punya kolom ini.
 */
const effectivePrice = (variant) => Number(variant?.effective_price ?? variant?.price ?? 0);

const isDiscounted = (variant) => effectivePrice(variant) < Number(variant?.price ?? 0);

// Total price preview
const previewPrice = computed(() => {
    if (!selectedVariant.value) return 0;
    let price = effectivePrice(selectedVariant.value);
    price += flatModifiers.value.reduce((sum, m) => sum + m.extra_price, 0);
    return price;
});

// Validasi: semua required groups harus dipilih
const isValid = computed(() => {
    if (!selectedVariantId.value) return false;
    if (!props.product?.modifier_groups) return true;

    for (const group of props.product.modifier_groups) {
        if (group.is_required) {
            const sel = selectedModifiers.value[group.id];
            if (!sel || (Array.isArray(sel) && sel.length === 0)) return false;
        }
    }
    return true;
});

const toggleMultiModifier = (groupId, modifierId) => {
    if (!selectedModifiers.value[groupId]) {
        selectedModifiers.value[groupId] = [];
    }
    const arr = selectedModifiers.value[groupId];
    const idx = arr.indexOf(modifierId);
    if (idx >= 0) {
        arr.splice(idx, 1);
    } else {
        arr.push(modifierId);
    }
};

const confirm = () => {
    if (!isValid.value) return;

    emit('confirm', {
        variant_id: selectedVariant.value.id,
        variant_name: `${props.product.name} - ${selectedVariant.value.name}`,
        unit_price: effectivePrice(selectedVariant.value),
        // Mengubah pesanan tidak mengubah jumlahnya: kasir menaikkan qty di
        // keranjang, dan menyetelnya balik ke 1 di sini akan menghapus
        // pekerjaan itu diam-diam.
        qty: props.initial?.qty ?? 1,
        modifiers: flatModifiers.value,
    });

    emit('close');
};

const close = () => {
    emit('close');
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show && product" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/50" @click="close" />

                <!-- Modal -->
                <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[85vh] overflow-y-auto">
                    <!-- Header -->
                    <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                        <div>
                            <p v-if="isEditing" class="text-xs font-medium text-primary">Ubah pesanan</p>
                            <h3 class="text-lg font-semibold text-gray-800">{{ product.name }}</h3>
                        </div>
                        <button @click="close" class="text-gray-400 hover:text-gray-600">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-5">
                        <!-- Pilih Variant -->
                        <div>
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Pilih Varian <span class="text-red-500">*</span></h4>
                            <div class="space-y-2">
                                <label
                                    v-for="variant in product.variants"
                                    :key="variant.id"
                                    :class="[
                                        'flex items-center justify-between p-3 rounded-lg border-2 cursor-pointer transition',
                                        variant.stock <= 0
                                            ? 'border-gray-100 opacity-50 cursor-not-allowed'
                                            : selectedVariantId === variant.id
                                                ? 'border-primary bg-primary/10'
                                                : 'border-gray-200 hover:border-primary/30'
                                    ]"
                                >
                                    <div class="flex items-center gap-3">
                                        <input
                                            type="radio"
                                            :value="variant.id"
                                            v-model="selectedVariantId"
                                            :disabled="variant.stock <= 0"
                                            class="text-primary focus:ring-ring"
                                        />
                                        <div>
                                            <span class="text-sm font-medium text-gray-800">{{ variant.name }}</span>
                                            <span v-if="variant.stock <= 0" class="ml-2 text-xs text-red-500 font-medium">Habis</span>
                                            <span v-else class="ml-2 text-xs text-gray-400">Stok: {{ variant.stock }}</span>
                                        </div>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-700">
                                        <!-- Harga coret DAN harga bayar: potongan yang
                                             tidak terlihat tidak pernah jadi alasan
                                             orang membeli. -->
                                        <template v-if="isDiscounted(variant)">
                                            <span class="text-xs font-normal text-gray-400 line-through mr-1">{{ formatCurrency(variant.price) }}</span>
                                            <span class="text-success">{{ formatCurrency(effectivePrice(variant)) }}</span>
                                        </template>
                                        <template v-else>{{ formatCurrency(variant.price) }}</template>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- Modifier Groups -->
                        <div v-for="group in product.modifier_groups" :key="group.id">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2">
                                {{ group.name }}
                                <span v-if="group.is_required" class="text-red-500">*</span>
                                <span v-else class="text-xs text-gray-400 font-normal">(opsional)</span>
                                <span v-if="group.is_multiple" class="text-xs text-gray-400 font-normal ml-1">(boleh lebih dari 1)</span>
                            </h4>
                            <div class="space-y-2">
                                <!-- Single select (radio) -->
                                <template v-if="!group.is_multiple">
                                    <label
                                        v-for="mod in group.modifiers"
                                        :key="mod.id"
                                        :class="[
                                            'flex items-center justify-between p-3 rounded-lg border-2 cursor-pointer transition',
                                            selectedModifiers[group.id] === mod.id
                                                ? 'border-primary bg-primary/10'
                                                : 'border-gray-200 hover:border-primary/30'
                                        ]"
                                    >
                                        <div class="flex items-center gap-3">
                                            <input
                                                type="radio"
                                                :value="mod.id"
                                                v-model="selectedModifiers[group.id]"
                                                class="text-primary focus:ring-ring"
                                            />
                                            <span class="text-sm text-gray-700">{{ mod.name }}</span>
                                        </div>
                                        <span v-if="Number(mod.extra_price) > 0" class="text-sm text-gray-500">
                                            +{{ formatCurrency(mod.extra_price) }}
                                        </span>
                                    </label>
                                </template>

                                <!-- Multiple select (checkbox) -->
                                <template v-else>
                                    <label
                                        v-for="mod in group.modifiers"
                                        :key="mod.id"
                                        :class="[
                                            'flex items-center justify-between p-3 rounded-lg border-2 cursor-pointer transition',
                                            (selectedModifiers[group.id] || []).includes(mod.id)
                                                ? 'border-primary bg-primary/10'
                                                : 'border-gray-200 hover:border-primary/30'
                                        ]"
                                        @click.prevent="toggleMultiModifier(group.id, mod.id)"
                                    >
                                        <div class="flex items-center gap-3">
                                            <input
                                                type="checkbox"
                                                :checked="(selectedModifiers[group.id] || []).includes(mod.id)"
                                                class="text-primary focus:ring-ring rounded pointer-events-none"
                                            />
                                            <span class="text-sm text-gray-700">{{ mod.name }}</span>
                                        </div>
                                        <span v-if="Number(mod.extra_price) > 0" class="text-sm text-gray-500">
                                            +{{ formatCurrency(mod.extra_price) }}
                                        </span>
                                    </label>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="sticky bottom-0 bg-white border-t border-gray-200 px-6 py-4">
                        <div v-if="selectedVariant" class="flex items-center justify-between mb-3">
                            <span class="text-sm text-gray-500">Harga per item</span>
                            <span class="text-lg font-bold text-primary">{{ formatCurrency(previewPrice) }}</span>
                        </div>
                        <div class="flex gap-3">
                            <button
                                @click="close"
                                class="flex-1 py-2.5 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition"
                            >
                                Batal
                            </button>
                            <button
                                @click="confirm"
                                :disabled="!isValid"
                                class="flex-1 py-2.5 bg-primary text-primary-foreground font-semibold rounded-lg hover:bg-primary/90 transition disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                {{ isEditing ? 'Simpan Perubahan' : 'Tambah ke Keranjang' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
