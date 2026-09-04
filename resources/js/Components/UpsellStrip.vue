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
 *
 * Keputusan "diterima" TIDAK final. Yang sudah diambil tetap terlihat di bawah,
 * lengkap dengan tombol batalkannya ([BL-092]): salah pencet dan pelanggan yang
 * berubah pikiran terjadi tiap hari, dan penerimaan yang tidak bisa ditarik
 * memaksa kasir menghapus barisnya diam-diam — sementara laporannya tetap
 * mengaku upsell itu berhasil.
 */
import { computed } from 'vue';

const props = defineProps({
    suggestions: { type: Array, default: () => [] },
    accepted: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    mandatory: { type: Boolean, default: false },
});

const emit = defineEmits(['accept', 'reject', 'retract']);

const formatCurrency = (value) => 'Rp ' + Number(value).toLocaleString('id-ID');

/**
 * Empat jenis saran, empat nada — seluruhnya token sistem desain.
 *
 * Sebelumnya warnanya diambil langsung dari palet Tailwind (sky, violet, amber,
 * emerald), jadi ia tidak ikut tema mana pun dan tidak bisa diubah dari satu
 * tempat. Yang lebih penting: empat warna itu dipilih supaya BERBEDA, bukan
 * supaya BERARTI — dan warna yang cuma menandai kategori membuat kasir menghafal
 * kunci warna alih-alih membacanya.
 *
 * Sekarang nadanya mengikuti SEBERAPA BESAR TUNTUTANNYA pada kasir:
 *
 *   - `manual`  → primary. Permintaan pemilik toko; ini yang wajib diucapkan.
 *   - `pressed` → warning. Barang tertekan stok/kedaluwarsa — ada waktunya.
 *   - `upsize`  → success. Naik ukuran, tambahan omzet yang nyata.
 *   - `attach`  → muted.   Add-on, ajakan paling ringan dan paling sering.
 *
 * `attach` sengaja yang paling tenang meski paling sering muncul: strip ini
 * hanya memuat tiga baris sekaligus, jadi nada tenang tidak menyembunyikannya —
 * ia cuma berhenti berteriak sekeras permintaan pemilik.
 */
const TONE = {
    attach: {
        ring: 'border-border bg-muted/40',
        chip: 'bg-muted text-muted-foreground',
        title: 'Tambah',
    },
    pressed_stock: {
        ring: 'border-warning/30 bg-warning/5',
        chip: 'bg-warning/15 text-warning-foreground',
        title: 'Dorong',
    },
    upsize: {
        ring: 'border-success/30 bg-success/5',
        chip: 'bg-success/10 text-success',
        title: 'Naik ukuran',
    },
    // Aturan yang ditulis pemilik ([BL-074]). Tanpa baris ini ia menyamar jadi
    // add-on biasa, dan kasir kehilangan satu-satunya keterangan yang membuat
    // saran itu layak diucapkan: ini permintaan pemilik toko, bukan tebakan.
    manual: {
        ring: 'border-primary/30 bg-primary/5',
        chip: 'bg-primary/10 text-primary',
        title: 'Pilihan pemilik',
    },
};

const toneFor = (type) => TONE[type] ?? TONE.attach;

const visible = computed(() => props.suggestions ?? []);

const taken = computed(() => props.accepted ?? []);
</script>

<template>
    <div v-if="visible.length > 0 || taken.length > 0" class="space-y-1.5">
        <p v-if="visible.length > 0" class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
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

<!-- Satu warna untuk keempat jenis. Tombol ini adalah AKSI yang
                     sama persis di mana pun ia berdiri — "pelanggan menerima" —
                     dan mewarnainya per kategori membuat kasir mengira empat
                     tombol itu berbeda akibatnya. Kategorinya sudah dibawa
                     lencana dan bingkai kartunya. -->
                <button
                    type="button"
                    :disabled="disabled"
                    class="shrink-0 rounded-md bg-primary px-2.5 py-1.5 text-[11px] font-semibold text-primary-foreground transition hover:bg-primary/90 disabled:opacity-40"
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

        <!-- Yang sudah diambil. Tetap terlihat justru supaya bisa ditarik lagi
             ([BL-092]) — dan pembatalannya membereskan keranjang sekaligus
             catatannya, bukan salah satunya saja. -->
        <div v-if="taken.length > 0" class="space-y-1">
            <div
                v-for="suggestion in taken"
                :key="suggestion.key"
                class="flex items-center gap-2 rounded-lg border border-success/30 bg-success/5 px-2.5 py-1.5"
            >
                <svg class="h-3.5 w-3.5 shrink-0 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>

                <span class="min-w-0 flex-1 truncate text-[11px] text-foreground">
                    <span class="font-semibold">Diambil:</span> {{ suggestion.label }}
                    <span v-if="Number(suggestion.actual_extra_amount) > 0" class="text-success">
                        · +{{ formatCurrency(suggestion.actual_extra_amount) }}
                    </span>
                </span>

                <button
                    type="button"
                    :disabled="disabled"
                    class="shrink-0 rounded px-1.5 py-1 text-[11px] font-semibold text-success underline-offset-2 transition hover:underline disabled:opacity-40"
                    title="Batalkan — barangnya dikeluarkan lagi dari keranjang"
                    @click="emit('retract', suggestion)"
                >
                    Batalkan
                </button>
            </div>
        </div>
    </div>
</template>
