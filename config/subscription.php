<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Siklus Hidup Langganan
    |--------------------------------------------------------------------------
    |
    | Angka-angka yang menentukan kapan tenant berpindah keadaan. Ditaruh di
    | config, bukan sebagai konstanta di kode, karena keduanya adalah kebijakan
    | komersial yang wajar berubah — dan mengubah kebijakan sebaiknya tidak
    | menuntut membaca kelas mana pun.
    |
    | Alurnya:
    |
    |   trial ──(trial_months habis)──> grace ──(grace_days habis)──> suspended
    |   active ──(periode lewat)──────> grace ──(grace_days habis)──> suspended
    |
    | `grace` sengaja ada di antaranya, dan sejak keputusan pemilik 2026-08-07 ia
    | BERTINGKAT. Prinsip lamanya — tenggat mencabut kemampuan menulis sejak hari
    | pertama — dicabut sendiri: warung yang tidak bisa berjualan tidak punya
    | uang untuk membayar, jadi mematikan kasirnya di hari pertama menagih dengan
    | merusak sumber pembayarannya. Yang menggantikannya adalah tangga tekanan
    | yang naik pelan, dengan kasir tetap hidup sampai dua pertiga tenggat lewat.
    | Yang TIDAK pernah dicabut di tahap mana pun: membaca data yang sudah ada.
    |
    | Ketiga angka di bawah menggambarkan satu tangga, jadi ketiganya tinggal
    | berdampingan di sini — bukan satu di config dan dua tertanam di middleware.
    | Kebijakan tenggat harus bisa diubah tanpa membaca kelas mana pun.
    |
    */

    /**
     * Panjang masa gratis tenant baru, dalam BULAN — bukan hari.
     *
     * Bulan, karena jangkar tanggal tagih diambil dari tanggal daftar: yang
     * daftar tanggal 7 ditagih tiap tanggal 7. Menghitungnya dalam hari
     * menggeser jangkarnya (60 hari dari 7 Agustus mendarat di 6 Oktober), dan
     * pergeseran itu permanen karena jangkar hanya ditulis sekali.
     */
    'trial_months' => 2,

    'grace_days' => 30,

    /**
     * Hari tenggat ke berapa notifikasi berubah dari pemberitahuan biasa menjadi
     * peringatan yang sengaja mengganggu. Hari 1 sampai sehari sebelum ini
     * bersikap halus.
     */
    'grace_intensive_from_day' => 15,

    /**
     * Hari tenggat ke berapa tenant kehilangan kemampuan menulis — termasuk
     * mencatat transaksi kasir. Sesudah ini yang tersisa hanya membaca:
     * dashboard tetap terbuka, menu lain menampilkan halaman "selesaikan
     * tagihan dulu" beserta tautan ke pembayaran.
     *
     * Inilah angka yang benar-benar menentukan nasib tenant, bukan `grace_days`
     * — sesudah hari ini ia sudah tidak bisa berdagang, dan sisa harinya tinggal
     * ruang tunggu. Keduanya harus dibaca bersama.
     */
    'grace_lock_from_day' => 20,

    /**
     * Berapa hari sebelum periode berakhir tagihan periode berikutnya terbit.
     *
     * Tagihan yang terbit tepat di hari periodenya habis sampai bersamaan
     * dengan hilangnya kemampuan menulis — tenant membaca angkanya dan
     * mendapati aplikasinya sudah setengah terkunci di menit yang sama.
     * Menerbitkannya lebih awal membuat "berapa yang harus dibayar" tiba
     * sebagai pemberitahuan, bukan sebagai penjelasan setelah kejadian.
     */
    'invoice_lead_days' => 7,

    /**
     * Umur minimum tenant terbengkalai sebelum boleh dipangkas, dalam hari.
     *
     * "Terbengkalai" bermakna sempit dan sengaja: pemiliknya tidak pernah
     * memverifikasi alamat surelnya, DAN tidak ada satu pun transaksi. Dua
     * syarat itu bersama-sama berarti akunnya tidak pernah benar-benar dipakai.
     *
     * Pemangkasannya TIDAK dijadwalkan — lihat `platform:prune-abandoned-tenants`.
     */
    'abandoned_after_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | Dokumen Persetujuan
    |--------------------------------------------------------------------------
    |
    | Satu dokumen per jalur harga — sengaja terpisah, bukan satu dokumen dengan
    | pasal bersyarat. Dokumen bersyarat justru mengaburkan hal terpentingnya:
    | jalur normal tidak membuka data bisnis sama sekali, jalur subsidi membuka
    | omset. Dipisah membuat masing-masing pendek dan benar-benar terbaca.
    |
    | Teksnya tinggal di `resources/consents/` dan TIDAK PERNAH disunting di
    | tempat. Versi baru = berkas baru + naikkan `version` di sini. Menyunting
    | teks yang sudah disetujui orang akan membuat catatan persetujuannya
    | menunjuk ke kalimat yang tidak pernah mereka baca.
    |
    */

    'consents' => [
        'normal' => ['version' => '1', 'file' => 'normal-v1.md'],
        'subsidized' => ['version' => '1', 'file' => 'subsidized-v1.md'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Jalur Subsidi UMKM
    |--------------------------------------------------------------------------
    |
    | `revenue_brackets` — SUDAH TIDAK DIBACA aplikasi. Sumber aturan harga kini
    | tabel `pricing_rules`, yang di-CRUD pemilik SaaS dari platform console.
    | Daftar di bawah tinggal sebagai benih migrasi `create_pricing_rules_table`
    | — dibiarkan agar pemasangan baru tetap punya bracket awal yang masuk akal.
    | Mengubah angka di sini tidak berpengaruh apa pun pada pemasangan yang
    | tabelnya sudah terisi.
    |
    | `metrics_retention_months` — omset lebih tua dari ini dipangkas. Dua tahun
    | cukup untuk membuktikan penetapan harga bila disengketakan; lebih dari itu
    | hanya menumpuk data yang harus dijaga tanpa ada yang membacanya.
    |
    | `track_switch_minimum_months` — jarak minimum antar perpindahan jalur.
    | Tanpa jarak ini, tenant bisa pindah ke subsidi tiap bulan sepi lalu balik
    | ke normal, dan perhitungan bracket kehilangan artinya.
    |
    */

    /**
     * Tangga diskon Harga Adaptif, sebagai benih pemasangan baru.
     *
     * Dibaca sebagai persentase potongan terhadap `paid-1`, bukan sebagai daftar
     * harga yang berdiri sendiri — itulah arti "Adaptif = paid 1 yang didiskon"
     * (keputusan pemilik 2026-08-07): 90% / 75% / 50% / 25%, lalu berhenti.
     *
     * Bracket D dulu Rp 100.000 tanpa batas atas, yang berarti diskon **0%**:
     * tenant menyerahkan data penjualannya dan tidak menerima apa pun. Sekarang
     * ia diskon 25% dan tangganya ditutup di Rp 50 juta — di atas itu tidak ada
     * keringanan lagi, dan tenant dipindahkan ke `paid-1` harga penuh.
     */
    'revenue_brackets' => [
        ['label' => 'A', 'min' => 0, 'max' => 2_000_000, 'price' => 10_000],
        ['label' => 'B', 'min' => 2_000_000, 'max' => 5_000_000, 'price' => 25_000],
        ['label' => 'C', 'min' => 5_000_000, 'max' => 15_000_000, 'price' => 50_000],
        ['label' => 'D', 'min' => 15_000_000, 'max' => 50_000_000, 'price' => 75_000],
    ],

    'metrics_retention_months' => 24,

    'track_switch_minimum_months' => 3,

];
