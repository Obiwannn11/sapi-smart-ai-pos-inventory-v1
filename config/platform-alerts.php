<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Peringatan ke Pemilik SaaS
    |--------------------------------------------------------------------------
    |
    | Jejak audit menjawab "apa yang terjadi?" — tapi hanya bagi yang sempat
    | membukanya. Peringatan di sini menjawab "apa yang perlu saya lihat
    | SEKARANG?", dan dikirim tanpa menunggu seseorang membuka halaman.
    |
    | Kanalnya surel. Proyek ini sudah memakainya sejak pemulihan kata sandi
    | ([BL-010]/[BL-012]), jadi tidak ada kanal baru yang perlu dibangun —
    | catatan lama di [BL-011] yang menyebut "belum ada kanal notifikasi apa
    | pun" sudah tidak berlaku.
    |
    */

    /**
     * Penerima peringatan.
     *
     * `null` berarti dikirim ke semua akun platform bertanda `is_owner`.
     * Isi dengan alamat tertentu bila peringatan sebaiknya masuk ke kotak surel
     * operasional, bukan ke kotak pribadi.
     */
    'recipient' => env('PLATFORM_ALERT_EMAIL'),

    /**
     * Jeda minimum sebelum peringatan SEJENIS dikirim ulang, dalam menit.
     *
     * Tanpa jeda ini, satu serangan yang berlangsung semalaman menghasilkan
     * satu surel tiap kali perintah berjalan — dan kotak masuk yang penuh
     * peringatan identik dibaca persis sama seperti kotak masuk tanpa
     * peringatan sama sekali.
     */
    'cooldown_minutes' => 180,

    /*
    |--------------------------------------------------------------------------
    | Ambang Percobaan Masuk Gagal ([BL-011])
    |--------------------------------------------------------------------------
    |
    | Dua pola yang berbeda, dan sengaja punya ambang sendiri-sendiri:
    |
    | `per_email` — banyak kegagalan pada SATU alamat. Ini penebakan kata sandi
    |               terhadap akun yang sudah diketahui. Throttle sudah menahan
    |               lajunya ([BL-007]); yang belum ada adalah yang memberitahu.
    |
    | `per_ip`    — banyak alamat BERBEDA dicoba dari satu IP. Ini penebakan
    |               akun: mencari tahu alamat mana yang terdaftar. Lolos dari
    |               kunci per-email justru karena tiap alamat dicoba sedikit.
    |
    | Jendelanya sengaja lebih panjang daripada jendela throttle: serangan yang
    | berjalan pelan — di bawah ambang throttle, tersebar berjam-jam — persis
    | yang ingin ditangkap di sini.
    |
    */

    'failed_login' => [
        'window_minutes' => 360,
        'per_email' => 10,
        'per_ip_distinct_emails' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deteksi Pendaftaran Berulang ([BL-014])
    |--------------------------------------------------------------------------
    |
    | Berapa banyak pendaftaran dari satu IP dalam rentang waktu sebelum tenant
    | baru ditandai untuk ditinjau.
    |
    | DITANDAI, bukan diblokir. Satu IP publik bisa dipakai bersama satu
    | kompleks pertokoan, dan memblokir otomatis akan menjegal warung sebelah
    | yang tidak melakukan kesalahan apa pun. Menandai membuat orang memutuskan;
    | memblokir membuat mesin memutuskan atas hal yang tidak cukup ia ketahui.
    |
    */

    'signup' => [
        'window_hours' => 24,
        'max_per_ip' => 3,
    ],

];
