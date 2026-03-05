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
