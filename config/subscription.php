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
    ],

];
