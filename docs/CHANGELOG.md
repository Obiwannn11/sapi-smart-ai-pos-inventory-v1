# SAPI — Changelog & Revision Log

**Format:** Setiap perubahan yang dilakukan **di luar fase/phase** yang sudah direncanakan, atau perubahan keputusan arsitektur, WAJIB dicatat di file ini.

---

## Cara Menggunakan File Ini

### Kapan Harus Dicatat
- Perubahan skema database yang tidak ada di dokumen fase
- Perubahan keputusan arsitektur (auth method, status naming, dll)
- Hotfix atau patch di luar fase yang sedang berjalan
- Penambahan dependency baru yang tidak direncanakan
- Perubahan API contract / endpoint
- Bug fix kritis yang mengubah behavior

### Format Entry

```markdown
### [TIPE] Judul Singkat
- **Tanggal:** YYYY-MM-DD
- **Fase Terkait:** Phase-X / Di Luar Fase / Cross-Phase
- **Dampak:** Migration | Model | Controller | Service | Route | Frontend | Config
- **Breaking Change:** Ya / Tidak
- **Deskripsi:** Penjelasan singkat perubahan
- **Alasan:** Kenapa perubahan ini dilakukan
- **File Terdampak:**
  - `path/to/file.php` — deskripsi perubahan
- **Catatan Migrasi:** (jika ada) Instruksi khusus untuk apply perubahan
```

### Tipe Entry
| Tag | Keterangan |
|---|---|
| `[RECONCILE]` | Rekonsiliasi perbedaan antara dokumen v1.0 ↔ v1.1 ↔ diskusi |
| `[HOTFIX]` | Perbaikan mendesak di luar fase |
| `[DECISION]` | Perubahan keputusan arsitektur/teknis |
| `[ADDITION]` | Penambahan fitur/komponen yang tidak direncanakan |
| `[SCHEMA]` | Perubahan skema database |
| `[DEPRECATE]` | Fitur/approach yang ditinggalkan |

---

## Revision History

---

### [ADDITION] Laravel IDE Helper untuk Autocomplete IDE
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Di Luar Fase
- **Dampak:** Dependency | Config
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan package `barryvdh/laravel-ide-helper` (sebagai dependensi dev) untuk mengatasi issue property & magic method Laravel yang sering dianggap error oleh IDE/Editor (VS Code, PhpStorm).
- **Alasan:** Meningkatkan DX (Developer Experience) agar editor dapat mengenali model properti, facades, dan magic method Laravel lainnya dengan tepat.
- **File Terdampak:**
  - `composer.json` — Tambahan `barryvdh/laravel-ide-helper` di require-dev.
  - `.gitignore` — Ignore file hasil generate (`_ide_helper.php`, `.phpstorm.meta.php`, `_ide_helper_models.php`).
- **Catatan Migrasi:** Jika IDE masih complain, jalankan perintah `php artisan ide-helper:generate`, `php artisan ide-helper:meta`, dan `php artisan ide-helper:models -N`.

---

### [ADDITION] Integrasi Dependency Xendit SDK (Persiapan Payment Gateway)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Di Luar Fase
- **Dampak:** Dependency | Config
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan package `xendit/xendit-php` ke dependency aplikasi dan konfigurasi service `xendit` di `config/services.php` (`secret_key`, `webhook_token`).
- **Alasan:** Persiapan integrasi payment gateway Xendit untuk kebutuhan pembayaran digital dan webhook settlement.
- **File Terdampak:**
  - `composer.json` — tambah dependency `xendit/xendit-php`
  - `composer.lock` — lock file update setelah install package
  - `config/services.php` — tambah config `xendit.secret_key` dan `xendit.webhook_token`
- **Catatan Migrasi:** Tambahkan env `XENDIT_SECRET_KEY` dan `XENDIT_WEBHOOK_TOKEN` pada environment yang digunakan.

---

