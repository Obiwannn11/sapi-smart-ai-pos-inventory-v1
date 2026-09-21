<script setup>
import { ref, computed, watch } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import { businessToday } from '@/support/date';

/**
 * Peringatan tenggat yang menghalangi layar, untuk tahap `intensive` dan
 * `locked` ([BL-054](c)).
 *
 * Pasangan `SubscriptionBanner`, bukan penggantinya: pita menyatakan keadaan
 * yang berlaku terus-menerus, modal ini menuntut satu keputusan. Ia sengaja
 * TIDAK muncul di tahap halus — modal yang muncul sejak hari pertama akan
 * ditutup refleks pada hari kesembilan belas juga.
 *
 * Tiga hal yang mematikannya, dan ketiganya sengaja:
 *
 * 1. **Pembayaran sedang berjalan** (`payment_pending`). Menagih orang yang
 *    sudah membayar adalah cara tercepat kehilangan mereka. Yang padam hanya
 *    notifikasinya — jam tenggatnya jalan terus di server, karena kalau tidak,
 *    menerbitkan instruksi bayar lalu mendiamkannya jadi cara membeli waktu
 *    tanpa membayar ([BL-054](d)).
 * 2. **Pengajuan Harga Adaptif sudah dilakukan** (`adaptive_pending`) — pemadam
 *    kedua yang [BL-054](c) minta dan [BL-055] sediakan. Tenant yang menyerahkan
 *    data omzetnya demi keringanan sudah melakukan persis hal yang diminta
 *    kepadanya; meneruskan teriakan menghukum orang yang menurut. Jam tenggatnya
 *    juga jalan terus, alasan yang sama seperti di atas.
 * 3. **Ditutup pengguna**, dan hanya sampai hari berganti. Tahap `locked`
 *    tidak menyimpan penutupannya sama sekali: sesudah kemampuan menulis
 *    dicabut, tidak ada lagi pekerjaan yang bisa diselesaikan dengan
 *    mengabaikannya.
 */

const page = usePage();

const restriction = computed(() => page.props.auth?.tenant?.subscription ?? null);
const isOwner = computed(() => page.props.auth?.user?.role === 'owner');

const stage = computed(() => restriction.value?.stage ?? null);
const isLocked = computed(() => stage.value === 'locked');

/**
 * Kunci penutupan, berumur satu hari kalender. Tanggalnya ikut di dalam kunci
 * supaya "jangan ganggu lagi" berarti hari ini, bukan selamanya.
 */
const dismissKey = computed(() => `grace-modal:${businessToday()}`);

const dismissed = ref(
    typeof window !== 'undefined' &&
    window.sessionStorage?.getItem(dismissKey.value) === '1',
);

const show = computed(() => {
    if (restriction.value === null) return false;
    if (restriction.value.status === 'suspended') return false;
    if (restriction.value.payment_pending) return false;
    if (restriction.value.adaptive_pending) return false;
    if (stage.value !== 'intensive' && stage.value !== 'locked') return false;

    return isLocked.value ? true : !dismissed.value;
});

const close = () => {
    if (isLocked.value) return;

    dismissed.value = true;
    window.sessionStorage?.setItem(dismissKey.value, '1');
};

// Tahap berpindah saat aplikasi sedang terbuka (kasir yang menyala semalaman
// melewati tengah malam) — penutupan kemarin tidak boleh ikut terbawa.
watch(dismissKey, () => {
    dismissed.value = window.sessionStorage?.getItem(dismissKey.value) === '1';
});

const formatCalendarDate = (value) => {
    if (!value) return null;
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const suspendsAt = computed(() => formatCalendarDate(restriction.value?.suspends_at));

const daysLeftToLock = computed(() => {
    const day = restriction.value?.grace_day;
    const lockFrom = restriction.value?.lock_from_day;
    if (!day || !lockFrom) return null;
    return Math.max(0, lockFrom - day);
});

const title = computed(() =>
    isLocked.value
        ? 'Pencatatan penjualan dihentikan'
        : 'Tagihan langganan belum diselesaikan',
);
</script>

<template>
    <!--
        Judulnya dirender di badan, bukan lewat prop `title`: header bawaan
        `Modal` selalu membawa tombol silang, dan di tahap `locked` tombol silang
        yang tidak menutup apa pun terbaca sebagai aplikasi rusak.
    -->
    <Modal
        :show="show"
        max-width="max-w-lg"
        :close-on-backdrop="!isLocked"
        @close="close"
    >
        <div class="space-y-4 text-sm leading-relaxed text-foreground">
            <h3 class="text-lg font-semibold">{{ title }}</h3>

            <p v-if="isLocked">
                Transaksi baru tidak bisa disimpan sampai tagihan diselesaikan.
                Seluruh data lama tetap bisa dibuka, dilihat, dan diunduh —
                laporan, riwayat transaksi, dan stok tidak ke mana-mana.
            </p>
            <p v-else>
                Kasir masih bisa dipakai seperti biasa hari ini. Bila tagihan
                belum diselesaikan
                <strong v-if="daysLeftToLock !== null">dalam {{ daysLeftToLock }} hari lagi</strong>
                <strong v-else>sampai batasnya</strong>, pencatatan penjualan
                akan dihentikan dan kasir tidak bisa menyimpan transaksi.
            </p>

            <p v-if="suspendsAt" class="text-muted-foreground">
                Bila tetap tidak diselesaikan, akses ditutup sepenuhnya pada
                <strong class="text-foreground">{{ suspendsAt }}</strong>.
                Data Anda tidak dihapus.
            </p>

            <!--
                Kasir tidak dikirim ke halaman Langganan — setiap tombol di sana
                digerbang `role:owner`. Alasan yang sama seperti di
                SubscriptionBanner: mengarahkan staf ke halaman yang tak satu pun
                aksinya bisa ia tekan hanya memindahkan kebuntuan.
            -->
            <p v-if="!isOwner" class="text-muted-foreground">
                Sampaikan ke pemilik usaha untuk menyelesaikannya.
            </p>
        </div>

        <template #footer>
            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                <button
                    v-if="!isLocked"
                    type="button"
                    class="rounded-lg border border-border px-4 py-2 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted"
                    @click="close"
                >
                    Nanti saja
                </button>
                <Link
                    v-if="isOwner"
                    href="/langganan"
                    class="rounded-lg bg-primary px-4 py-2 text-center text-sm font-medium text-primary-foreground transition-colors hover:opacity-90"
                >
                    Selesaikan pembayaran
                </Link>
            </div>
        </template>
    </Modal>
</template>
