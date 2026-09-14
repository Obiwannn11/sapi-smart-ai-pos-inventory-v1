<script setup>
import { computed, ref, nextTick } from 'vue';
import TapTooltip from '@/Components/TapTooltip.vue';

const props = defineProps({
    item: Object,
    index: Number,
    /**
     * Apakah baris ini punya sesuatu untuk diubah — varian lain atau grup
     * modifier. Produk polos tanpa pilihan tidak diberi tombol Ubah: modal yang
     * terbuka hanya untuk memperlihatkan satu-satunya pilihan adalah jalan
     * buntu, bukan fitur.
     */
    canEdit: { type: Boolean, default: false },
    /**
     * Harga khusus di bawah lantai untung ([BL-018]) — hanya owner. Kasir tidak
     * diberi tombolnya sama sekali, bukan tombol yang menolak saat ditekan.
     */
    canSetSpecialPrice: { type: Boolean, default: false },
    /**
     * Berapa saran jual yang masih menunggu keputusan dan berasal dari baris
     * ini. Kartunya hanya memuat satu saran sekaligus, jadi tanpa angka ini
     * kasir tidak punya cara tahu masih ada yang mengantre di belakangnya
     * selain menghitung titik pager.
     */
    upsellCount: { type: Number, default: 0 },
    /**
     * Apakah saran yang SEDANG tergambar di kartu berasal dari baris ini.
     * Inilah yang menjawab "tawaran ini untuk barang yang mana" — dijawab
     * dengan menunjuk, bukan dengan memindahkan tombolnya ke sini.
     */
    upsellActive: { type: Boolean, default: false },
    /**
     * Qty varian ini di keranjang melebihi stok yang belum kedaluwarsa, jadi
     * sebagian yang terjual adalah barang basi ([BL-108]). Dihitung POS, bukan
     * di sini: baris tidak tahu isi baris lain dengan varian yang sama.
     */
    sellsExpired: { type: Boolean, default: false },
});

const emit = defineEmits(['updateQty', 'remove', 'updateNotes', 'edit', 'specialPrice', 'clearSpecialPrice']);

/**
 * Aksi baris keranjang harus sudah terlihat bisa ditekan SEBELUM disentuh.
 *
 * Kasir bekerja di tablet: tidak ada kursor yang bisa melayang di atas ikon,
 * jadi bentuk tombol yang baru muncul saat hover — sebelumnya hanya `hover:bg-gray-100`
 * di atas ikon telanjang — bagi kasir tidak pernah ada sama sekali. Karena itu
 * setiap aksi punya kotak, garis tepi, dan latar sejak diam; hover dan tekan
 * hanya menambah umpan balik, bukan yang pertama memperkenalkannya sebagai tombol.
 *
 * `pointer-coarse:` membesarkan kotaknya ke 44px hanya di perangkat sentuh,
 * jadi tampilan desktop tidak ikut menggemuk.
 */
const ACTION_BUTTON = 'h-9 w-9 pointer-coarse:h-11 pointer-coarse:w-11 flex items-center justify-center rounded-lg border transition active:scale-90';

const ACTION_ICON = 'h-4 w-4 pointer-coarse:h-5 pointer-coarse:w-5';

/** Kotak diam yang netral — dipakai semua aksi yang belum menyala. */
const ACTION_IDLE = 'border-gray-200 bg-gray-50 text-gray-500';

/**
 * Tombol +/- ikut membesar di layar sentuh dengan alasan yang sama, dan karena
 * berdiri sebaris dengan aksi di atas: kotak 28px di sebelah kotak 44px terbaca
 * sebagai tombol kelas dua, padahal justru inilah yang paling sering ditekan.
 */
const QTY_BUTTON = 'h-8 w-8 pointer-coarse:h-10 pointer-coarse:w-10 flex items-center justify-center rounded-md border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-100 active:scale-90 active:bg-gray-200';

const QTY_ICON = 'h-3 w-3 pointer-coarse:h-4 pointer-coarse:w-4';

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

const edit = () => {
    emit('edit', props.index);
};

const specialPrice = () => {
    emit('specialPrice', props.index);
};

const clearSpecialPrice = () => {
    emit('clearSpecialPrice', props.index);
};

const specialPriceLabel = computed(() =>
    props.item.override_unit_price ? 'Ubah harga khusus' : 'Harga khusus'
);

