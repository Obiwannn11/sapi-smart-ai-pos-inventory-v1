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

];
