<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Preset Kapabilitas per Jenis Usaha
    |--------------------------------------------------------------------------
    |
    | Jawaban "Jenis Usaha" di formulir pendaftaran dulu hanya dipakai penetapan
    | harga; warung bazar dan kafe mendarat di aplikasi yang persis sama, dengan
    | antrian dapur mati, lalu harus menemukan sendiri halaman Pengaturan untuk
    | menyalakannya (`[BL-034]`). Peta di bawah inilah yang membuat jawabannya
    | berarti sesuatu sejak menit pertama.
    |
    | Tiga batas yang menentukan bentuk berkas ini:
    |
    |   1. NILAI AWAL, BUKAN IKATAN. Preset berlaku SEKALI, saat pendaftaran.
    |      Sejak `[DECISION] Jenis Usaha Berpindah ke Pemilik Toko` jenis usaha
    |      bisa diubah kapan saja dari Pengaturan, dan mengubahnya TIDAK boleh
    |      menerapkan ulang preset ini — pemilik yang sudah mematikan antrian
    |      dapur tidak boleh mendapatkannya kembali hanya karena ia membetulkan
    |      jenis usahanya. Yang menegakkannya: `BusinessProfileController` tidak
    |      pernah menyentuh kolom `*_enabled`, dan ada ujinya.
    |
    |   2. PETA, BUKAN `if` DI CONTROLLER. Menambah jenis usaha berarti menambah
    |      satu baris di sini, bukan menyunting alur pendaftaran.
    |
    |   3. TETAP BISA DIUBAH SEBELUM LANJUT. Preset mengisi daftar centang di
    |      formulir; pendaftar boleh mencentang atau melepasnya. Yang dikirim
    |      formulir adalah hasil akhirnya, bukan nama presetnya — jadi peta ini
    |      tidak pernah jadi kata terakhir atas apa yang didapat tenant.
    |
    */

    /*
    | Kapabilitas yang boleh disetel preset.
    |
    | Kuncinya adalah nama fitur yang dikenali `Tenant::hasFeature()`, dan
    | `column` kolom yang menyimpannya. Sengaja hanya kapabilitas modul: aturan
    | kerja seperti `upsell_mandatory` dan `order_identity_mode` tinggal di
    | halaman "Cara Kerja Sistem" dan tidak menggerbangi rute apa pun, jadi
    | menebaknya dari jenis usaha berarti menebak cara orang bekerja, bukan
    | modul apa yang ia butuhkan.
    */
    'features' => [

        'kitchen_queue' => [
            'column' => 'kitchen_queue_enabled',
            'label' => 'Antrian dapur',
            'description' => 'Pesanan masuk ke layar dapur dan ditandai selesai satu per satu.',
        ],

        'self_order' => [
            'column' => 'self_order_enabled',
            'label' => 'Pesan mandiri',
            'description' => 'Pelanggan memesan sendiri lewat tautan publik outlet Anda.',
        ],

        'ai' => [
            'column' => 'ai_enabled',
            'label' => 'Analisis AI',
            'description' => 'Ringkasan penjualan dan saran stok yang disusun otomatis.',
        ],

    ],

    /*
    | Jenis usaha → kapabilitas yang menyala.
    |
    | Kuncinya WAJIB mencakup seluruh `config('pricing-dimensions.business_type.options')`
    | — satu jenis usaha tanpa baris di sini akan mendarat tanpa kapabilitas apa
    | pun, dan itu lebih buruk daripada keadaan sebelum preset ada. Ada ujinya.
    |
    | `self_order` tidak menyala di mana pun, dan itu bukan kelalaian:
    | menyalakannya membuka tautan pemesanan yang bisa diakses siapa saja. Itu
    | keputusan yang harus diambil pemiliknya sendiri, bukan disimpulkan dari
    | jenis usaha yang ia pilih.
    |
    | Preset `bazar` — "kasir dan stok saja" — belum ada di sini karena mode
    | bazar belum punya wujud di kode; ia menunggu `[BL-035]`. Begitu ada, ia
    | masuk sebagai satu baris tambahan, bukan sebagai perubahan alur.
    */
    'presets' => [

        // Dapur mengolah pesanan; layar antrian justru inti pekerjaannya.
        'kuliner' => ['kitchen_queue', 'ai'],

        // Barang diserahkan saat itu juga — tidak ada yang perlu diantre.
        'retail' => ['ai'],

        'jasa' => ['ai'],

        // Bawaan netral: jenis usaha yang belum dijawab tidak boleh membuat
        // aplikasi mengaku tahu cara kerja pemiliknya.
        'lainnya' => ['ai'],

    ],

];