### [RECONCILE] Transaction Status Naming
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Cross-Phase (Phase-1 migration, Phase-3 logic)
- **Dampak:** Migration | Model | Service
- **Breaking Change:** Tidak (belum ada implementasi)
- **Deskripsi:** Status transaksi diubah dari v1.0 naming ke naming yang lebih jelas
- **Alasan:** `open/paid/cancelled` kurang deskriptif. `pending/completed/voided` lebih konsisten dengan industry standard dan lebih jelas untuk developer baru.
- **Perubahan:**
  | v1.0 (lama) | Final (baru) | Keterangan |
  |---|---|---|
  | `open` | `pending` | Transaksi dibuat, belum dibayar |
  | `paid` | `completed` | Pembayaran diterima, stok sudah dikurangi |
  | `cancelled` | `voided` | Transaksi dibatalkan |
- **File Terdampak:**
  - `database/migrations/xxxx_create_transactions_table.php` — ENUM values
  - `app/Models/Transaction.php` — status constants
  - `app/Services/TransactionService.php` — status transitions

---

### [RECONCILE] Stock Movement Type Naming
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Cross-Phase (Phase-1 migration, Phase-4 logic)
- **Dampak:** Migration | Model | Service
- **Breaking Change:** Tidak (belum ada implementasi)
- **Deskripsi:** Tipe stock movement diubah dari generic ke deskriptif
- **Alasan:** `in/out` terlalu generik, tidak langsung menjelaskan konteks bisnis. `sale/restock/adjustment` langsung menjelaskan alasan perubahan stok.
- **Perubahan:**
  | v1.0 (lama) | Final (baru) | Keterangan |
  |---|---|---|
  | `in` | `restock` | Barang masuk dari supplier/restock |
  | `out` | `sale` | Stok keluar karena transaksi penjualan |
  | `adjustment` | `adjustment` | Koreksi manual (positif/negatif) |
- **File Terdampak:**
  - `database/migrations/xxxx_create_stock_movements_table.php` — ENUM values
  - `app/Models/StockMovement.php` — type constants
  - `app/Services/StockService.php` — movement type logic

---

### [DECISION] Auth Method: Manual → Laravel Sanctum (SPA Mode)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation
- **Dampak:** Config | Middleware | Controller
- **Breaking Change:** Tidak (belum ada implementasi)
- **Deskripsi:** Mekanisme auth diubah dari full manual ke Laravel Sanctum SPA mode
- **Alasan:** Sanctum SPA mode tetap cookie-based (tidak perlu manage token), sudah terintegrasi dengan Laravel, dan kompatibel sempurna dengan Inertia.js. Lebih secure (CSRF protection built-in) tanpa overhead tambahan dibanding manual auth.
- **Implementasi:**
  - Install: `composer require laravel/sanctum` (sudah included di Laravel 12)
  - Config: `config/sanctum.php` — set stateful domains
  - Middleware: Sanctum middleware di `api` routes jika dibutuhkan nanti
  - Auth flow tetap session-based, Sanctum hanya menambah layer keamanan
- **File Terdampak:**
  - `config/sanctum.php` — konfigurasi
  - `app/Http/Controllers/Auth/AuthController.php` — auth logic
  - `bootstrap/app.php` — middleware registration

---

### [SCHEMA] Penambahan Tabel `cash_drawers`
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation (migration), Phase-3 POS (logic)
- **Dampak:** Migration | Model | Controller | Service
- **Breaking Change:** Tidak (tabel baru)
- **Deskripsi:** Tabel baru untuk sesi kas per shift kasir, sesuai v1.1 Section 1
- **Detail:** Lihat `docs/phases/PHASE-1_Foundation.md` — Section Database

---

### [SCHEMA] Penambahan Kolom `expiry_date` di `product_variants`
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation (migration), Phase-5 Badges (logic)
- **Dampak:** Migration | Model
- **Breaking Change:** Tidak (kolom baru, nullable)
- **Deskripsi:** Kolom expiry_date untuk mendukung badge "Potensi Expired", sesuai v1.1 Section 2

---

### [SCHEMA] Penambahan `deleted_at` (SoftDeletes) di 6 Tabel
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation
- **Dampak:** Migration | Model
- **Breaking Change:** Tidak (kolom baru, nullable)
- **Deskripsi:** SoftDeletes ditambahkan ke: `categories`, `products`, `product_variants`, `modifier_groups`, `modifiers`, `payment_methods`. Sesuai v1.1 Section 7.
- **Alasan:** Menjaga integritas data historis transaksi. Record yang dihapus user tidak benar-benar hilang dari database.

