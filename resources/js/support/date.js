/**
 * Tanggal dan waktu menurut jam TOKO, bukan jam perangkat ([BL-082]).
 *
 * Sisi server menjalankan seluruh aplikasi di zona bisnisnya
 * (`config('app.timezone')`). Sisi peramban dulu memakai zona perangkat, dan
 * di situlah selisihnya terlihat: pada satu layar yang sama, topbar menulis
 * "Jumat, 21 Agustus" sementara pemilih tanggal Laporan Harian default ke
 * "Kamis, 20 Agustus". Dua tanggal untuk satu saat yang sama.
 *
 * Dua kesalahan yang berkali-kali ditulis di berkas ini sebelum ada modul ini:
 *
 *   1. `new Date().toISOString().slice(0, 10)` — itu tanggal UTC, bukan
 *      tanggal siapa pun. Di WITA ia salah hari sepanjang pukul 00.00–08.00.
 *   2. `new Date().getFullYear()` dan kawan-kawannya — itu tanggal PERANGKAT.
 *      Benar selama tabletnya disetel benar, dan hanya selama itu.
 *
 * Keduanya dijawab dengan menyebut zonanya secara eksplisit lewat `Intl`.
 */

/** Zona bisnis dari meta tag; jatuh ke zona perangkat bila halaman tak punya. */
const resolveTimezone = () => {
    const meta =
        typeof document !== 'undefined'
            ? document.querySelector('meta[name="business-timezone"]')?.content
            : null;

    return meta || Intl.DateTimeFormat().resolvedOptions().timeZone;
};

/**
 * Zona waktu bisnis, mis. `Asia/Makassar`.
 *
 * Konstanta, bukan fungsi: meta tag-nya ada sejak dokumen dimuat dan tidak
 * berubah selama sesi. Diekspor supaya pemanggil bisa menitipkannya sebagai
 * `timeZone` ke opsi `toLocaleDateString`-nya sendiri.
 */
export const BUSINESS_TZ = resolveTimezone();

const isoParts = new Intl.DateTimeFormat('en-CA', {
    timeZone: BUSINESS_TZ,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

/** Nilai apa pun (Date/ISO string) jadi `YYYY-MM-DD` menurut hari toko. */
export const businessDateString = (value = new Date()) => {
    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    // en-CA memberi bentuk ISO (2026-08-22) di semua peramban modern.
    return isoParts.format(date);
};

/** Tanggal hari ini menurut hari toko, `YYYY-MM-DD`. */
export const businessToday = () => businessDateString();

/** Tanggal `days` hari yang lalu menurut hari toko, `YYYY-MM-DD`. */
export const businessDaysAgo = (days) =>
    businessDateString(new Date(Date.now() - days * 86400000));

/** Tanggal `days` hari ke depan menurut hari toko, `YYYY-MM-DD`. */
export const businessDaysAhead = (days) => businessDaysAgo(-days);

/** Bulan berjalan menurut bulan toko, `YYYY-MM`. */
export const businessMonth = () => businessToday().slice(0, 7);

/**
 * Tanggal-saja (`YYYY-MM-DD`) jadi objek Date pada tengah malam LOKAL.
 *
 * `new Date('2026-08-22')` diurai sebagai tengah malam UTC, jadi di zona
 * mana pun yang lebih barat ia mundur satu hari begitu diformat. Dipakai
 * label grafik dan pemilih tanggal, yang isinya memang tanggal tanpa jam.
 */
export const parseDateOnly = (value) => {
    const [year, month, day] = String(value).slice(0, 10).split('-').map(Number);

    return new Date(year, (month ?? 1) - 1, day ?? 1);
};

/** Format tanggal menurut zona toko; opsi `Intl` biasa. */
export const formatBusinessDate = (value, options = {}, locale = 'id-ID') =>
    new Date(value).toLocaleDateString(locale, { timeZone: BUSINESS_TZ, ...options });

/** Format tanggal + jam menurut zona toko; opsi `Intl` biasa. */
export const formatBusinessDateTime = (value, options = {}, locale = 'id-ID') =>
    new Date(value).toLocaleString(locale, { timeZone: BUSINESS_TZ, ...options });

/** Format jam menurut zona toko; opsi `Intl` biasa. */
export const formatBusinessTime = (value, options = {}, locale = 'id-ID') =>
    new Date(value).toLocaleTimeString(locale, { timeZone: BUSINESS_TZ, ...options });
