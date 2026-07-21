<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Katalog Modul RBAC
    |--------------------------------------------------------------------------
    |
    | Satu sumber kebenaran untuk modul yang bisa di-grant ke staf. Satu entri =
    | satu spatie permission ("boleh membuka modul ini"). Dipakai oleh:
    |   - PermissionCatalogSeeder (seed permission global)
    |   - Owner\RoleController     (validasi + metadata UI)
    |   - HandleInertiaRequests    (share auth.user.permissions)
    |   - OwnerLayout.vue          (filter nav — via nama permission yang sama)
    |
    | 'sensitive' => true menandai modul yang di UI tidak dicentang secara
    | default saat owner membuat role (Keputusan B). Owner-eksklusif seperti
    | `settings` dan manajemen staf/role sengaja TIDAK ada di sini.
    |
    */

    'modules' => [
        'pos' => ['label' => 'Kasir (POS)', 'sensitive' => false],
        'cash_drawer' => ['label' => 'Sesi Kas', 'sensitive' => false],
        'products' => ['label' => 'Produk', 'sensitive' => false],
        'stock' => ['label' => 'Stok / Inventori', 'sensitive' => false],
        'reports' => ['label' => 'Laporan & Riwayat', 'sensitive' => false],
        'payment_methods' => ['label' => 'Metode Pembayaran', 'sensitive' => true],
        'ai_analysis' => ['label' => 'AI Analysis', 'sensitive' => true],
    ],

];