---

### [DECISION] `expected_amount` Cash Drawer = Semua Payment Method
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Service
- **Breaking Change:** Tidak
- **Deskripsi:** `expected_amount` di `cash_drawers` dihitung dari **semua** metode pembayaran (cash + QRIS + transfer), bukan hanya cash.
- **Alasan:** Keputusan bisnis — owner ingin melihat total expected revenue di sesi tersebut, bukan hanya uang fisik.
- **Catatan:** Di summary tutup kas, tetap tampilkan rekap per metode pembayaran agar owner bisa reconcile masing-masing channel.

---

### [ADDITION] Tenant Isolation via Global Scope + Trait
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-1 Foundation
- **Dampak:** Model | Trait
- **Breaking Change:** Tidak
- **Deskripsi:** Implementasi `BelongsToTenant` trait + `TenantScope` global scope untuk auto-filter & auto-assign tenant_id. Sesuai v1.1 Section 6.

---

### [ADDITION] Image Upload Service dengan Konversi WEBP
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-2 Master Data CRUD
- **Dampak:** Service | Config | Dependency
- **Breaking Change:** Tidak
- **Deskripsi:** ImageService untuk upload & konversi gambar produk ke WEBP. Dependency: `intervention/image`. Sesuai v1.1 Section 8.

---

### [ADDITION] Flash `lastTransaction` via Inertia untuk Receipt Modal
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Middleware | Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `flash.lastTransaction` di `HandleInertiaRequests` middleware agar data transaksi terakhir yang berhasil bisa dikirim ke frontend untuk ditampilkan di ReceiptModal (struk). Phase 3 doc tidak secara eksplisit mendefinisikan mekanisme passing data transaksi ke receipt.
- **Alasan:** Inertia redirect-back tidak bisa mengirim data object secara langsung. Flash session digunakan sebagai bridge untuk menampilkan struk setelah checkout sukses.
- **File Terdampak:**
  - `app/Http/Middleware/HandleInertiaRequests.php` — tambah `lastTransaction` di flash share
  - `app/Http/Controllers/Cashier/POSController.php` — `->with('lastTransaction', ...)` di store method

---

### [ADDITION] Redirect Tutup Kas ke Summary Page
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Route
- **Breaking Change:** Tidak
- **Deskripsi:** Setelah tutup kas, kasir langsung di-redirect ke halaman `CashDrawerSummary` (rekap sesi), bukan kembali ke halaman `CashDrawer.index`. Phase 3 doc menunjukkan redirect ke `cash-drawer.index`.
- **Alasan:** UX lebih baik — kasir langsung melihat rekap sesi lengkap (per payment method, selisih) setelah tutup kas.
- **File Terdampak:**
  - `app/Http/Controllers/Cashier/CashDrawerController.php` — `close()` redirect ke `cash-drawer.summary`

---

### [ADDITION] Transaction Count di Cash Drawer Summary
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `transactionCount` (jumlah transaksi completed dalam sesi) ke data yang dikirim ke `CashDrawerSummary.vue`. Tidak ada di Phase 3 doc.
- **Alasan:** Informasi tambahan yang berguna untuk rekap shift kasir.
- **File Terdampak:**
  - `app/Http/Controllers/Cashier/CashDrawerController.php` — `summary()` menghitung dan mengirim `transactionCount`

---

### [ADDITION] Dependency chart.js + vue-chartjs untuk Dashboard Chart
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-5 Dashboard, Laporan & Badge Helper
- **Dampak:** Frontend | Config
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `chart.js` dan `vue-chartjs` sebagai dependency npm untuk menampilkan bar chart trend pendapatan 7 hari di halaman Dashboard owner. Phase 5 doc menyebutkan chart sebagai "opsional, bisa pakai chart.js" tanpa mendefinisikan dependency secara eksplisit.
- **Alasan:** Dipilih atas permintaan user. Chart.js + vue-chartjs ringan dan memberikan visualisasi data yang interaktif.
- **File Terdampak:**
  - `package.json` — tambah dependency `chart.js`, `vue-chartjs`
  - `resources/js/Components/DailyChart.vue` — komponen chart baru

