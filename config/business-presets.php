<?php

use App\Models\Tenant;

return [

    /*
    |--------------------------------------------------------------------------
    | Paket Setelan Awal per Cara Berjualan
    |--------------------------------------------------------------------------
    |
    | Jawaban "Jenis Usaha" di formulir pendaftaran dulu hanya dipakai penetapan
    | harga; warung bazar dan kafe mendarat di aplikasi yang persis sama, dengan
    | antrian dapur mati, lalu harus menemukan sendiri halaman Pengaturan untuk
    | menyalakannya (`[BL-034]`). Peta di bawah inilah yang membuat jawabannya
    | berarti sesuatu sejak menit pertama.
    |
    | Empat batas yang menentukan bentuk berkas ini:
    |
    |   1. NILAI AWAL, BUKAN IKATAN. Preset berlaku SEKALI, saat pendaftaran.
    |      Sejak `[DECISION] Jenis Usaha Berpindah ke Pemilik Toko` jenis usaha
    |      bisa diubah kapan saja dari Pengaturan, dan mengubahnya TIDAK boleh
    |      menerapkan ulang preset ini — pemilik yang sudah mematikan antrian
    |      dapur tidak boleh mendapatkannya kembali hanya karena ia membetulkan
    |      jenis usahanya. Yang menegakkannya: `BusinessProfileController` tidak
    |      pernah menyentuh kolom setelan, dan ada ujinya.
    |
    |   2. PETA, BUKAN `if` DI CONTROLLER. Menambah cara berjualan berarti
    |      menambah satu baris di sini, bukan menyunting alur pendaftaran.
    |
    |   3. TETAP BISA DIUBAH SEBELUM LANJUT. Preset mengisi daftar centang di
    |      formulir; pendaftar boleh mencentang atau melepasnya. Yang dikirim
    |      formulir adalah hasil akhirnya, bukan nama presetnya — jadi peta ini
    |      tidak pernah jadi kata terakhir atas apa yang didapat tenant.
    |
    |   4. KUNCINYA CARA BERJUALAN, BUKAN JENIS USAHA (`[BL-035]`). Keduanya
    |      pertanyaan berbeda dengan akibat berbeda: `business_type` milik
    |      PENETAPAN HARGA (dimensi di `config/pricing-dimensions.php`, dibekukan
    |      per tagihan), sedangkan `selling_style` cuma memilih setelan awal dan
    |      tidak pernah menyentuh tarif. Penjual di CFD tetap `kuliner` untuk
    |      harga; yang berbeda cuma cara ia bekerja. `business_type_styles` di
    |      bawah menjembatani keduanya sebagai TEBAKAN AWAL saja — pendaftar
    |      tetap melihat dan bisa mengganti cara berjualannya.
    |
    | PEMBALIKAN ATURAN, 2026-09-06 (`[BL-035]`). Berkas ini dulu menyatakan
    | preset sengaja hanya menyentuh kapabilitas modul, karena "menebak aturan
    | kerja dari jenis usaha berarti menebak cara orang bekerja". Pemilik
    | membalikkannya, dan alasannya kuat: aturan itu ditulis untuk mencegah
    | aplikasi memutuskan DIAM-DIAM, dan batas #3 di atas sudah menetralkannya.
    | Jadi aturan kerja seperti `order_identity_mode` dan `upsell_mandatory`
    | SEKARANG BOLEH ikut paket — syaratnya satu, dan ia ditegakkan oleh
    | `visible` di bawah: setelan yang tidak tampil di formulir wajib disebutkan
    | di ringkasan sesudahnya. Yang tetap terlarang adalah setelan yang mendarat
    | tanpa pernah muncul di layar mana pun.
    |
    | Yang TETAP tidak boleh masuk paket mana pun: pajak (`tax_*`) karena status
    | pajak urusan hukum, serta `min_margin_percent` dan
    | `cash_payout_approval_threshold` karena keduanya angka kebijakan. Ketiganya
    | tidak punya baris di `settings` di bawah, dan itu bukan kelalaian.
    */

    /*
    | Setelan yang boleh disetel paket.
    |
    | Kuncinya nama setelan; `column` kolom `tenants` yang menyimpannya.
    |
    |   - `type`       'boolean' untuk saklar, 'choice' untuk setelan bernilai
    |                  pilihan.
    |   - `capability`  true  = kapabilitas modul, dikenali `Tenant::hasFeature()`
    |                          dan bisa menggerbangi rute.
    |                  false = ATURAN KERJA. Ia tetap saklar dan tetap kolom,
    |                          tapi `hasFeature()` sengaja tidak mengenalinya
    |                          karena ia tidak menggerbangi apa pun ([BL-025]).
    |                          Dipisahkan supaya "apa yang bisa dipakai
    |                          `feature:` di rute" tetap punya jawaban yang
    |                          bisa diperiksa; ada ujinya.
    |   - `visible`     true  = tampil di formulir pendaftaran, bisa diubah saat
    |                          itu juga.
    |                  false = mendarat tanpa ditanyakan, tapi WAJIB disebut di
    |                          ringkasan sesudahnya — itu syarat pembalikan
    |                          aturan di atas, dan `hiddenSummaryFor()` yang
    |                          menegakkannya. Dipakai untuk setelan yang benar
    |                          bagi hampir semua orang dan cuma menambah panjang
    |                          formulir kalau ditanyakan — panjangnya formulir
    |                          itu sendiri yang membingungkan pengguna baru,
    |                          yaitu masalah yang paket ini ada untuk
    |                          menyelesaikannya.
    |
    | Hanya setelan `visible` yang boleh dikirim formulir pendaftaran. Yang
    | tersembunyi datang dari paket saja, dan itu bukan pembatasan kosmetik:
    | daftar centang formulir tidak pernah memuatnya, jadi memperlakukannya
    | sebagai "tidak dicentang berarti mati" akan memaksa mati setiap setelan
    | tersembunyi yang paketnya justru ingin nyalakan.
    */
    'settings' => [

        'kitchen_queue' => [
            'column' => 'kitchen_queue_enabled',
            'type' => 'boolean',
            'capability' => true,
            'visible' => true,
            'label' => 'Antrian dapur',
            'description' => 'Pesanan masuk ke layar dapur dan ditandai selesai satu per satu.',
        ],

        'self_order' => [
            'column' => 'self_order_enabled',
            'type' => 'boolean',
            'capability' => true,
            'visible' => true,
            'label' => 'Pesan mandiri',
            'description' => 'Pelanggan memesan sendiri lewat tautan publik outlet Anda.',
        ],

        'ai' => [
            'column' => 'ai_enabled',
            'type' => 'boolean',
            'capability' => true,
            'visible' => true,
            'label' => 'Analisis AI',
            'description' => 'Ringkasan penjualan dan saran stok yang disusun otomatis.',
        ],

        // Tidak tampil: menyalakannya menambah satu langkah ke SETIAP penjualan
        // non-tunai ([BL-075]), dan bawaannya mati untuk semua orang. Menanyakan
        // ini di pendaftaran berarti meminta orang memutuskan sesuatu yang belum
        // punya konteks apa pun baginya.
        'payment_proof' => [
            'column' => 'payment_proof_enabled',
            'type' => 'boolean',
            'capability' => true,
            'visible' => false,
            'label' => 'Foto bukti pembayaran',
            'description' => 'Kasir memotret bukti transfer/QRIS sebelum penjualan non-tunai ditutup.',
        ],

        // Aturan kerja, bukan kapabilitas modul: ia menahan tombol bayar sampai
        // tiap saran dijawab ([BL-025]). Tidak tampil karena akibatnya baru
        // terasa setelah ada saran yang muncul, dan di gerai dengan antrean
        // panjang ia racun.
        'upsell_mandatory' => [
            'column' => 'upsell_mandatory',
            'type' => 'boolean',
            'capability' => false,
            'visible' => false,
            'label' => 'Saran jual wajib dijawab',
            'description' => 'Tombol bayar tertahan sampai kasir menjawab setiap saran yang muncul.',
        ],

        // Aturan kerja ([BL-104]): mati berarti tombol "Tunda Bayar" hilang dan
        // server menolak tagihan terbuka baru. Tidak tampil, sama seperti
        // `upsell_mandatory` — pendaftar belum punya konteks untuk memutuskan
        // ini, dan ringkasan "Disetel otomatis" tetap menyebutnya.
        'open_bill' => [
            'column' => 'open_bill_enabled',
            'type' => 'boolean',
            'capability' => false,
            'visible' => false,
            'label' => 'Tagihan terbuka',
            'description' => 'Kasir boleh menyimpan pesanan dan menagihnya belakangan (Tunda Bayar).',
        ],

        // Setelan bernilai pilihan pertama yang ikut paket, dan alasan `type`
        // ada sama sekali ([BL-026]). Tampil, karena inilah yang paling terasa
        // bedanya antara warung menetap dan gerai acara.
        'order_identity_mode' => [
            'column' => 'order_identity_mode',
            'type' => 'choice',
            'capability' => false,
            'visible' => true,
            'label' => 'Identitas pesanan',
            'description' => 'Cara outlet mengenali pesanan saat dipanggil.',
        ],

    ],

    /*
    | Cara berjualan yang bisa dipilih, beserta keterangan yang membuatnya bisa
    | dikenali sendiri oleh pendaftar. Urutannya urutan tampil.
    */
    'styles' => [

        'warung_menetap' => [
            'label' => 'Warung / kafe menetap',
            'description' => 'Satu tempat tetap. Pelanggan makan di tempat atau bawa pulang, dan bisa dipanggil dengan nama.',
        ],

        'gerai_acara' => [
            'label' => 'Gerai acara & bazar',
            'description' => 'Berpindah-pindah di CFD, bazar, atau event. Antrean panjang, katalog pendek, semua lunas di tempat.',
        ],

        'toko_retail' => [
            'label' => 'Toko retail',
            'description' => 'Barang diserahkan saat itu juga. Tidak ada yang perlu diantre atau dipanggil.',
        ],

        'jasa' => [
            'label' => 'Jasa',
            'description' => 'Pekerjaan dikerjakan lalu ditagih. Tidak ada dapur dan tidak ada antrean pesanan.',
        ],

        'lainnya' => [
            'label' => 'Belum yakin',
            'description' => 'Setelan paling polos. Semuanya tetap bisa dinyalakan kapan saja dari Pengaturan.',
        ],

    ],

    /*
    | Cara berjualan → setelan awalnya.
    |
    | Kuncinya WAJIB mencakup seluruh `styles` di atas — satu cara berjualan
    | tanpa baris di sini akan mendarat tanpa setelan apa pun, dan itu lebih
    | buruk daripada keadaan sebelum paket ada. Ada ujinya.
    |
    | `features` menyalakan kapabilitas bertipe boolean; yang TIDAK disebut
    | berarti mati. `settings` memberi nilai untuk setelan bertipe lain; yang
    | tidak disebut dibiarkan pada bawaan kolomnya.
    |
    | `open_bill` menyala di setiap paket KECUALI gerai acara ([BL-104]):
    | kolomnya bawaan menyala, dan paket yang lupa menyebutnya akan diam-diam
    | mencabut tombol "Tunda Bayar" dari tenant yang tidak memintanya.
    |
    | `self_order` tidak menyala di mana pun, dan itu bukan kelalaian:
    | menyalakannya membuka tautan pemesanan yang bisa diakses siapa saja. Itu
    | keputusan yang harus diambil pemiliknya sendiri, bukan disimpulkan dari
    | cara ia berjualan.
    */
    'presets' => [

        // Dapur mengolah pesanan, dan pelanggannya duduk cukup lama untuk
        // dipanggil dengan nama.
        'warung_menetap' => [
            'features' => ['kitchen_queue', 'ai', 'open_bill'],
            'settings' => ['order_identity_mode' => Tenant::ORDER_IDENTITY_NAME],
        ],

        // Paket yang melahirkan `[BL-035]`. Kode panggil otomatis, bukan nama:
        // di antrean acara kasir tidak punya waktu mengetik apa pun, dan nomor
        // itulah penanda utamanya. `upsell_mandatory` ditegaskan mati walau
        // bawaannya memang mati — paket ini yang paling dirugikan olehnya, jadi
        // ia ditulis sebagai pernyataan, bukan diwariskan diam-diam.
        //
        // `open_bill` mati dengan alasan yang sama dan ditulis sama tegasnya
        // ([BL-104]): pelanggan acara pergi saat acara bubar, jadi hampir setiap
        // tagihan terbuka di sini berakhir sebagai kas negatif.
        'gerai_acara' => [
            'features' => ['kitchen_queue', 'ai'],
            'settings' => [
                'order_identity_mode' => Tenant::ORDER_IDENTITY_CODE,
                'upsell_mandatory' => false,
                'open_bill' => false,
            ],
        ],

        // Barang diserahkan saat itu juga — tidak ada yang perlu diantre.
        'toko_retail' => [
            'features' => ['ai', 'open_bill'],
            'settings' => ['order_identity_mode' => Tenant::ORDER_IDENTITY_NONE],
        ],

        'jasa' => [
            'features' => ['ai', 'open_bill'],
            'settings' => ['order_identity_mode' => Tenant::ORDER_IDENTITY_NONE],
        ],

        // Bawaan netral: cara berjualan yang belum dijawab tidak boleh membuat
        // aplikasi mengaku tahu cara kerja pemiliknya.
        'lainnya' => [
            'features' => ['ai', 'open_bill'],
            'settings' => ['order_identity_mode' => Tenant::ORDER_IDENTITY_NONE],
        ],

    ],

    /*
    | Jenis usaha → tebakan awal cara berjualan.
    |
    | Jembatan antara dua pertanyaan yang sengaja dipisah, dan HANYA tebakan:
    | pendaftar melihat cara berjualannya terisi lalu boleh menggantinya sebelum
    | lanjut. Gunanya dua — pertanyaan kedua tidak pernah tampil kosong, dan
    | klien yang cuma mengirim `business_type` (uji lama, permintaan langsung)
    | tetap mendarat dengan setelan yang masuk akal, bukan tanpa setelan.
    |
    | Tidak ada baris `bazar` di sini, dan itu disengaja: tidak ada jenis usaha
    | yang bisa menyimpulkan seseorang berjualan di acara. Gerai acara dipilih
    | orangnya sendiri di pertanyaan kedua.
    */
    'business_type_styles' => [
        'kuliner' => 'warung_menetap',
        'retail' => 'toko_retail',
        'jasa' => 'jasa',
        'lainnya' => 'lainnya',
    ],

    /*
    | Cara berjualan yang dipakai kalau jenis usahanya pun tidak dikenali.
    */
    'default_style' => 'lainnya',

];
