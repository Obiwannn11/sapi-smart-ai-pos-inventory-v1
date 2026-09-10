<script setup>
import { computed, ref, watch } from 'vue';

/**
 * Gambar produk dengan cadangan huruf pertama.
 *
 * Ikon "gunung" abu-abu yang dulu dipakai membuat semua produk tak bergambar
 * terlihat identik — di kisi POS itu berarti sederet kotak kembar yang harus
 * dibaca teksnya satu per satu. Huruf pertama tiap kata memberi produk bentuk
 * yang bisa dikenali sekilas, di atas satu nada hijau merek yang sama untuk
 * semua kartu.
 *
 * Komponen ini mengisi penuh kotak induknya; induklah yang menentukan ukuran
 * dan sudutnya.
 */
const props = defineProps({
    src: { type: String, default: null },
    name: { type: String, default: '' },
});

const failed = ref(false);

// Sumber baru berhak dicoba lagi — tanpa ini satu 404 mengunci placeholder
// untuk selamanya walau produknya sudah diberi gambar.
watch(() => props.src, () => {
    failed.value = false;
});

// Huruf pertama SETIAP kata: "Cafe Latte" jadi "CL", bukan "C" — di kisi POS
// deretan produk yang berawalan sama ("Cafe Latte", "Croissant") kalau tidak
// begitu akan tampil kembar. Dipotong di tiga huruf karena lebih dari itu tak
// lagi terbaca sekilas di kartu selebar 170 px, dan yang dicari mata memang
// bentuknya, bukan namanya yang utuh.
const initials = computed(() => {
    const letters = (props.name || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 3)
        .map((word) => word.charAt(0).toUpperCase())
        .join('');

    return letters || '?';
});

// Kotaknya tetap sama besar, jadi hurufnyalah yang mengalah agar tiga inisial
// tidak melimpah keluar.
const fontScale = computed(() => [42, 30, 22][initials.value.length - 1] ?? 22);

// Satu nada hijau merek untuk semua produk. Warna acak per nama dulu dipakai
// agar kartu mudah dibedakan, tapi di kisi POS hasilnya jadi tambal sulam
// enam warna yang bertabrakan dengan identitas aplikasi; inisiallah yang
// membedakan produk, bukan warnanya.
const tone = 'bg-primary/10 text-primary';
</script>

<template>
    <div class="w-full h-full overflow-hidden" style="container-type: inline-size">
        <img
            v-if="src && !failed"
            :src="src"
            :alt="name"
            loading="lazy"
            class="w-full h-full object-cover"
            @error="failed = true"
        />
        <div
            v-else
            :class="['w-full h-full flex items-center justify-center font-bold select-none', tone]"
        >
            <!-- Satuan cqw membuat hurufnya ikut skala kotak induk, jadi
                 ukurannya seragam di kartu POS maupun kisi produk. text-2xl
                 adalah cadangan bila peramban tak mengenal container query. -->
            <span class="text-2xl tracking-tight" :style="{ fontSize: `${fontScale}cqw`, lineHeight: 1 }">{{ initials }}</span>
        </div>
    </div>
</template>