---

### [ADDITION] Navigasi Sidebar Owner Ditambah Menu Phase 5
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-5 Dashboard, Laporan & Badge Helper
- **Dampak:** Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan 3 menu navigasi baru di sidebar OwnerLayout: Laporan Harian, Transaksi, Sesi Kas. Serta menambahkan 3 ikon SVG baru (report, receipt, cash) untuk navigasi tersebut. Phase 5 doc tidak secara eksplisit mendefinisikan perubahan pada layout/navigasi.
- **Alasan:** Halaman-halaman baru Phase 5 perlu bisa diakses via navigasi sidebar agar UX konsisten dengan halaman owner lainnya.
- **File Terdampak:**
  - `resources/js/Layouts/OwnerLayout.vue` — tambah navigation items + ikon SVG

---

### [HOTFIX] BadgeHelperService Dead Stock Query Bug
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-6 Testing & QA (ditemukan saat testing)
- **Dampak:** Service
- **Breaking Change:** Tidak
- **Deskripsi:** Query dead stock badge menggunakan `transactionItems.created_at` yang tidak ada (tabel `transaction_items` memiliki `$timestamps = false`). Diperbaiki dengan query melalui relasi `transactionItems.transaction` dan filter berdasarkan `transactions.created_at` serta `transactions.status = completed`.
- **Alasan:** Bug — query selalu gagal karena kolom `created_at` tidak ada di tabel `transaction_items`, sehingga badge dead stock tidak pernah akurat.
- **File Terdampak:**
  - `app/Services/BadgeHelperService.php` — perbaikan `whereDoesntHave` query dari `transactionItems` ke `transactionItems.transaction`

---

### [HOTFIX] CategoryController Soft Delete Tidak Nullify product.category_id
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-6 Testing & QA (ditemukan saat testing)
- **Dampak:** Controller
- **Breaking Change:** Tidak
- **Deskripsi:** Komentar di controller mengklaim `nullOnDelete` DB constraint akan handle nullify `category_id` produk saat kategori dihapus. Namun soft delete tidak memicu DB-level foreign key constraint. Diperbaiki dengan menambahkan manual `$category->products()->update(['category_id' => null])` sebelum soft delete.
- **Alasan:** Bug — produk tetap mereferensi kategori yang sudah di-soft-delete, menyebabkan data inkonsisten.
- **File Terdampak:**
  - `app/Http/Controllers/Owner/CategoryController.php` — tambah manual nullify sebelum `$category->delete()`

---

### [SCHEMA] Penambahan 'void' di ENUM stock_movements.type (Create Migration)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-6 Testing & QA (fix untuk SQLite test compatibility)
- **Dampak:** Migration
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `'void'` ke ENUM di migration create `stock_movements` agar SQLite CHECK constraint mengizinkan tipe void. Migration ALTER tetap ada untuk MySQL production yang sudah menjalankan migration create sebelumnya.
- **Alasan:** SQLite (test database) membuat CHECK constraint dari ENUM. Migration ALTER yang skip SQLite menyebabkan 'void' tidak dikenal di test environment.
- **File Terdampak:**
  - `database/migrations/2026_03_06_000015_create_stock_movements_table.php` — ENUM values ditambah `'void'`
  - `database/migrations/2026_03_06_100002_add_void_type_to_stock_movements.php` — tetap ada driver-aware skip untuk SQLite

---

### [ADDITION] HasFactory Trait pada Semua Model
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-6 Testing & QA
- **Dampak:** Model
- **Breaking Change:** Tidak
- **Deskripsi:** Menambahkan `HasFactory` trait ke semua model yang belum memilikinya: `Transaction`, `CashDrawer`, `StockMovement`, `Tenant`, `Category`, `Product`, `ProductVariant`, `ModifierGroup`, `Modifier`, `PaymentMethod`, `TransactionPayment`. Diperlukan agar `Model::factory()` dapat digunakan di test suite.
- **Alasan:** Prasyarat untuk factory-based testing di Pest PHP.
- **File Terdampak:**
  - `app/Models/Transaction.php` — tambah `use HasFactory`
  - `app/Models/CashDrawer.php` — tambah `use HasFactory`
  - `app/Models/StockMovement.php` — tambah `use HasFactory`
  - (dan 8 model lain yang sudah ditambahkan sebelumnya)

