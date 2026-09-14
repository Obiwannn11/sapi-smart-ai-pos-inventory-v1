<script setup>
/**
 * Saran jual — SATU KARTU BERGILIRAN, di dasar kolom katalog kasir.
 *
 * Sengaja BUKAN pop-up. Kasir yang diberi pop-up tiap penjualan akan menutup
 * semuanya tanpa membaca, dan fiturnya mati diam-diam sambil tetap terlihat
 * "ada" saat didemokan.
 *
 * **Kenapa satu kartu, bukan tiga baris setara.** Tiga baris kecil berbobot
 * sama dibaca sekilas lalu ditutup tiga-tiganya; tidak satu pun cukup besar
 * untuk diucapkan ke pelanggan yang sedang berdiri di depan kasir. Satu kartu
 * memaksa layar memilih apa yang paling mendesak — dan server sudah
 * memeringkatnya — lalu memberinya ruang yang cukup untuk dibaca sambil
 * melayani antrean. Slotnya tetap tiga di server; yang berubah hanya berapa
 * yang dihadapi kasir sekaligus.
 *
 * **Tata letaknya mengikuti LEBAR WADAHNYA, bukan lebar layar.** Di kolom
 * katalog yang lebar, kartunya mendatar: keterangan di kiri, harga di tengah,
 * keputusan di kanan — setinggi ±88 px. Di wadah sempit (tablet tegak, atau
 * kolom katalog yang tergencet keranjang yang dilebarkan) ia menumpuk seperti
 * semula. Breakpoint layar (`lg:`) akan salah menebak keduanya: layar 768 px
 * bisa punya kolom katalog 378 px, layar 1366 px bisa punya kolom 976 px.
 *
 * **Kartu ini menyebut ASAL sarannya, dan itu bukan hiasan.** "Tambah Extra
 * Shot" tanpa keterangan menyisakan pertanyaan yang harus dijawab kasir
 * sebelum bisa mengucapkannya: extra shot untuk yang mana. Saran ber-pemicu
 * menyebut baris keranjangnya, dan `@source` memberitahu POS baris mana yang
 * harus ikut menyala — jadi pertanyaan itu dijawab dengan MENUNJUK, tanpa
 * memindahkan tombolnya ke dalam daftar keranjang yang bisa tergulir.
 *
 * Kedua tombolnya adalah KEPUTUSAN, bukan "ambil" dan "tutup": pelanggan
 * menerima, atau pelanggan menolak. Keduanya sama-sama menyelesaikan saran —
 * termasuk saat owner mewajibkannya. Yang tidak tersedia hanyalah melewatinya
 * tanpa menjawab ([BL-025]). Keputusannya diikat ke saran yang TERGAMBAR —
 * lihat `decide()`.
 *
 * Keputusan "diterima" TIDAK final. Yang sudah diambil tetap terlihat di bawah,
 * lengkap dengan tombol batalkannya ([BL-092]): salah pencet dan pelanggan yang
 * berubah pikiran terjadi tiap hari, dan penerimaan yang tidak bisa ditarik
 * memaksa kasir menghapus barisnya diam-diam — sementara laporannya tetap
 * mengaku upsell itu berhasil.
 */
import { computed, ref, watch, watchEffect } from 'vue';

