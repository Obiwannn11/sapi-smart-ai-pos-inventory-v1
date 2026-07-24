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
    |   trial ──(trial_days habis)──> grace ──(grace_days habis)──> suspended
    |   active ──(periode lewat)────> grace ──(grace_days habis)──> suspended
    |
    | `grace` sengaja ada di antaranya: tenant yang lupa membayar kehilangan
    | kemampuan MENAMBAH data, bukan kemampuan MEMBACA data yang sudah ada.
    | Mengunci penjualan kemarin bukan alat penagihan yang sah.
    |
    */

    'trial_days' => 30,

    'grace_days' => 30,

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
    | `revenue_brackets` — omset bulanan → tarif. Sementara ini di config; Tahap
    | D memindahkannya ke tabel `pricing_rules` agar pemilik SaaS bisa mengubah
    | sendiri dari dashboard. `max` bernilai null berarti tanpa batas atas.
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

    'revenue_brackets' => [
        ['label' => 'A', 'min' => 0, 'max' => 2_000_000, 'price' => 10_000],
        ['label' => 'B', 'min' => 2_000_000, 'max' => 5_000_000, 'price' => 25_000],
        ['label' => 'C', 'min' => 5_000_000, 'max' => 15_000_000, 'price' => 50_000],
        ['label' => 'D', 'min' => 15_000_000, 'max' => null, 'price' => 100_000],
    ],

    'metrics_retention_months' => 24,

    'track_switch_minimum_months' => 3,

];