---

### [ADDITION] Fitur Open Bill (Simpan Pesanan Tanpa Bayar)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Service | Route | Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Kasir kini bisa menyimpan pesanan sebagai "Open Bill" (status `pending`) tanpa langsung membayar. Stok dikurangi saat bill dibuat untuk mencegah overselling. Pembayaran dilakukan kemudian dari panel Open Bill di halaman POS. Phase 3 doc tidak mendefinisikan fitur open bill.
- **Alasan:** Permintaan user — di banyak skenario F&B, pelanggan pesan dulu dan bayar belakangan (makan di tempat, tab, dll). Stok tetap dikurangi di awal agar tidak terjual ganda.
- **File Terdampak:**
  - `app/Services/TransactionService.php` — `checkout()` menerima flag `is_open_bill`; method baru `payOpenBill()` untuk menyelesaikan pembayaran
  - `app/Http/Requests/StoreTransactionRequest.php` — validasi `payments` jadi nullable + field `is_open_bill`
  - `app/Http/Controllers/Cashier/POSController.php` — method `payOpenBill()`, load `openBills` untuk frontend
  - `routes/web.php` — route `POST /cashier/transactions/{transaction}/pay`
  - `resources/js/Pages/Cashier/POS.vue` — panel Open Bill, tombol "Open Bill", flow pembayaran pending

---

### [SCHEMA] Penambahan Kolom `notes` di `transaction_items`
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Migration | Model
- **Breaking Change:** Tidak (kolom baru, nullable)
- **Deskripsi:** Kolom `notes` (text, nullable) ditambahkan ke tabel `transaction_items` untuk menyimpan catatan per item pesanan (contoh: "less sugar", "no ice"). Tidak ada di Phase 3 doc.
- **Alasan:** Permintaan user — support catatan khusus per item pesanan, umum di bisnis F&B.
- **File Terdampak:**
  - `database/migrations/2026_03_06_200001_add_notes_to_transaction_items_table.php` — migration baru
  - `app/Models/TransactionItem.php` — `notes` ditambahkan ke `$fillable`
- **Catatan Migrasi:** Jalankan `php artisan migrate` untuk menambah kolom.

---

### [ADDITION] Catatan (Notes) per Item di Halaman POS
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Frontend | Service
- **Breaking Change:** Tidak
- **Deskripsi:** Setiap item di cart kini bisa memiliki catatan individual. Item dengan produk & modifier sama tapi catatan berbeda ditampilkan sebagai baris terpisah di cart. Catatan ditampilkan di struk (ReceiptModal). Phase 3 doc tidak mendefinisikan fitur notes.
- **Alasan:** Permintaan user — catatan per item umum di POS F&B untuk instruksi khusus dapur/barista.
- **File Terdampak:**
  - `resources/js/Components/CartItem.vue` — toggle notes + input text per item
  - `resources/js/Pages/Cashier/POS.vue` — dedup cart mempertimbangkan notes, handler `updateCartNotes()`
  - `resources/js/Components/ReceiptModal.vue` — tampilkan notes item di struk
  - `app/Services/TransactionService.php` — simpan `notes` per item ke database

---

### [ADDITION] Halaman Riwayat Transaksi Kasir
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Route | Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Halaman baru `Cashier/TransactionHistory` untuk kasir melihat riwayat transaksi mereka sendiri, dengan filter status dan tanggal, serta tombol cetak ulang struk. Phase 3 doc tidak mendefinisikan halaman history khusus untuk kasir.
- **Alasan:** Permintaan user — kasir perlu bisa melihat & mencetak ulang struk transaksi sebelumnya tanpa harus minta akses owner.
- **File Terdampak:**
  - `app/Http/Controllers/Cashier/POSController.php` — method `history()` dengan filter & pagination
  - `routes/web.php` — route `GET /cashier/transactions`
  - `resources/js/Pages/Cashier/TransactionHistory.vue` — halaman baru
  - `resources/js/Pages/Cashier/POS.vue` — link "Riwayat" di top bar

