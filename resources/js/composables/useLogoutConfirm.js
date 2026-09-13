import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { clearPrivateOfflineData } from '@/services/offlineSession';

/**
 * Satu langkah tanya sebelum keluar, untuk semua jenis akun.
 *
 * Keadaannya di lingkup modul — singleton seperti `useFullscreen` dan
 * `useFlash` — supaya tombol keluar dan dialognya tidak harus tinggal di
 * komponen yang sama. Tombolnya ada di lima tempat (topbar kasir, topbar
 * owner, sidebar platform, Rekap Kas, dan halaman verifikasi email); dialognya
 * cukup satu per cangkang.
 *
 * Kenapa perlu ditanya sama sekali: mesin kasir adalah perangkat bersama, dan
 * tombol keluar duduk di pita yang sama dengan tombol-tombol yang dipakai
 * puluhan kali sehari. Keluar tidak bisa diurungkan — sesinya hilang, cache
 * halaman dibersihkan, dan kasir harus mengetik ulang sandinya di depan
 * antrean.
 *
 * @typedef {object} LogoutRequest
 * @property {string} endpoint Rute POST yang menutup sesi.
 * @property {boolean} clearOfflineData Bersihkan cache milik pengguna dulu.
 * @property {string} title Judul dialog.
 * @property {string} message Kalimat penjelas di dalam dialog.
 */

/** @type {import('vue').Ref<LogoutRequest|null>} */
const request = ref(null);
const processing = ref(false);

export function useLogoutConfirm() {
    const isConfirming = computed(() => request.value !== null);

    /**
     * Buka dialognya. Tidak ada yang dikirim ke server sampai dijawab.
     *
     * @param {Partial<LogoutRequest>} options
     */
    const requestLogout = (options = {}) => {
        request.value = {
            endpoint: options.endpoint ?? '/logout',
            clearOfflineData: options.clearOfflineData ?? true,
            title: options.title ?? 'Keluar dari akun?',
            message:
                options.message ??
                'Sesi Anda di perangkat ini akan ditutup dan Anda harus masuk lagi untuk melanjutkan.',
        };
    };

    const cancelLogout = () => {
        // Selama permintaannya sedang jalan, backdrop dan tombol Batal tidak
        // boleh menutup dialog: sesinya sudah dalam perjalanan untuk ditutup.
        if (processing.value) {
            return;
        }

        request.value = null;
    };

    const confirmLogout = async () => {
        if (processing.value || !request.value) {
            return;
        }

        const { endpoint, clearOfflineData } = request.value;
        processing.value = true;

        if (clearOfflineData) {
            await clearPrivateOfflineData();
        }

        // Dialognya sengaja dibiarkan terbuka sampai halaman berpindah:
        // menutupnya lebih dulu menampilkan kembali layar yang sudah tidak
        // berlaku. `request` ini singleton lintas modul (bukan per komponen),
        // jadi kalau tidak dibersihkan di sini ia bertahan melewati navigasi
        // Inertia yang tidak memuat ulang JS — muncul lagi begitu dialog
        // dipasang ulang di sesi login berikutnya. `onError` membersihkannya
        // juga supaya tombolnya tidak mati selamanya kalau POST-nya gagal.
        router.post(endpoint, {}, {
            onSuccess: () => {
                request.value = null;
            },
            onError: () => {
                processing.value = false;
                request.value = null;
            },
            onFinish: () => {
                processing.value = false;
            },
        });
    };

    return { request, isConfirming, processing, requestLogout, cancelLogout, confirmLogout };
}
