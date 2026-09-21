<script setup>
import { computed, ref, watch } from 'vue';

/**
 * Lencana merek toko — logo usaha, dengan cadangan huruf pertama namanya.
 *
 * Ia ada sebagai komponen tersendiri karena pertanyaan yang dijawabnya ("saya
 * sedang di toko mana") muncul di tiga tempat yang tak saling kenal: kepala
 * sidebar owner, topbar kasir, dan kepala struk. Sebelum ini masing-masing
 * menghitung inisialnya sendiri, dengan huruf cadangan yang berbeda pula —
 * `S` di satu tempat, `U` di tempat lain. Satu jawaban, satu tempat.
 *
 * Kotaknya berlatar warna merek SELAMA belum ada logo. Setelah ada, latarnya
 * hilang: logo dengan sisi transparan di atas kotak hijau akan terbaca sebagai
 * stiker yang ditempel, bukan sebagai identitas toko. Ukuran dan sudutnya
 * ditentukan induk lewat kelas, bukan oleh komponen ini.
 */
const props = defineProps({
    src: { type: String, default: null },
    name: { type: String, default: '' },
});

const failed = ref(false);

// Sumber baru berhak dicoba lagi — tanpa ini satu 404 mengunci inisial untuk
// selamanya walau logonya sudah dipasang. Sama seperti ProductImage.vue.
watch(() => props.src, () => {
    failed.value = false;
});

const showLogo = computed(() => !!props.src && !failed.value);

// Huruf pertama nama TOKO, bukan nama produk. Cadangannya `S` (SAPI POS),
// yang juga cadangan nama tokonya sendiri di kedua shell.
const initial = computed(() => (props.name || '').trim().charAt(0).toUpperCase() || 'S');
</script>

<template>
    <div
        class="flex items-center justify-center overflow-hidden flex-shrink-0"
        :class="showLogo ? 'bg-transparent' : 'bg-primary'"
        aria-hidden="true"
    >
        <img
            v-if="showLogo"
            :src="src"
            :alt="name"
            class="w-full h-full object-contain"
            @error="failed = true"
        />
        <span
            v-else
            class="text-primary-foreground font-bold tracking-tight select-none leading-none"
        >{{ initial }}</span>
    </div>
</template>
