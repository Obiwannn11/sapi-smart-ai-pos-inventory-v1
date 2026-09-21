<script setup>
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';

/**
 * Pita keadaan langganan, dipasang di shell owner DAN shell kasir.
 *
 * Penegakan hanya-baca sudah lama benar, tapi ia tak terlihat: kasir yang
 * membuka POS langsung, atau owner yang seharian di halaman Produk, tidak tahu
 * apa-apa sampai ia menekan Simpan dan mendapat penolakan. Penolakannya benar,
 * tapi kejutan — dan kejutan itu datang justru saat pembeli sedang menunggu di
 * depan meja kasir. Lihat `[BL-045]`.
 *
 * Sejak `[BL-054]` pita ini BERTINGKAT mengikuti tahap tenggat: hari 1–14
 * memberi tahu tanpa menakut-nakuti (kasirnya masih jalan — mengabarkan
 * "transaksi tidak bisa disimpan" di tahap itu adalah bohong yang menghentikan
 * jualan orang sendiri), 15–19 mendesak, 20+ barulah menyatakan penulisan
 * dicabut. Ia satu-satunya "penanda di topbar" yang dimaksud keputusan pemilik;
 * modalnya terpisah di `GraceModal`.
 *
 * Sengaja TIDAK bisa ditutup. Ia bukan notifikasi yang lewat seperti
 * `FlashMessage`, melainkan keadaan yang masih berlaku; menutupnya hanya
 * menyembunyikan sesuatu yang tetap benar sedetik kemudian.
 */

const page = usePage();

const restriction = computed(() => page.props.auth?.tenant?.subscription ?? null);
const isOwner = computed(() => page.props.auth?.user?.role === 'owner');

/**
 * Tanggal ISO dibaca sebagai tanggal kalender, bukan sebagai titik waktu.
 * `new Date('2026-08-25')` diurai sebagai tengah malam UTC, sehingga di zona
 * yang di belakang UTC hasilnya mundur sehari. Zona Indonesia kebetulan aman,
 * tapi tanggal penangguhan yang benar hanya karena kebetulan bukan tanggal yang
 * benar. Pola yang sama dipakai kartu Dashboard dan halaman Langganan.
 */
const formatCalendarDate = (value) => {
    if (!value) return null;
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const notice = computed(() => {
    if (restriction.value === null) return null;

    const { status, stage, grace_day: day, grace_days: total } = restriction.value;
    const suspendsAt = formatCalendarDate(restriction.value.suspends_at);
    const closing = suspendsAt ? ` Akses ditutup sepenuhnya pada ${suspendsAt}.` : '';
    const counter = day ? ` Hari ke-${day} dari ${total}.` : '';

    if (status === 'suspended') {
        return {
            tone: 'danger',
            title: 'Langganan ditangguhkan',
            body: 'Akses ditutup sampai pembayaran diselesaikan. Data Anda tidak dihapus.',
        };
    }

    if (stage === 'locked') {
        return {
            tone: 'danger',
            title: 'Pencatatan dihentikan sampai tagihan diselesaikan',
            body: `Transaksi dan perubahan baru tidak bisa disimpan. Data lama tetap bisa dibuka dan diunduh.${closing}`,
        };
    }

    if (stage === 'intensive') {
        return {
            tone: 'warning',
            title: 'Tagihan belum diselesaikan',
            body: `Kasir masih bisa dipakai, tapi tidak lama lagi.${counter} Setelah itu transaksi baru tidak bisa disimpan.${closing}`,
        };
    }

    // Tahap halus. Nadanya sengaja `info`, bukan peringatan: belum ada satu pun
    // yang dicabut, dan pita merah di layar kasir sepanjang hari kerja hanya
    // melatih orang mengabaikannya — sehingga tahap berikutnya, yang benar-benar
    // mencabut sesuatu, tiba tanpa ada yang membacanya.
    return {
        tone: 'info',
        title: 'Masa langganan sudah berakhir',
        body: `Kasir dan seluruh menu tetap berjalan seperti biasa.${counter} Selesaikan tagihan agar tidak terganggu.`,
    };
});

const tones = {
    info: {
        strip: 'bg-muted border-border text-foreground',
        action: 'text-foreground hover:bg-muted-foreground/10 border-border',
    },
    warning: {
        strip: 'bg-warning/10 border-warning/40 text-warning-foreground',
        action: 'text-warning-foreground hover:bg-warning/20 border-warning/40',
    },
    danger: {
        strip: 'bg-destructive/10 border-destructive/40 text-destructive',
        action: 'text-destructive hover:bg-destructive/20 border-destructive/40',
    },
};
</script>

<template>
    <div
        v-if="notice"
        :class="['border-b px-4 py-2.5 sm:px-6', tones[notice.tone].strip]"
        role="status"
        aria-live="polite"
    >
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    :d="notice.tone === 'info'
                        ? 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
                        : 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.834-1.964-.834-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z'"
                />
            </svg>

            <p class="flex-1 text-sm leading-snug">
                <span class="font-semibold">{{ notice.title }}.</span>
                {{ notice.body }}
            </p>

            <!--
                Kasir tidak dikirim ke halaman Langganan. Ia boleh membukanya,
                tapi setiap tombol di sana digerbang `role:owner` — mengarahkan
                staf ke halaman yang tak satu pun aksinya bisa ia tekan hanya
                memindahkan kebuntuan, tidak menyelesaikannya.
            -->
            <Link
                v-if="isOwner"
                href="/langganan"
                :class="['flex-shrink-0 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors', tones[notice.tone].action]"
            >
                Selesaikan pembayaran
            </Link>
            <span v-else class="flex-shrink-0 text-sm opacity-80">
                Hubungi pemilik usaha.
            </span>
        </div>
    </div>
</template>
