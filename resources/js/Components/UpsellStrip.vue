<script setup>
/**
 * Strip saran jual di atas total keranjang.
 *
 * Sengaja BUKAN pop-up. Kasir yang diberi pop-up tiap penjualan akan menutup
 * semuanya tanpa membaca, dan fiturnya mati diam-diam sambil tetap terlihat
 * "ada" saat didemokan. Jumlah barisnya sudah dibatasi di server
 * (`upsell.max_per_transaction`).
 *
 * Kedua tombolnya adalah KEPUTUSAN, bukan "ambil" dan "tutup": pelanggan
 * menerima, atau pelanggan menolak. Keduanya sama-sama menyelesaikan saran —
 * termasuk saat owner mewajibkannya. Yang tidak tersedia hanyalah melewatinya
 * tanpa menjawab ([BL-025]).
 */
import { computed } from 'vue';

const props = defineProps({
    suggestions: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    mandatory: { type: Boolean, default: false },
});

const emit = defineEmits(['accept', 'reject']);

const formatCurrency = (value) => 'Rp ' + Number(value).toLocaleString('id-ID');

// Tiap jenis menjawab pertanyaan berbeda, jadi tampilannya pun dibedakan —
// kasir harus bisa tahu sekilas ini "tambah topping" atau "barang mau habis".
const TONE = {
    attach: {
        ring: 'border-sky-200 bg-sky-50',
        chip: 'bg-sky-100 text-sky-700',
        button: 'bg-sky-600 hover:bg-sky-700',
        title: 'Tambah',
    },
    pressed_stock: {
        ring: 'border-amber-200 bg-amber-50',
        chip: 'bg-amber-100 text-amber-800',
        button: 'bg-amber-600 hover:bg-amber-700',
        title: 'Dorong',
    },
    upsize: {
        ring: 'border-violet-200 bg-violet-50',
        chip: 'bg-violet-100 text-violet-700',
        button: 'bg-violet-600 hover:bg-violet-700',
        title: 'Naik ukuran',
    },
};

const toneFor = (type) => TONE[type] ?? TONE.attach;

const visible = computed(() => props.suggestions ?? []);
</script>

<template>
    <div v-if="visible.length > 0" class="space-y-1.5">
        <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
            Saran untuk pelanggan
            <span v-if="mandatory" class="ml-1 rounded bg-amber-100 px-1 py-0.5 text-amber-800">Wajib dijawab</span>
        </p>

        <TransitionGroup
            enter-active-class="transition-all duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-100 ease-in absolute"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="suggestion in visible"
                :key="suggestion.key"
                :class="['flex items-center gap-2 rounded-lg border px-2.5 py-2', toneFor(suggestion.type).ring]"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <span
                            :class="['shrink-0 rounded px-1.5 py-0.5 text-[9px] font-bold uppercase', toneFor(suggestion.type).chip]"
                        >
                            {{ toneFor(suggestion.type).title }}
                        </span>
                        <span class="truncate text-xs font-semibold text-gray-800">{{ suggestion.label }}</span>
                    </div>
                    <p class="mt-0.5 truncate text-[10px] text-gray-500">
                        {{ suggestion.note }}
                        <span v-if="Number(suggestion.extra_amount) > 0" class="font-medium text-gray-600">
                            · +{{ formatCurrency(suggestion.extra_amount) }}
                        </span>
                    </p>
                </div>

                <button
                    type="button"
                    :disabled="disabled"
                    :class="[
                        'shrink-0 rounded-md px-2.5 py-1.5 text-[11px] font-semibold text-white transition disabled:opacity-40',
                        toneFor(suggestion.type).button,
                    ]"
                    @click="emit('accept', suggestion)"
                >
                    Diterima
                </button>

                <button
                    type="button"
                    :disabled="disabled"
                    class="shrink-0 rounded p-1 text-gray-400 transition hover:text-destructive disabled:opacity-40"
                    title="Ditolak pelanggan"
                    aria-label="Ditolak pelanggan"
                    @click="emit('reject', suggestion)"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
