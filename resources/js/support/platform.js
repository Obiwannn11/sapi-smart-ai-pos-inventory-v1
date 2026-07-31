/**
 * Format dan kosakata bersama panel platform.
 *
 * Sebelumnya tiap halaman menuliskan `Intl.NumberFormat`-nya sendiri, dan
 * label keadaan yang sama diterjemahkan berbeda di dua tempat. Menaruhnya di
 * satu berkas membuat "Masa tenggang" berbunyi sama di mana pun ia muncul.
 */

const rupiah = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
});

/** Nominal rupiah; `null` sengaja jadi tanda hubung, bukan Rp 0. */
export const formatRupiah = (value, fallback = '—') =>
    value === null || value === undefined ? fallback : rupiah.format(value);

/** Tanggal ISO (YYYY-MM-DD) ke bentuk yang dibaca orang. */
export const formatDate = (value, fallback = '—') => {
    if (!value) {
        return fallback;
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
};

/** Periode tagihan (YYYY-MM) ke nama bulan. */
export const formatPeriod = (value, fallback = '—') => {
    if (!value) {
        return fallback;
    }

    return new Date(`${value}-01`).toLocaleDateString('id-ID', {
        month: 'long',
        year: 'numeric',
    });
};

// --- Keadaan tenant ---
export const TENANT_STATUS = {
    trial: { label: 'Masa coba', tone: 'info' },
    active: { label: 'Aktif', tone: 'success' },
    grace: { label: 'Masa tenggang', tone: 'warning' },
    suspended: { label: 'Ditangguhkan', tone: 'danger' },
};

// --- Keadaan tagihan ---
export const INVOICE_STATUS = {
    unpaid: { label: 'Belum bayar', tone: 'neutral' },
    awaiting_verification: { label: 'Menunggu diperiksa', tone: 'warning' },
    paid: { label: 'Lunas', tone: 'success' },
    rejected: { label: 'Ditolak', tone: 'danger' },
};

export const INVOICE_KIND = {
    subscription: 'Langganan',
    upgrade: 'Tambah pengguna',
};

// --- Jalur harga ---
// Istilah "subsidi" sengaja tidak dipakai di permukaan mana pun. Nilai yang
// tersimpan di basis data tetap `normal` / `subsidized`; yang berubah hanyalah
// apa yang dibaca orang. Kata "subsidi" menempatkan klien sebagai penerima
// bantuan, padahal yang terjadi adalah pertukaran: mereka membuka omzetnya,
// tarifnya menyesuaikan.
export const PRICING_TRACK = {
    normal: {
        label: 'Harga Tetap',
        tone: 'neutral',
        description: 'Tarif publik yang sama untuk semua. Tidak ada data usaha yang dibuka.',
    },
    subsidized: {
        label: 'Harga Adaptif',
        tone: 'info',
        description: 'Tarif mengikuti omzet bulanan, atas persetujuan tenant sendiri.',
    },
};

/** Kelas input yang dipakai seluruh form platform. */
export const inputClass =
    'w-full px-3 py-2 border border-border rounded-lg text-sm bg-card text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring';
