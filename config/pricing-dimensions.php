<?php

use App\Models\TenantConsent;
use App\Services\Pricing\ActiveSeatsResolver;
use App\Services\Pricing\BusinessTypeResolver;
use App\Services\Pricing\MonthlyRevenueResolver;
use App\Services\Pricing\TransactionCountResolver;

return [

    /*
    |--------------------------------------------------------------------------
    | Katalog Dimensi Harga
    |--------------------------------------------------------------------------
    |
    | Kategori yang BOLEH menentukan harga langganan. Ambang dan tarifnya bebas
    | sepenuhnya — pemilik SaaS menyusunnya dari `/platform/pricing-rules` tanpa
    | rilis. Yang tidak bisa dibebaskan adalah daftar di bawah ini, dan itu
    | bukan kompromi yang bisa dihindari: tiap dimensi butuh SUMBER ANGKANYA.
    | Nilai yang tidak bisa dihitung aplikasi tidak akan pernah bisa jadi dasar
    | harga, sebebas apa pun panelnya.
    |
    | Daftar ini tumbuh sekali per JENIS DATA, bukan sekali per skema harga.
    |
    | Setiap entri:
    |
    |   label            Nama yang dibaca manusia di panel.
    |   type             `metric`   — angka yang dihitung aplikasi.
    |                    `attribute`— nilai yang harus ditanyakan, bukan dihitung.
    |                    Menentukan operator mana yang masuk akal.
    |   unit             `currency` | `number` | `text` — hanya soal tampilan.
    |   requires_consent Jenis persetujuan yang WAJIB aktif sebelum nilainya
    |                    boleh dihitung, atau null bila tidak membuka apa pun.
    |                    Inilah batas `[BL-005]` yang ditegakkan di kode:
    |                    dimensi bercatatan consent tidak akan pernah punya
    |                    nilai untuk tenant jalur normal, sehingga aturan yang
    |                    memakainya tidak pernah cocok untuk mereka.
    |   options          Untuk `attribute`: nilai sah yang boleh dipilih.
    |   resolver         Kelas yang tahu cara mengambil angkanya.
    |
    | MENAMBAH DIMENSI BARU — yang wajib dijawab lebih dulu:
    | apakah nilainya membuka detail bisnis klien? "Jumlah item terjual" dan
    | "rata-rata nilai transaksi" terdengar tidak berbahaya, padahal keduanya
    | membuka lebih dalam daripada omzet bulanan yang kini disetujui klien.
    | Kalau ya, ia butuh `requires_consent` DAN teks consent-nya naik versi —
    | menambahkannya diam-diam ke dimensi ber-consent yang sudah ada berarti
    | mengambil data yang tak pernah disetujui siapa pun.
    |
    */

    'monthly_revenue' => [
        'label' => 'Omzet bulanan',
        'type' => 'metric',
        'unit' => 'currency',
        // Dibaca dari `tenant_monthly_metrics`, satu-satunya jalan data
        // penjualan tenant sampai ke pemilik SaaS.
        'requires_consent' => TenantConsent::TYPE_SUBSIDIZED,
        'resolver' => MonthlyRevenueResolver::class,
    ],

    'transaction_count' => [
        'label' => 'Jumlah transaksi/bulan',
        'type' => 'metric',
        'unit' => 'number',
        // Kolom `transaction_count` SUDAH dikumpulkan job penghitung omzet dan
        // SUDAH tercakup dokumen consent subsidi yang berlaku — tidak ada data
        // baru yang diambil, dan tidak ada versi consent yang perlu naik.
        'requires_consent' => TenantConsent::TYPE_SUBSIDIZED,
        'resolver' => TransactionCountResolver::class,
    ],

    'active_seats' => [
        'label' => 'Pengguna aktif',
        'type' => 'metric',
        'unit' => 'number',
        // Hanya `count()` di tabel `users`. Tidak menyentuh penjualan, produk,
        // maupun stok — jadi berlaku penuh untuk KEDUA jalur harga, termasuk
        // tenant jalur normal yang tidak membuka apa pun.
        'requires_consent' => null,
        'resolver' => ActiveSeatsResolver::class,
    ],

    'business_type' => [
        'label' => 'Tipe usaha',
        'type' => 'attribute',
        'unit' => 'text',
        'requires_consent' => null,
        'options' => [
            'kuliner' => 'Kuliner / F&B',
            'retail' => 'Retail / Toko',
            'jasa' => 'Jasa',
            'lainnya' => 'Lainnya',
        ],
        'resolver' => BusinessTypeResolver::class,
    ],

];