---

### [DECISION] Reversal: `expected_amount` Cash Drawer = Cash Only (Bukan Semua Payment)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Controller | Frontend
- **Breaking Change:** Ya (mengubah behavior kalkulasi expected_amount)
- **Deskripsi:** **Membalik keputusan sebelumnya.** `expected_amount` di `cash_drawers` kini dihitung hanya dari pembayaran tunai (cash) dikurangi kembalian, bukan dari semua metode pembayaran. Formula baru: `opening_amount + Σ(cash payments) - Σ(change given)`.
- **Alasan:** Permintaan user — `expected_amount` seharusnya merepresentasikan uang fisik yang diharapkan ada di laci kas. Pembayaran QRIS dan transfer tidak masuk ke laci kas fisik. Keputusan sebelumnya (`expected_amount = semua payment method`) dibatalkan.
- **File Terdampak:**
  - `app/Http/Controllers/Cashier/CashDrawerController.php` — `close()` query hanya `TransactionPayment` with `paymentMethod.type = 'cash'`, kurangi `change_amount`
  - `resources/js/Pages/Cashier/CashDrawerSummary.vue` — label diubah dari "Expected Amount" ke "Expected Cash (uang tunai di laci)"

---

### [ADDITION] Perbaikan Payment Modal (Lebar, Sticky Total, Denominasi, Format Angka)
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-3 POS & Transactions
- **Dampak:** Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Beberapa perbaikan UX pada PaymentModal: (1) Modal diperlebar dari `max-w-md` ke `max-w-lg`, (2) Total Belanja menjadi sticky headline yang selalu terlihat saat scroll, (3) Tombol denominasi: "Uang Pas" + "+20rb" + "+50rb" + "+100rb" yang kumulatif, (4) Input amount menggunakan `type="text" inputmode="numeric"` dengan auto-format ribuan (titik).
- **Alasan:** Permintaan user — modal terlalu kecil, total tidak terlihat saat scroll, dan kasir butuh shortcut denominasi untuk mempercepat proses pembayaran.
- **File Terdampak:**
  - `resources/js/Components/PaymentModal.vue` — semua perubahan di atas

---

### [ADDITION] Sidebar Owner Grouped dengan Dropdown Collapsible
- **Tanggal:** 2026-03-06
- **Fase Terkait:** Phase-5 Dashboard, Laporan & Badge Helper
- **Dampak:** Frontend
- **Breaking Change:** Tidak
- **Deskripsi:** Navigasi sidebar OwnerLayout diubah dari flat list ke grouped sections: Dashboard (standalone), "Atur Menu" (Kategori, Produk, Stok, Modifier), "Keuangan" (Laporan Harian, Transaksi, Sesi Kas, Pembayaran). Setiap group memiliki dropdown collapsible dengan chevron animasi. Group label ter-highlight saat salah satu child-nya aktif. Juga memperbaiki issue sidebar yang memiliki empty space di bawah yang bisa di-scroll.
- **Alasan:** Permintaan user — navigasi flat terlalu panjang dan sulit dinavigasi. Grouping membantu organisasi menu berdasarkan domain bisnis. Perbaikan scroll menghilangkan empty space yang tidak perlu.
- **File Terdampak:**
  - `resources/js/Layouts/OwnerLayout.vue` — rewrite navigasi dari flat array ke `sidebarGroups` array, collapsible dropdown per group, `overflow-y-auto` hanya pada nav

---

## Template Entry Kosong (Copy-Paste)

```markdown
### [TIPE] Judul Singkat
- **Tanggal:** YYYY-MM-DD
- **Fase Terkait:** Phase-X / Di Luar Fase / Cross-Phase
- **Dampak:** Migration | Model | Controller | Service | Route | Frontend | Config
- **Breaking Change:** Ya / Tidak
- **Deskripsi:** 
- **Alasan:** 
- **File Terdampak:**
  - `path/to/file` — deskripsi
- **Catatan Migrasi:** (opsional)
```