const props = defineProps({
    suggestions: { type: Array, default: () => [] },
    accepted: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    mandatory: { type: Boolean, default: false },
    /**
     * id varian pemicu → nama baris keranjangnya, untuk keterangan "Dari …".
     * Dititipkan POS: komponen ini tidak boleh tahu bentuk keranjang.
     */
    sourceNames: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['accept', 'reject', 'retract', 'shown', 'source']);

const formatCurrency = (value) => 'Rp ' + Number(value).toLocaleString('id-ID');

/**
 * Empat jenis saran, empat nada — seluruhnya token sistem desain.
 *
 * Nadanya mengikuti SEBERAPA BESAR TUNTUTANNYA pada kasir:
 *
 *   - `manual`  → primary. Permintaan pemilik toko; ini yang wajib diucapkan.
 *   - `pressed` → warning. Barang tertekan stok/kedaluwarsa — ada waktunya.
 *   - `upsize`  → success. Naik ukuran, tambahan omzet yang nyata.
 *   - `attach`  → muted.   Add-on, ajakan paling ringan dan paling sering.
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
        // Bukan "Barang tertekan" seperti di laporan owner: itu istilah
        // toko, bukan sesuatu yang bisa diucapkan kasir ke pelanggan.
        // Pratinjau di halaman Aturan (`SOURCE_BADGES`, RuleOutcomeResolver)
        // menyalin kata ini, jadi ubah ketiganya bersamaan.
        title: 'Segera jual',
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

/**
 * Giliran yang sedang dihadapi kasir.
 *
 * Bertahan di TEMPAT, bukan kembali ke nol, saat sebuah saran diputuskan:
 * saran yang selesai keluar dari daftar, jadi indeks yang sama otomatis
 * menunjuk yang berikutnya. Melompat balik ke awal tiap keputusan akan
 * memaksa kasir menelusuri ulang yang sudah ia jawab.
 */
const cursor = ref(0);

const current = computed(() => visible.value[cursor.value] ?? visible.value[0] ?? null);

watch(
    visible,
    (list) => {
        if (cursor.value > list.length - 1) {
            cursor.value = Math.max(0, list.length - 1);
        }
    },
    { immediate: true }
);

/**
 * `watchEffect`, BUKAN `watch(current, …)`, dan bedanya bukan gaya.
 *
 * Objek saran datang langsung dari indeks di props, jadi identitasnya STABIL:
 * saran yang sama pada transaksi berikutnya adalah objek yang sama persis.
 * Sebuah watch atas `current` membandingkan identitas, jadi ia tidak menyala —
 * dan setelah `reset()` mengosongkan catatan "pernah tampil", saran pertama
 * transaksi baru tidak akan pernah tercatat tampil. Penyebut konversinya hilang
 * satu tiap transaksi, diam-diam.
 *
 * `watchEffect` menjejaki `visible` dan `cursor`, dan `visible` adalah array
 * BARU tiap kali keranjang berubah — jadi ia menyala walau sarannya sama.
 * `markShown()` sendiri idempoten, jadi menyala berkali-kali tidak apa-apa.
 */
watchEffect(() => {
    const suggestion = current.value;

    // Dibaca supaya ikut terjejak walau `current` kebetulan tidak berubah.
    void visible.value;

    emit('source', suggestion?.trigger_variant_id ?? null);

    if (suggestion) {
        emit('shown', suggestion);
    }
});

const goTo = (index) => {
    if (visible.value.length === 0) return;

    cursor.value = (index + visible.value.length) % visible.value.length;
};

/**
 * Putuskan saran yang TERGAMBAR di kartu yang diketuk — bukan `current`.
 *
 * Kartunya berganti lewat `<Transition mode="out-in">`: kartu lama tetap
 * terlihat selama animasi keluarnya, sementara `current` sudah menunjuk saran
 * berikutnya. Tombol yang membaca `current` saat diketuk karenanya memutuskan
 * saran yang BELUM PERNAH DILIHAT kasir — cukup ketuk ganda dalam jeda ±100 ms
 * itu, dan saran berikutnya tercatat ditolak di laporan konversi. Ditemukan
 * saat diuji sebagai kasir: di halaman yang tersembunyi animasinya tertahan,
 * dan setiap ketukan pada kartu lama menolak satu saran lain.
 *
 * Kuncinya dibekukan di DOM saat kartu dirender (`data-suggestion-key`) —
 * Vue tidak menambal elemen yang sedang keluar — lalu dicari di daftar saran
 * yang MASIH menunggu. Kartu yang sedang keluar menunjuk saran yang sudah
 * diputuskan, jadi ketukannya tidak menemukan apa pun dan tidak melakukan apa
 * pun.
 *
 * @param {MouseEvent} event
 * @param {'accept'|'reject'} kind
 */
const decide = (event, kind) => {
    const key = event.currentTarget?.dataset?.suggestionKey;
    const suggestion = visible.value.find((item) => item.key === key);

    if (!suggestion) return;

    emit(kind, suggestion);
};

/** Nama baris keranjang yang melahirkan saran ini, bila ia punya pemicu. */
const sourceLabel = computed(() => {
    const triggerId = current.value?.trigger_variant_id;

    return triggerId ? (props.sourceNames[triggerId] ?? null) : null;
});
</script>

<template>
    <!-- `@container`: tiap `@2xl:` di bawah bertanya "apakah WADAH ini
         selebar ≥ 42rem", bukan layarnya. Lihat docblock di atas. -->
    <div v-if="current || taken.length > 0" class="@container space-y-2">
        <!-- Kepala terpisah hanya di wadah sempit. Di tata letak mendatar,
             "Wajib dijawab" menumpang di baris asal saran — satu baris
             penuh hanya untuk sebuah label adalah tinggi yang baru saja
             dikembalikan ke keranjang. -->
        <p
            v-if="current"
            class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground @2xl:hidden"
        >
            Tawarkan ke pelanggan
            <span v-if="mandatory" class="rounded bg-amber-100 px-1 py-0.5 normal-case text-amber-800">Wajib dijawab</span>
        </p>

        <Transition
            mode="out-in"
            enter-active-class="transition-all duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="current"
                :key="current.key"
                :class="[
                    'rounded-xl border px-3 py-2.5',
                    '@2xl:grid @2xl:grid-cols-[minmax(0,1fr)_auto_18rem] @2xl:items-center @2xl:gap-6 @2xl:px-4 @2xl:py-3',
                    toneFor(current.type).ring,
                ]"
            >
                <!-- Kiri: asal, jenis, nama, catatan -->
                <div class="min-w-0">
                    <!-- Asal sarannya, sebelum apa pun yang lain: inilah yang
                         menjawab "untuk barang yang mana" — pertanyaan yang harus
                         dijawab kasir sebelum bisa mengucapkan tawarannya. -->
                    <p
                        class="flex items-center gap-1.5 border-b border-dashed border-border pb-2 text-[10px] text-muted-foreground @2xl:border-b-0 @2xl:pb-0 @2xl:text-[11px]"
                    >
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
                        <template v-if="sourceLabel">
                            Dari <span class="truncate font-semibold text-foreground">{{ sourceLabel }}</span>
                        </template>
                        <template v-else>Untuk seluruh pesanan</template>
                        <span
                            v-if="mandatory"
                            class="ml-1 hidden shrink-0 rounded bg-amber-100 px-1 py-0.5 text-[10px] font-semibold text-amber-800 @2xl:inline"
                        >
                            Wajib dijawab
                        </span>
                    </p>

                    <div class="mt-2 flex items-center gap-2 @2xl:mt-1.5">
                        <span
                            :class="['shrink-0 rounded px-1.5 py-0.5 text-[9px] font-bold uppercase', toneFor(current.type).chip]"
                        >
                            {{ toneFor(current.type).title }}
                        </span>
                        <!-- Nama sebaris dengan jenisnya hanya di tata letak
                             mendatar; di wadah sempit ia turun ke barisnya
                             sendiri supaya nama panjang tidak terpotong. -->
                        <span class="hidden min-w-0 truncate text-base font-bold leading-tight text-foreground @2xl:block">
                            {{ current.label }}
                        </span>
                        <span
                            v-if="Number(current.extra_amount) > 0"
                            class="ml-auto shrink-0 text-xs font-bold text-primary @2xl:hidden"
                        >
                            +{{ formatCurrency(current.extra_amount) }}
                        </span>
                    </div>

                    <p class="mt-1 break-words text-[15px] font-bold leading-tight text-foreground @2xl:hidden">{{ current.label }}</p>
                    <!-- Catatan yang cuma mengulang chip jenisnya ("Pilihan pemilik"
                         dua kali) disembunyikan. -->
                    <p
                        v-if="current.note && current.note !== toneFor(current.type).title"
                        class="mt-0.5 break-words text-[11px] text-muted-foreground @2xl:truncate @2xl:text-xs"
                    >{{ current.note }}</p>
                </div>

                <!-- Tengah: harga, cukup besar untuk dibaca dari jarak meja -->
                <span
                    v-if="Number(current.extra_amount) > 0"
                    class="hidden shrink-0 text-lg font-bold tabular-nums text-primary @2xl:block"
                >
                    +{{ formatCurrency(current.extra_amount) }}
                </span>

                <!-- Kanan: keputusan. Kunci sarannya dibekukan di tombol saat
                     dirender — lihat `decide()`. -->
                <div class="mt-2.5 @2xl:mt-0">
                    <div class="flex gap-2">
                        <button
                            type="button"
                            :disabled="disabled"
                            :data-suggestion-key="current.key"
                            class="flex-1 rounded-lg bg-primary px-3 py-2.5 text-xs font-semibold text-primary-foreground transition hover:bg-primary/90 active:scale-[0.98] disabled:opacity-40"
                            @click="decide($event, 'accept')"
                        >
                            Diterima
                        </button>
                        <button
                            type="button"
                            :disabled="disabled"
                            :data-suggestion-key="current.key"
                            class="flex-1 rounded-lg border border-border bg-card px-3 py-2.5 text-xs font-semibold text-muted-foreground transition hover:bg-muted active:scale-[0.98] disabled:opacity-40"
                            @click="decide($event, 'reject')"
                        >
                            Ditolak
                        </button>
                    </div>

                    <!-- Pager hanya ada saat memang ada yang bisa digilir. Satu titik
                         sendirian cuma memberi tahu bahwa tidak ada yang lain. -->
                    <div v-if="visible.length > 1" class="mt-2 flex items-center justify-between @2xl:mt-1.5">
                        <div class="flex gap-1.5" role="tablist" aria-label="Saran untuk pelanggan">
                            <button
                                v-for="(suggestion, index) in visible"
                                :key="suggestion.key"
                                type="button"
                                role="tab"
                                :aria-selected="index === cursor"
                                :aria-label="`Saran ${index + 1}: ${suggestion.label}`"
                                :class="[
                                    'h-2 w-2 rounded-full transition',
                                    index === cursor ? 'bg-primary' : 'bg-border hover:bg-muted-foreground/40',
                                ]"
                                @click="goTo(index)"
                            ></button>
                        </div>
                        <button
                            type="button"
                            class="text-[10px] font-medium text-muted-foreground transition hover:text-foreground"
                            @click="goTo(cursor + 1)"
                        >
                            {{ cursor + 1 }} dari {{ visible.length }} · Berikutnya
                        </button>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Yang sudah diambil. Tetap terlihat justru supaya bisa ditarik lagi
             ([BL-092]) — dan pembatalannya membereskan keranjang sekaligus
             catatannya, bukan salah satunya saja. Di wadah lebar mereka berbaris
             menyamping, supaya tiga saran yang diterima tidak menambah tiga baris
             tinggi di atas katalog. -->
        <div v-if="taken.length > 0" class="space-y-1 @2xl:flex @2xl:flex-wrap @2xl:gap-2 @2xl:space-y-0">
            <div
                v-for="suggestion in taken"
                :key="suggestion.key"
                class="flex items-center gap-2 rounded-lg border border-success/30 bg-success/5 px-2.5 py-1.5 @2xl:max-w-md"
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
                    title="Batalkan dan keluarkan barangnya dari keranjang"
                    @click="emit('retract', suggestion)"
                >
                    Batalkan
                </button>
            </div>
        </div>
    </div>
</template>
