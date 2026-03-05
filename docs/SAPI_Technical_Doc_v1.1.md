# Technical Documentation Addendum — SAPI (Smart Inventory POS)
**Versi:** 1.1  
**Tanggal:** Maret 2026  
**Status:** Revisi dari diskusi review v1.0  

---

## 1. Tabel Baru: `cash_drawers`

Tabel ini menyimpan sesi kas per shift kasir. Setiap kali kasir buka kas, satu record dibuat. Saat tutup kas, record di-update dengan data penutupan.

```sql
cash_drawers
─────────────────────────────────────────────
id                  BIGINT PK
tenant_id           BIGINT FK → tenants.id
user_id             BIGINT FK → users.id         -- kasir yang buka
opening_amount      DECIMAL(12,2)                 -- saldo awal laci
closing_amount      DECIMAL(12,2) NULLABLE        -- saldo akhir laci (NULL jika belum ditutup)
expected_amount     DECIMAL(12,2) NULLABLE        -- kalkulasi sistem dari total transaksi tunai
difference          DECIMAL(12,2) NULLABLE        -- selisih aktual vs expected
notes               TEXT NULLABLE
opened_at           TIMESTAMP                     -- waktu buka kas
closed_at           TIMESTAMP NULLABLE            -- waktu tutup kas (NULL jika masih open)
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

**Relasi:**
- `cash_drawers` terhubung ke `tenants` dan `users`
- Rekap per metode pembayaran dihitung dari `transaction_payments` yang `created_at`-nya berada di antara `opened_at` dan `closed_at`

**Urutan migrasi:** Tambahkan `create_cash_drawers_table` setelah `create_users_table` (posisi ke-3, sebelum `create_categories_table`), atau di akhir urutan migrasi yang sudah ada (posisi ke-15).

**Constraint:** Satu kasir hanya boleh punya **satu** sesi kas terbuka (`closed_at IS NULL`) pada satu waktu. Validasi ini dilakukan di level aplikasi (controller/service), bukan database constraint.

---

## 2. Kolom Baru: `expiry_date` di `product_variants`

Untuk mendukung Badge "Potensi Expired" yang sudah ada di PRD.

```sql
-- Tambahkan kolom ke product_variants
expiry_date         DATE NULLABLE                 -- tanggal kedaluwarsa batch terakhir
```

**Catatan penting:**
- Field ini **nullable** karena tidak semua produk punya expiry (contoh: retail fashion)
- Untuk MVP, ini adalah **tanggal expiry batch terakhir** yang diinput manual oleh owner saat restock
- Sistem tracking expiry per-batch (FIFO/FEFO) ditunda ke fase berikutnya karena kompleksitasnya tidak sebanding dengan nilai MVP
- Badge "Potensi Expired" mengecek: `expiry_date IS NOT NULL AND expiry_date <= NOW() + INTERVAL 7 DAY AND stock > 0`

---

## 3. Perbaikan: `BadgeHelperService` — Query Melalui Relasi Product

`product_variants` tidak punya kolom `tenant_id` langsung. Semua query badge harus melalui relasi `products.tenant_id`.

```php
// BadgeHelperService.php — PERBAIKAN

public function generate(Tenant $tenant): array
{
    $badges = [];

    // Scope helper: semua variant milik tenant ini
    $variantScope = ProductVariant::whereHas('product', function ($q) use ($tenant) {
        $q->where('tenant_id', $tenant->id);
    });

    // Rule 1: Stok kritis (≤ 5, belum habis)
    $lowStock = (clone $variantScope)
        ->where('stock', '<=', 5)
        ->where('stock', '>', 0)
        ->with('product:id,name')
        ->get();

    // Rule 2: Dead stock (0 penjualan dalam 30 hari, stok > 0)
    $deadStock = (clone $variantScope)
        ->where('stock', '>', 0)
        ->whereDoesntHave('transactionItems', function ($q) {
            $q->where('created_at', '>=', now()->subDays(30));
        })
        ->with('product:id,name')
        ->get();

    // Rule 3: Stok habis
    $outOfStock = (clone $variantScope)
        ->where('stock', '<=', 0)
        ->with('product:id,name')
        ->get();

    // Rule 4: Potensi expired (expiry_date ≤ 7 hari ke depan, stok > 0)
    $nearExpiry = (clone $variantScope)
        ->whereNotNull('expiry_date')
        ->where('expiry_date', '<=', now()->addDays(7))
        ->where('stock', '>', 0)
        ->with('product:id,name')
        ->get();

    // Konstruksi badge array...
    return $badges;
}
```

**Catatan:** `ProductVariant` model harus punya relasi:
```php
// ProductVariant.php
public function product(): BelongsTo
{
    return $this->belongsTo(Product::class);
}

