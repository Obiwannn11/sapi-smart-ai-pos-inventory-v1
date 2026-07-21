<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Katalog Modul Platform Console
    |--------------------------------------------------------------------------
    |
    | Satu sumber kebenaran untuk modul yang bisa diberikan ke akun platform.
    | Satu entri = satu izin "boleh membuka modul ini". Dipakai oleh:
    |   - EnsurePlatformModule    (gerbang route)
    |   - PlatformUserFactory     (state withAllModules)
    |   - HandleInertiaRequests   (share auth.platformUser.modules)
    |   - PlatformLayout.vue      (filter nav — via nama modul yang sama)
    |
    | Sengaja TERPISAH dari config/rbac.php: itu katalog modul operasional milik
    | tenant (POS, Stok, Produk) yang tak ada artinya di panel platform.
    |
    | Catatan: `tenants` (melihat daftar klien) sengaja dipisah dari
    | `revenue_data` (melihat omset klien jalur subsidi). Keduanya kewenangan
    | berbeda — kelak staf platform bisa diberi yang pertama tanpa yang kedua.
    |
    | 'available' => false menandai modul yang katalognya sudah ditetapkan tapi
    | halamannya belum dibuat (menyusul di Tahap B–D). Modul begini tidak bisa
    | dicentang: memberikan izin yang tidak berefek apa pun hanya menyesatkan
    | orang yang mengaturnya. Cukup ubah flag ini jadi true saat halamannya jadi.
    |
    | Manajemen akun platform sengaja TIDAK ada di daftar ini — ia dijaga
    | penanda `is_owner`, bukan modul grantable, agar staf platform tak bisa
    | mencentangkan modul sensitif untuk dirinya sendiri.
    |
    */

    'modules' => [
        'tenants' => ['label' => 'Daftar Tenant', 'sensitive' => false, 'available' => true],
        'subscriptions' => ['label' => 'Langganan', 'sensitive' => false, 'available' => false],
        'payments' => ['label' => 'Pembayaran', 'sensitive' => false, 'available' => false],
        'pricing_rules' => ['label' => 'Aturan Harga', 'sensitive' => true, 'available' => false],
        'revenue_data' => ['label' => 'Data Omset Subsidi', 'sensitive' => true, 'available' => false],
        'audit_logs' => ['label' => 'Log Audit', 'sensitive' => true, 'available' => false],
    ],

];
