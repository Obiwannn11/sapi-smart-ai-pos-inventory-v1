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

    const suspendsAt = formatCalendarDate(restriction.value.suspends_at);

    if (restriction.value.status === 'suspended') {
        return {
            tone: 'danger',
            title: 'Langganan ditangguhkan',
            body: 'Akses ditutup sampai pembayaran diselesaikan. Data Anda tidak dihapus.',
        };
    }

    return {
        tone: 'warning',
        title: 'Masa langganan sudah berakhir',
        body: suspendsAt
            ? `Transaksi dan perubahan baru tidak bisa disimpan. Data lama tetap bisa dibuka dan diunduh. Akses ditutup sepenuhnya pada ${suspendsAt}.`
            : 'Transaksi dan perubahan baru tidak bisa disimpan. Data lama tetap bisa dibuka dan diunduh.',
    };
});

const tones = {
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.834-1.964-.834-2.732 0L3.07 16.5c-.77.833.192 2.5 1.732 2.5z" />
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