public function transactionItems(): HasMany
{
    return $this->hasMany(TransactionItem::class);
}
```

---

## 4. Race Condition — Strategi Stok

**Konteks:** Dalam praktik di lapangan (cafe/coffee shop), hanya ada 1 kasir aktif per waktu. Owner bisa mengakses POS (`role:cashier,owner`), tapi dalam operasional nyata, owner berada di dashboard dan tidak melakukan transaksi bersamaan.

**Strategi:**
- Untuk MVP, **tidak diperlukan** pessimistic/optimistic locking yang kompleks
- Namun, sebagai minimum safety net, gunakan **`DB::transaction()`** yang sudah ada di `TransactionService` — ini mencegah partial write
- Tambahkan check sederhana di dalam transaction block:

```php
// Di dalam DB::transaction() pada TransactionService.php
foreach ($data['items'] as $item) {
    $variant = ProductVariant::lockForUpdate()->find($item['variant_id']);
    
    if ($variant->stock < $item['qty']) {
        throw new \Exception("Stok {$variant->name} tidak cukup.");
    }
    
    // ... lanjut proses
}
```

**`lockForUpdate()`** memastikan row tidak bisa dibaca oleh transaksi lain sampai commit. Ini ringan, sudah bawaan Laravel/MySQL, dan cukup untuk skenario di mana concurrent checkout sangat jarang terjadi.

**Catatan untuk fase berikutnya:** Jika SAPI scale ke multi-outlet atau multi-kasir per outlet, evaluasi ulang strategi ini — bisa pertimbangkan optimistic locking dengan versioning atau queue-based checkout.

---

## 5. Generate Transaction Code

Format: `TRX-YYYYMMDD-XXX` (contoh: `TRX-20260305-001`)

**Implementasi: Auto-increment counter per hari per tenant.**

```php
// TransactionService.php — di dalam DB::transaction()

private function generateTransactionCode(int $tenantId): string
{
    $today = now()->format('Ymd');
    
    $lastTransaction = Transaction::where('tenant_id', $tenantId)
        ->where('code', 'like', "TRX-{$today}-%")
        ->lockForUpdate()
        ->orderByDesc('code')
        ->first();

    if ($lastTransaction) {
        $lastNumber = (int) Str::afterLast($lastTransaction->code, '-');
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    return sprintf("TRX-%s-%03d", $today, $nextNumber);
}
```

**Catatan:**
- `lockForUpdate()` mencegah dua transaksi mengambil nomor urut yang sama secara bersamaan
- Format `%03d` → 3 digit (001, 002, ... 999). Jika butuh lebih dari 999 transaksi per hari, ubah ke `%04d`
- Kolom `code` di tabel `transactions` sudah `UNIQUE`, jadi database akan reject duplikat sebagai safety net terakhir

---

## 6. Tenant Isolation — Implementasi Global Scope

**Pendekatan: Automatic Global Scope pada semua model yang punya `tenant_id`.**

### 6.1 Trait `BelongsToTenant`

```php
// app/Traits/BelongsToTenant.php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // Auto-filter: semua query hanya ambil data tenant yang sedang login
        static::addGlobalScope(new TenantScope());

        // Auto-assign: setiap record baru otomatis diisi tenant_id
        static::creating(function (Model $model) {
            if (auth()->check() && !$model->tenant_id) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }
}
```

### 6.2 TenantScope

```php
// app/Models/Scopes/TenantScope.php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (auth()->check()) {
            $builder->where($model->getTable() . '.tenant_id', auth()->user()->tenant_id);
        }
    }
}
```

### 6.3 Penggunaan di Model

```php
// Semua model yang punya tenant_id WAJIB pakai trait ini
class Product extends Model
{
    use BelongsToTenant;
}

class Category extends Model
{
    use BelongsToTenant;
}

class Transaction extends Model
{
    use BelongsToTenant;
}

