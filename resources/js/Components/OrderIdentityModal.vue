<script setup>
/**
 * Satu modal identitas untuk KEDUA jalur kasir — bayar langsung dan tunda
 * bayar ([BL-026]).
 *
 * Sebelumnya identitas hanya bisa diberikan lewat modal "Tunda Bayar", padahal
 * justru pesanan bayar-langsung yang perlu dipanggil saat siap. Dua modal untuk
 * hal yang sama akan berbeda isinya cepat atau lambat, jadi jalurnya disatukan
 * di sini dan bentuk inputnya yang mengikuti mode.
 *
 * Mode `code` sengaja TIDAK punya tampilan di sini: nomornya dialokasikan
 * server, tak ada yang perlu diketik, dan modal yang hanya berisi tombol
 * "Lanjut" adalah satu ketukan tanpa guna. Pemanggil melewatinya.
 *
 * Identitas selalu boleh dilewati. Ini alat bantu operasional, bukan syarat
 * sah penjualan — kasir yang menghadapi antrean panjang harus tetap bisa
 * menyelesaikan transaksi.
 */
import { ref, computed, watch, nextTick } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    /** 'name' | 'table' */
    mode: { type: String, default: 'name' },
    /** Label tombol utama — beda antara bayar langsung dan tunda bayar. */
    confirmLabel: { type: String, default: 'Lanjut' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);

const customerName = ref('');
const tableNumber = ref('');
const nameInput = ref(null);

const isTableMode = computed(() => props.mode === 'table');

const title = computed(() => (isTableMode.value ? 'Nomor Meja' : 'Nama Pelanggan'));

const hint = computed(() => (isTableMode.value
    ? 'Nomor meja untuk pesanan ini. Boleh dilewati.'
    : 'Nama pelanggan untuk pesanan ini. Boleh dilewati.'));

// Isian direset tiap kali modal dibuka: identitas milik satu pesanan, dan sisa
// ketikan pesanan sebelumnya adalah cara termudah menempelkan nama orang lain
// pada tagihan yang salah.
watch(() => props.show, async (visible) => {
    if (!visible) return;

    customerName.value = '';
    tableNumber.value = '';

    await nextTick();
    if (!isTableMode.value) nameInput.value?.focus();
});

const pressDigit = (digit) => {
    if (tableNumber.value.length >= 10) return;
    tableNumber.value += digit;
};

const backspace = () => {
    tableNumber.value = tableNumber.value.slice(0, -1);
};

/** @param {boolean} skipped  true bila kasir menekan "Lewati" */
const submit = (skipped = false) => {
    if (props.disabled) return;

    emit('confirm', {
        customer_name: skipped || isTableMode.value ? null : (customerName.value.trim() || null),
        table_number: skipped || !isTableMode.value ? null : (tableNumber.value.trim() || null),
    });
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-[110] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="emit('close')" />

                <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm p-6 space-y-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-800">{{ title }}</h3>
                        <p class="text-sm text-gray-500 mt-0.5">{{ hint }}</p>
                    </div>

                    <!-- Nama: teks bebas -->
                    <input
                        v-if="!isTableMode"
                        ref="nameInput"
                        v-model="customerName"
                        type="text"
                        placeholder="Contoh: Budi"
                        maxlength="100"
                        class="w-full px-3 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-ring focus:border-ring"
                        @keydown.enter="submit()"
                        @keydown.esc="emit('close')"
                    />

                    <!-- Meja: papan angka. Layar kasir umumnya sentuh, dan
                         memunculkan keyboard sistem untuk empat digit lebih
                         lambat daripada tombol besar yang selalu di tempatnya. -->
                    <div v-else class="space-y-3">
                        <div
                            class="w-full px-3 py-3 border border-gray-300 rounded-lg text-center text-2xl font-bold tracking-widest text-gray-800 min-h-[3.25rem]"
                            aria-live="polite"
                        >
                            <span v-if="tableNumber">{{ tableNumber }}</span>
                            <span v-else class="text-gray-300">—</span>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="digit in ['1', '2', '3', '4', '5', '6', '7', '8', '9']"
                                :key="digit"
                                type="button"
                                @click="pressDigit(digit)"
                                class="py-3 bg-gray-100 hover:bg-gray-200 rounded-lg text-lg font-semibold text-gray-800 transition"
                            >
                                {{ digit }}
                            </button>
                            <button
                                type="button"
                                @click="tableNumber = ''"
                                class="py-3 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium text-gray-600 transition"
                            >
                                Hapus
                            </button>
                            <button
                                type="button"
                                @click="pressDigit('0')"
                                class="py-3 bg-gray-100 hover:bg-gray-200 rounded-lg text-lg font-semibold text-gray-800 transition"
                            >
                                0
                            </button>
                            <button
                                type="button"
                                @click="backspace"
                                class="py-3 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium text-gray-600 transition"
                                aria-label="Hapus satu angka"
                            >
                                ←
                            </button>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-1">
                        <button
                            type="button"
                            @click="submit(true)"
                            :disabled="disabled"
                            class="flex-1 py-2.5 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition text-sm disabled:opacity-40"
                        >
                            Lewati
                        </button>
                        <button
                            type="button"
                            @click="submit()"
                            :disabled="disabled"
                            class="flex-1 py-2.5 bg-primary text-primary-foreground font-semibold rounded-lg hover:bg-primary/90 transition text-sm disabled:opacity-40"
                        >
                            {{ confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