// Menutup catatan juga MENGHAPUS isinya, jadi labelnya mengatakan begitu saat
// memang ada yang hilang.
const notesActionLabel = computed(() => {
    if (props.item.notes) {
        return 'Hapus catatan';
    }

    return showNotes.value ? 'Tutup catatan' : 'Tambah catatan';
});

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
    <!-- Baris yang sedang ditunjuk kartu saran menyala dengan garis tepi kiri,
         bukan dengan warna latar sendirian: latar berwarna sudah dipakai
         keadaan lain di layar ini, sedangkan tepi kiri membaca sebagai "yang
         ini, yang sedang dibicarakan" tanpa menambah satu warna baru pun. -->
    <div
        :class="[
            'rounded-lg border p-3 transition-colors',
            upsellActive
                ? 'border-primary/40 bg-primary/5 shadow-[inset_3px_0_0_0_var(--color-primary)]'
                : 'bg-white border-gray-200',
        ]"
    >
        <div class="flex items-start justify-between gap-2">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5">
                    <p class="min-w-0 flex-1 truncate text-sm font-medium text-gray-800">{{ item.variant_name }}</p>
                    <span
                        v-if="upsellCount > 0"
                        :class="[
                            'shrink-0 rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide',
                            upsellActive ? 'bg-primary/15 text-primary' : 'bg-gray-100 text-gray-500',
                        ]"
                        :title="`${upsellCount} saran untuk item ini belum dijawab`"
                    >
                        {{ upsellCount }} saran
                    </span>
                </div>
                <p class="text-xs text-gray-400">@ {{ formatCurrency(item.unit_price) }} x {{ item.qty }}</p>

                <!-- Modifiers -->
                <div v-if="item.modifiers && item.modifiers.length > 0" class="mt-1 space-y-0.5">
                    <p v-for="mod in item.modifiers" :key="mod.id" class="text-xs text-primary">
                        + {{ mod.name }}
                        <span v-if="Number(mod.extra_price) > 0" class="text-gray-400">({{ formatCurrency(mod.extra_price) }})</span>
                    </p>
                </div>
            </div>

            <!-- Aksi per baris. Tiga ikon yang tugasnya berbeda-beda, jadi
                 masing-masing dapat bentuknya sendiri: kertas bercatat untuk
                 catatan, pensil untuk mengubah pesanan, tong sampah untuk
                 menghapus. Sebelumnya catatan memakai ikon pensil dan hapus
                 memakai tanda silang — dua-duanya terbaca sebagai hal lain. -->
            <div class="flex items-center gap-1 flex-shrink-0">
                <!-- Catatan -->
                <TapTooltip :label="notesActionLabel">
                    <button
                        @click="toggleNotes"
                        type="button"
                        :aria-pressed="showNotes"
                        :aria-label="notesActionLabel"
                        :class="[
                            ACTION_BUTTON,
                            showNotes || item.notes
                                ? 'border-primary/40 bg-primary/10 text-primary hover:bg-primary/20'
                                : `${ACTION_IDLE} hover:border-primary/40 hover:bg-primary/10 hover:text-primary`
                        ]"
                    >
                        <svg :class="ACTION_ICON" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h4m5 5H6a2 2 0 01-2-2V5a2 2 0 012-2h7.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </button>
                </TapTooltip>

                <!-- Harga khusus ([BL-018]). Ikut baris ikon ini, bukan baris
                     sendiri di kaki kartu: keranjang kasir dinilai dari berapa
                     banyak baris yang muat di layar, dan tombol yang jarang
                     dipakai tidak boleh memungut satu baris dari SETIAP item
                     hanya untuk menunggu ditekan. Kalau harganya sedang
                     berlaku, kotaknya menyala amber dan alasannya muncul di
                     bawah — baris tambahan itu hanya dibayar oleh item yang
                     memang punya harga khusus. -->
                <TapTooltip v-if="canSetSpecialPrice" :label="specialPriceLabel">
                    <button
                        @click="specialPrice"
                        type="button"
                        :aria-label="specialPriceLabel"
                        :class="[
                            ACTION_BUTTON,
                            item.override_unit_price
                                ? 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100'
                                : `${ACTION_IDLE} hover:border-amber-300 hover:bg-amber-50 hover:text-amber-700`
                        ]"
                    >
                        <svg :class="ACTION_ICON" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-5 5a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 10V5a2 2 0 012-2z" />
                        </svg>
                    </button>
                </TapTooltip>

                <!-- Ubah pesanan -->
                <TapTooltip v-if="canEdit" label="Ubah pesanan">
                    <button
                        @click="edit"
                        type="button"
                        aria-label="Ubah varian dan pilihan item"
                        :class="[ACTION_BUTTON, ACTION_IDLE, 'hover:border-primary/40 hover:bg-primary/10 hover:text-primary']"
                    >
                        <svg :class="ACTION_ICON" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                    </button>
                </TapTooltip>

                <!-- Hapus -->
                <TapTooltip label="Hapus item" align="right">
                    <button
                        @click="remove"
                        type="button"
                        aria-label="Hapus item dari keranjang"
                        :class="[ACTION_BUTTON, ACTION_IDLE, 'hover:border-destructive/40 hover:bg-destructive/10 hover:text-destructive']"
                    >
                        <svg :class="ACTION_ICON" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </TapTooltip>
            </div>
        </div>

        <!-- Notes input -->
        <div v-if="showNotes" class="mt-2">
            <input
                :value="item.notes || ''"
                @input="onNotesChange"
                type="text"
                placeholder="Contoh: tanpa gula, ekstra susu"
                class="w-full px-2 py-1.5 text-xs border border-gray-200 rounded-md focus:ring-1 focus:ring-ring focus:border-ring bg-gray-50"
            />
        </div>

        <!-- Item notes display (if has notes but input hidden) -->
        <p v-if="!showNotes && item.notes" class="text-xs text-amber-600 mt-1 italic">
            Catatan: {{ item.notes }}
        </p>

        <!-- Harga khusus yang sedang berlaku: alasannya wajib terbaca tanpa
             membuka apa pun, karena inilah satu-satunya jejaknya di layar
             sebelum struk dicetak. -->
        <div v-if="item.override_unit_price" class="mt-2 flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-2 py-1">
            <span class="min-w-0 flex-1 truncate text-[11px] font-medium text-amber-800">
                Harga khusus: {{ item.discount_reason }}
            </span>
            <button
                @click="clearSpecialPrice"
                type="button"
                class="shrink-0 rounded border border-amber-300 bg-white px-1.5 py-0.5 pointer-coarse:px-2 pointer-coarse:py-1.5 text-[10px] font-semibold text-amber-800 transition hover:bg-amber-100 active:scale-95"
            >
                Batalkan
            </button>
        </div>

        <!-- Barang kedaluwarsa yang sudah dikonfirmasi ([BL-108]). Alasannya
             terbaca tanpa membuka apa pun, sama dengan harga khusus di atas:
             inilah jejaknya di layar sebelum penjualan tersimpan. Tidak ada
             tombol batal; kurangi qty atau hapus barisnya. -->
        <div
            v-if="sellsExpired && item.expired_confirmation_reason"
            class="mt-2 rounded-md border border-red-200 bg-red-50 px-2 py-1"
        >
            <span class="block truncate text-[11px] font-medium text-red-800">
                Kedaluwarsa: {{ item.expired_confirmation_reason }}
            </span>
        </div>

        <!-- Qty + Subtotal -->
        <div class="flex items-center justify-between mt-3">
            <div class="flex items-center gap-2">
                <button
                    @click="decrement"
                    :disabled="item.qty <= 1"
                    :class="[QTY_BUTTON, 'disabled:opacity-30 disabled:cursor-not-allowed disabled:active:scale-100']"
                >
                    <svg :class="QTY_ICON" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    class="w-12 h-8 pointer-coarse:h-10 px-1 text-sm font-semibold text-gray-800 text-center border border-primary rounded-md focus:ring-1 focus:ring-ring focus:border-ring [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                />
                <span
                    v-else
                    @dblclick="startEditQty"
                    @touchstart.passive="startLongPress"
                    @touchend="cancelLongPress"
                    @touchmove="cancelLongPress"
                    @touchcancel="cancelLongPress"
                    title="Klik 2x atau tahan untuk isi jumlah"
                    class="text-sm font-semibold text-gray-800 w-8 pointer-coarse:w-10 text-center cursor-pointer select-none rounded hover:bg-gray-100"
                >{{ item.qty }}</span>
                <button
                    @click="increment"
                    :class="QTY_BUTTON"
                >
                    <svg :class="QTY_ICON" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                </button>
            </div>
            <p class="text-sm font-semibold text-gray-800">{{ formatCurrency(subtotal()) }}</p>
        </div>
    </div>
</template>