// dst: ModifierGroup, PaymentMethod, StockMovement, CashDrawer
```

**Model yang TIDAK pakai trait ini:**
- `Tenant` — model tenant itu sendiri
- `User` — filter-nya lewat `tenant_id` langsung, bukan scope (karena auth belum aktif saat login)
- `ProductVariant`, `Modifier`, `TransactionItem`, `TransactionPayment`, `TransactionItemModifier` — tidak punya `tenant_id` langsung, isolasi lewat parent

### 6.4 Middleware `EnsureTenant` — Tetap Dipertahankan

Global Scope hanya berlaku untuk Eloquent query. Middleware tetap diperlukan sebagai **layer kedua** untuk:
- Memastikan user sudah punya `tenant_id` yang valid
- Menolak request dari user yang tenant-nya dinonaktifkan (jika fitur ini ditambahkan nanti)
- Logging audit

**Prinsip: defense in depth — jangan hanya andalkan satu layer.**

---

## 7. Soft Delete Strategy

**Prinsip utama: Gunakan `SoftDeletes` secara konsisten di semua tabel yang bisa dihapus user dan yang punya relasi FK ke tabel transaksi.**

### 7.1 Tabel yang WAJIB Pakai SoftDeletes

| Tabel | Alasan |
|---|---|
| `categories` | Produk perlu dipindahkan ke "tanpa kategori" saat dihapus |
| `products` | Direferensi oleh `product_variants` → `transaction_items` |
| `product_variants` | Direferensi langsung oleh `transaction_items` dan `stock_movements` |
| `modifier_groups` | Direferensi oleh `modifiers` → `transaction_item_modifiers` |
| `modifiers` | Direferensi langsung oleh `transaction_item_modifiers` |
| `payment_methods` | Direferensi langsung oleh `transaction_payments` |

### 7.2 Kolom yang Ditambahkan

Setiap tabel di atas perlu kolom:
```sql
deleted_at          TIMESTAMP NULLABLE
```

Dan di setiap model Laravel:
```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes, BelongsToTenant;
}
```

### 7.3 Perilaku Saat "Hapus"

| Aksi | Perilaku |
|---|---|
| Hapus kategori | Semua produk di bawahnya → set `category_id = NULL` (tanpa kategori). **Bukan** cascade delete. |
| Hapus produk | Soft delete produk. Semua variant-nya ikut soft delete. |
| Hapus modifier group | Soft delete group. Semua modifier di dalamnya ikut soft delete. Relasi `product_modifier_groups` yang terkait dihapus (hard delete — ini pivot table, bukan data transaksi). |
| Hapus payment method | Soft delete saja. Rekap historis tetap utuh. |
| Hapus product variant | Soft delete. Stok movement historis tetap utuh. |

### 7.4 Konsistensi Query — Aturan Wajib

Laravel `SoftDeletes` trait secara otomatis menambahkan `whereNull('deleted_at')` pada semua Eloquent query. Ini berarti:
- **POS, dropdown, daftar:** Otomatis tidak menampilkan data yang sudah dihapus ✓
- **Laporan historis:** Jika perlu menampilkan data yang sudah dihapus (misal: nama modifier di transaksi lama), gunakan `->withTrashed()` secara eksplisit
- **JANGAN** bypass SoftDeletes di query operasional sehari-hari

**Aturan konsistensi: Setiap model yang punya trait `SoftDeletes` HARUS diuji bahwa data yang sudah dihapus tidak muncul di interface operasional (POS, form, dropdown). Ini masuk checklist QA.**

---

## 8. Image Upload — Strategi Storage

### 8.1 Spesifikasi

| Aspek | Detail |
|---|---|
| Storage | Local disk (`storage/app/public/products/`) |
| Max file size | 5 MB |
| Format diterima | JPG, PNG, WEBP |
| Format disimpan | **Selalu konversi ke WEBP** untuk optimasi ukuran |
| Symbolic link | `php artisan storage:link` (wajib dijalankan saat deploy) |

### 8.2 Proses Upload

```
User upload (JPG/PNG/WEBP, maks 5MB)
    → Validasi format & ukuran
    → Konversi ke WEBP (gunakan library Intervention Image v3)
    → Simpan ke storage/app/public/products/{tenant_id}/{filename}.webp
    → Simpan path relatif di kolom products.image
