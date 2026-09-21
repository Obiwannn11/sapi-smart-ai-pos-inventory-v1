<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Saklar utama
    |--------------------------------------------------------------------------
    | Mati → indeks yang dikirim ke POS kosong dan strip saran tidak pernah
    | muncul. Pencatatan tetap menerima event lama (mis. dari outbox offline
    | yang antre sebelum fitur dimatikan) supaya tidak ada data yang hilang.
    */
    'enabled' => env('UPSELL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Batas tampilan
    |--------------------------------------------------------------------------
    | Kasir yang diberi terlalu banyak saran tiap penjualan akan menutup
    | semuanya tanpa membaca, dan fiturnya mati diam-diam sambil tetap terlihat
    | "ada" saat didemokan.
    |
    | NAIK DARI 2 KE 3 pada 2026-08-19, atas keputusan pemilik, bersamaan dengan
    | lahirnya aturan manual ([BL-074]): begitu owner bisa memasang sarannya
    | sendiri, dua slot berarti saran mesin nyaris selalu tergeser habis. Tiga
    | adalah batas yang pemilik minta diverifikasi langsung di layar kasir —
    | bila strip-nya jadi terlalu ramai, angka inilah yang diturunkan lagi,
    | bukan fiturnya yang dicabut.
    */
    'max_per_transaction' => 3,

    /** Kandidat yang disiapkan server per varian pemicu (client menyaring lagi). */
    'candidates_per_trigger' => 2,

    /** Kandidat barang tertekan yang disiapkan untuk seluruh keranjang. */
    'pressed_stock_candidates' => 4,

    /*
    |--------------------------------------------------------------------------
    | Jenis saran yang aktif
    |--------------------------------------------------------------------------
    | Laporan konversi memisahkan angka per jenis; jenis yang terbukti tidak
    | pernah diterima dimatikan di sini, bukan ditebak.
    */
    'types' => [
        'attach' => true,
        'pressed_stock' => true,
        'upsize' => true,

        /**
         * Aturan yang ditulis owner sendiri ([BL-074]). Mematikannya di sini
         * membungkam SELURUH aturan manual sekaligus — saklar darurat, bukan
         * cara mengatur aturan satu per satu. Yang itu ada di halaman
         * Aturan Saran Jual, per baris.
         */
        'manual' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Add-on (attach)
    |--------------------------------------------------------------------------
    */
    'attach' => [
        /** Jendela riwayat ko-okurensi modifier ↔ varian. */
        'window_days' => 30,

        /** Minimal kejadian sebelum sebuah modifier layak disarankan. */
        'min_support' => 2,

        /**
         * Tenant baru belum punya riwayat. Bila true, tawarkan modifier termurah
         * dari grup opsional produk tersebut dan tandai `reason: catalog` —
         * menawarkan yang tersedia, bukan mengarang pola yang tidak ada.
         */
        'fallback_to_catalog' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Barang tertekan (pressed_stock)
    |--------------------------------------------------------------------------
    | Ambangnya sengaja sama dengan BadgeHelperService supaya owner tidak
    | melihat dua definisi "mendekati kedaluwarsa" yang berbeda.
    */
    'pressed_stock' => [
        'near_expiry_days' => 7,
        'dead_stock_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Naik ukuran (upsize)
    |--------------------------------------------------------------------------
    */
    'upsize' => [
        /**
         * Batas lompatan harga terhadap harga pemicu. 0.6 berarti varian
         * Rp 10.000 hanya boleh disarankan naik sampai Rp 16.000. Lompatan
         * Small → Jumbo hampir selalu ditolak dan membuat kasir berhenti
         * membaca strip-nya.
         */
        'max_price_gap_ratio' => 0.6,
    ],

];
