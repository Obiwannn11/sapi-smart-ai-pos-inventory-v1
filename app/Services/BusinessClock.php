<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Satu-satunya tempat yang menjawab "sekarang jam berapa, dan hari ini
 * tanggal berapa, menurut toko" ([BL-082]).
 *
 * Sebelum ini tidak ada satu pun baris di `app/` yang menyebut zona waktu:
 * server berjalan di UTC sementara tokonya tidak, sehingga batas hari jatuh
 * pukul 08.00 WITA. Penjualan jam pertama tiap pagi masuk ke laporan hari
 * SEBELUMNYA — dan Laporan Harian adalah angka yang dipakai pemilik menutup
 * harinya.
 *
 * Perbaikannya bukan mengurangi delapan jam di beberapa kueri, melainkan
 * menjalankan seluruh aplikasi di zona bisnisnya (`config('app.timezone')`,
 * lihat `APP_TIMEZONE`). Karena itu `now()` Laravel SUDAH waktu toko, dan
 * kelas ini tidak menghitung ulang apa pun — ia menamai maksudnya:
 *
 *   1. Ia satu-satunya yang menyebut zona bisnis. Bila suatu hari zonanya
 *      jadi per tenant, hanya berkas ini yang perlu berubah — bukan setiap
 *      laporan yang mengelompokkan per hari.
 *   2. `fromClient()` menutup satu-satunya lubang yang tidak ikut sembuh
 *      dengan mengubah config: cap waktu yang datang DARI perangkat.
 *
 * Aturan yang sama sudah terbukti pada `Transaction::effectiveDateSql()` —
 * dua definisi yang berselisih hanya akan terlihat sebagai angka laporan yang
 * salah, jauh setelah penyebabnya dilupakan.
 */
class BusinessClock
{
    /**
     * Zona waktu bisnis, mis. `Asia/Makassar`.
     *
     * Dibaca dari config, bukan ditulis mati: nilainya ikut `APP_TIMEZONE`.
     */
    public static function timezone(): string
    {
        return config('app.timezone');
    }

    /**
     * Saat ini menurut jam toko.
     */
    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    /**
     * Tanggal hari ini menurut hari toko, `Y-m-d`.
     *
     * Ini yang dipakai kartu "hari ini" di dashboard dan nilai bawaan pemilih
     * tanggal Laporan Harian — dua angka yang dulu berselisih satu hari di
     * layar yang sama.
     */
    public static function today(): string
    {
        return self::now()->toDateString();
    }

    /**
     * Tanggal `$days` hari yang lalu menurut hari toko, `Y-m-d`.
     */
    public static function daysAgo(int $days): string
    {
        return self::now()->subDays($days)->toDateString();
    }

    /**
     * Tanggal awal minggu berjalan menurut hari toko, `Y-m-d`.
     */
    public static function startOfWeek(): string
    {
        return self::now()->startOfWeek()->toDateString();
    }

    /**
     * Periode bulan berjalan menurut bulan toko, `Y-m`.
     *
     * Bentuk yang sama dengan kolom `period` di `tenant_monthly_metrics`, yang
     * jadi dasar bracket Harga Adaptif. Batas bulan yang bergeser delapan jam
     * bisa memindahkan tenant ke bracket lain, jadi ia wajib dibaca dari sini.
     */
    public static function period(): string
    {
        return self::now()->format('Y-m');
    }

    /**
     * Cap waktu yang dikirim perangkat, dibawa ke zona bisnis.
     *
     * Peramban mengirim instan dalam UTC (`toISOString()` selalu berakhiran
     * `Z`). Carbon mempertahankan zona asal string itu, dan Eloquent menyimpan
     * kolom datetime dengan memformat objeknya apa adanya — tanpa langkah ini,
     * satu transaksi offline pukul 09.00 WITA tersimpan sebagai `01:00` dan
     * jatuh ke hari yang salah, persis kesalahan yang sedang diperbaiki.
     */
    public static function fromClient(Carbon|\DateTimeInterface|string $value): Carbon
    {
        $instant = $value instanceof \DateTimeInterface
            ? Carbon::instance($value)
            : Carbon::parse($value);

        return $instant->setTimezone(self::timezone());
    }
}