```

### 8.3 Validasi di Request

```php
// StoreProductRequest.php
'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120', // 5MB = 5120 KB
```

### 8.4 Cache

- Gunakan **HTTP cache headers** (`Cache-Control`, `ETag`) pada response gambar
- Tambahkan timestamp sebagai query string untuk cache busting saat gambar diupdate: `/storage/products/1/kopi.webp?v=1709654400`
- Untuk MVP, ini sudah cukup. CDN bisa ditambahkan di fase berikutnya

### 8.5 Dependency

```bash
composer require intervention/image
```

---

## 9. Saran: Database Indexing (Simpan untuk Referensi)

Belum perlu diimplementasi di MVP, tapi catat untuk diterapkan saat data mulai besar (1000+ transaksi):

```sql
-- Prioritas tinggi
CREATE INDEX idx_transactions_tenant_status ON transactions(tenant_id, status);
CREATE INDEX idx_transactions_tenant_created ON transactions(tenant_id, created_at);
CREATE INDEX idx_transaction_items_transaction ON transaction_items(transaction_id);
CREATE INDEX idx_product_variants_product ON product_variants(product_id);
CREATE INDEX idx_stock_movements_variant ON stock_movements(product_variant_id);
CREATE INDEX idx_stock_movements_tenant_created ON stock_movements(tenant_id, created_at);

-- Prioritas sedang
CREATE INDEX idx_products_tenant_category ON products(tenant_id, category_id);
CREATE INDEX idx_products_tenant_active ON products(tenant_id, is_active);
CREATE INDEX idx_cash_drawers_tenant_user ON cash_drawers(tenant_id, user_id);
CREATE INDEX idx_transaction_payments_transaction ON transaction_payments(transaction_id);
```

**Catatan:** Tambahkan index ini via migration terpisah (`add_performance_indexes`) saat performa mulai terasa lambat. Jangan terlalu dini — setiap index menambah overhead pada write operation.

---

## 10. Update Diagram Relasi

Diagram relasi di dokumen v1.0 perlu diperbarui untuk memasukkan `cash_drawers`:

```
tenants
  └── users
  └── cash_drawers              ← BARU
  └── categories
        └── products
              └── product_variants (stok, harga, expiry_date) ← UPDATE
              └── product_modifier_groups
                    └── modifier_groups
                          └── modifiers
  └── payment_methods
  └── transactions
        └── transaction_payments
        └── transaction_items
              └── transaction_item_modifiers
  └── stock_movements
```

---

## 11. Update Urutan Migration

```
1.  create_tenants_table
2.  create_users_table
3.  create_cash_drawers_table           ← BARU
4.  create_categories_table
5.  create_products_table
6.  create_product_variants_table       ← + kolom expiry_date
7.  create_modifier_groups_table        ← + kolom deleted_at
8.  create_modifiers_table              ← + kolom deleted_at
9.  create_product_modifier_groups_table
10. create_payment_methods_table        ← + kolom deleted_at
11. create_transactions_table
12. create_transaction_payments_table
13. create_transaction_items_table
14. create_transaction_item_modifiers_table
15. create_stock_movements_table
```

**Tabel dengan `deleted_at`:** categories, products, product_variants, modifier_groups, modifiers, payment_methods

---

## 12. Update Struktur Folder

Tambahan file yang perlu dibuat:

```
app/
├── Traits/
│   └── BelongsToTenant.php             ← BARU
├── Models/
│   ├── Scopes/
│   │   └── TenantScope.php             ← BARU
│   └── CashDrawer.php                  ← BARU
├── Http/
│   └── Controllers/
│       └── Cashier/
│           └── CashDrawerController.php  (sudah ada di v1.0)
├── Services/
│   ├── TransactionService.php           ← UPDATE: + generateTransactionCode()
│   ├── StockService.php
│   ├── BadgeHelperService.php           ← UPDATE: fix tenant query
│   └── ImageService.php                 ← BARU: konversi & simpan gambar
```

---

## 13. Checklist Konsistensi SoftDeletes & Tenant Isolation

Sebelum deploy, pastikan:

- [ ] Semua model dengan `tenant_id` menggunakan trait `BelongsToTenant`
- [ ] Semua model yang bisa dihapus user menggunakan trait `SoftDeletes`
- [ ] Semua dropdown/list di frontend **tidak** menampilkan record yang sudah soft-deleted
- [ ] Laporan historis yang perlu data lama menggunakan `->withTrashed()` secara eksplisit
- [ ] Hapus kategori → produk di bawahnya pindah ke `category_id = NULL`
- [ ] Hapus modifier group → cascade soft delete ke modifiers, hard delete pivot `product_modifier_groups`
- [ ] Hapus produk → cascade soft delete ke product_variants
- [ ] Test: user tenant A **tidak bisa** akses data tenant B (manual test + automated test)
- [ ] Test: kasir tidak bisa buka 2 sesi kas bersamaan
- [ ] Test: stok tidak bisa minus setelah checkout (`lockForUpdate` bekerja)
- [ ] Test: transaction code unik per hari per tenant
