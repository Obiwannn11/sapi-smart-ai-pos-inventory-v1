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
    */

    'modules' => [
        'tenants' => ['label' => 'Daftar Tenant', 'sensitive' => false],
        'subscriptions' => ['label' => 'Langganan', 'sensitive' => false],
        'payments' => ['label' => 'Pembayaran', 'sensitive' => false],
        'pricing_rules' => ['label' => 'Aturan Harga', 'sensitive' => true],
        'revenue_data' => ['label' => 'Data Omset Subsidi', 'sensitive' => true],
        'audit_logs' => ['label' => 'Log Audit', 'sensitive' => true],
    ],

];
