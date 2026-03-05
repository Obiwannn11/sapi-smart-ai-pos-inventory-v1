# PHASE 4 — Stock Management (Restock, Adjustment, Movement Log)

**Status:** Belum dimulai  
**Estimasi:** Setelah Phase 3 selesai (atau paralel dengan Phase 3 untuk bagian service)  
**Dependency:** Phase 1 (models, migrations), Phase 3 (`StockService` dipanggil oleh `TransactionService`)  
**Output:** StockService lengkap, restock & adjustment UI, stock movement log viewer

---

## Daftar Isi
1. [Overview Tipe Stock Movement](#1-overview-tipe-stock-movement)
2. [StockService](#2-stockservice)
3. [Controllers](#3-controllers)
4. [Form Requests](#4-form-requests)
5. [Vue Pages](#5-vue-pages)
6. [Routes](#6-routes)
7. [Checklist Phase 4](#7-checklist)

---

## 1. Overview Tipe Stock Movement

| Tipe | Arah Stok | Trigger | Qty (di DB) | Contoh |
|---|---|---|---|---|
| `sale` | Keluar | Otomatis saat checkout (Phase 3) | Negatif | -2 (jual 2 gelas) |
| `restock` | Masuk | Manual oleh owner saat terima barang | Positif | +50 (terima 50 pack) |
| `adjustment` | Masuk/Keluar | Manual oleh owner untuk koreksi | Positif/Negatif | +5 (temuan audit) atau -3 (bahan terbuang) |

### Kapan Owner Pakai Masing-masing

- **Restock:** Supplier datang, owner input jumlah barang masuk + bisa update `expiry_date` variant
- **Adjustment (+):** Audit fisik menemukan stok lebih banyak dari sistem
- **Adjustment (-):** Bahan rusak, expired dibuang, salah hitung, susut

---

## 2. StockService

**File:** `app/Services/StockService.php`

```php
<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Kurangi stok saat checkout (dipanggil oleh TransactionService).
     * HARUS dipanggil di dalam DB::transaction().
     */
    public function deduct(ProductVariant $variant, int $qty, int $transactionId): void
    {
        $variant->decrement('stock', $qty);

        StockMovement::create([
            'tenant_id' => $variant->product->tenant_id,
            'product_variant_id' => $variant->id,
            'type' => StockMovement::TYPE_SALE,
            'qty' => -$qty, // negatif
            'notes' => "Penjualan dari transaksi #{$transactionId}",
            'reference_id' => $transactionId,
        ]);
    }

    /**
     * Kembalikan stok saat void transaksi (dipanggil oleh TransactionService).
     * HARUS dipanggil di dalam DB::transaction().
     */
    public function restore(ProductVariant $variant, int $qty, int $transactionId): void
    {
        $variant->increment('stock', $qty);

        StockMovement::create([
            'tenant_id' => $variant->product->tenant_id,
            'product_variant_id' => $variant->id,
            'type' => StockMovement::TYPE_ADJUSTMENT,
            'qty' => $qty, // positif
            'notes' => "Void transaksi #{$transactionId} — stok dikembalikan",
            'reference_id' => $transactionId,
        ]);
    }

    /**
     * Restock — tambah stok karena terima barang.
     */
    public function restock(ProductVariant $variant, int $qty, ?string $notes = null, ?string $expiryDate = null): void
    {
        DB::transaction(function () use ($variant, $qty, $notes, $expiryDate) {
            $variant->increment('stock', $qty);

            // Update expiry_date jika diisi (tanggal expiry batch terakhir)
            if ($expiryDate) {
                $variant->update(['expiry_date' => $expiryDate]);
            }

            StockMovement::create([
                'tenant_id' => $variant->product->tenant_id,
                'product_variant_id' => $variant->id,
                'type' => StockMovement::TYPE_RESTOCK,
                'qty' => $qty, // positif
                'notes' => $notes ?? 'Restock',
            ]);
        });
    }

    /**
     * Adjustment — koreksi stok manual (bisa positif atau negatif).
     */
    public function adjust(ProductVariant $variant, int $qty, ?string $notes = null): void
    {
        DB::transaction(function () use ($variant, $qty, $notes) {
            if ($qty > 0) {
                $variant->increment('stock', $qty);
            } else {
                $variant->decrement('stock', abs($qty));
            }

            // Safety: cegah stok negatif
            if ($variant->fresh()->stock < 0) {
                throw new \Exception(
                    "Adjustment gagal: stok {$variant->name} akan menjadi negatif."
                );
            }

            StockMovement::create([
                'tenant_id' => $variant->product->tenant_id,
                'product_variant_id' => $variant->id,
                'type' => StockMovement::TYPE_ADJUSTMENT,
                'qty' => $qty,
                'notes' => $notes ?? 'Adjustment manual',
            ]);
        });
    }
}
```

---

## 3. Controllers

### 3.1 `StockController`

**File:** `app/Http/Controllers/Owner/StockController.php`

```php
<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\RestockRequest;
use App\Http\Requests\AdjustStockRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StockController extends Controller
{
    public function __construct(
        private StockService $stockService
    ) {}

    /**
     * Halaman manajemen stok — list semua variant + stok saat ini.
     */
    public function index(): Response
    {
        $products = Product::with([
            'variants' => fn($q) => $q->select('id', 'product_id', 'name', 'sku', 'stock', 'expiry_date'),
            'category:id,name',
        ])->get();

        return Inertia::render('Owner/Stock/Index', [
            'products' => $products,
        ]);
    }

    /**
     * Restock — tambah stok variant.
     */
    public function restock(RestockRequest $request, ProductVariant $variant): RedirectResponse
    {
        try {
            $this->stockService->restock(
                variant: $variant,
                qty: $request->validated('qty'),
                notes: $request->validated('notes'),
                expiryDate: $request->validated('expiry_date'),
            );

            return back()->with('success', "Restock {$variant->name}: +{$request->qty} berhasil.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Adjustment — koreksi stok manual.
     */
    public function adjust(AdjustStockRequest $request, ProductVariant $variant): RedirectResponse
    {
        try {
            $this->stockService->adjust(
                variant: $variant,
                qty: $request->validated('qty'),
                notes: $request->validated('notes'),
            );

            $direction = $request->qty > 0 ? "+{$request->qty}" : "{$request->qty}";
            return back()->with('success', "Adjustment {$variant->name}: {$direction} berhasil.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Riwayat stock movement per variant.
     */
    public function history(ProductVariant $variant): Response
    {
        $movements = StockMovement::where('product_variant_id', $variant->id)
            ->latest('created_at')
            ->paginate(50);

        $variant->load('product:id,name');

        return Inertia::render('Owner/Stock/History', [
            'variant' => $variant,
            'movements' => $movements,
        ]);
    }

    /**
     * Semua stock movements (global tenant) — filterable.
     */
    public function movements(): Response
    {
        $movements = StockMovement::with(['variant.product:id,name'])
            ->latest('created_at')
            ->paginate(50);

        return Inertia::render('Owner/Stock/Movements', [
            'movements' => $movements,
        ]);
    }
}
```

---

## 4. Form Requests

### 4.1 `RestockRequest`

**File:** `app/Http/Requests/RestockRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qty'         => 'required|integer|min:1',
            'notes'       => 'nullable|string|max:500',
            'expiry_date' => 'nullable|date|after_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'qty.min' => 'Jumlah restock minimal 1.',
            'expiry_date.after_or_equal' => 'Tanggal kedaluwarsa tidak boleh di masa lalu.',
        ];
    }
}
```

### 4.2 `AdjustStockRequest`

**File:** `app/Http/Requests/AdjustStockRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qty'   => 'required|integer|not_in:0', // boleh positif atau negatif, tapi bukan 0
            'notes' => 'required|string|max:500',    // wajib isi alasan adjustment
        ];
    }

    public function messages(): array
    {
        return [
            'qty.not_in' => 'Jumlah adjustment tidak boleh 0.',
            'notes.required' => 'Alasan adjustment wajib diisi.',
        ];
    }
}
```

---

## 5. Vue Pages

### 5.1 Struktur File

```
resources/js/Pages/
└── Owner/
    └── Stock/
        ├── Index.vue            ← List semua produk + variant + stok
        ├── History.vue          ← Riwayat movement per variant
        └── Movements.vue        ← Semua movements (global, filterable)
```

### 5.2 `Owner/Stock/Index.vue`

**Tabel/Grid yang menampilkan:**
- Produk → Variant → Stok saat ini → Expiry date → Actions

**Layout per produk (expandable row):**
```
┌─────────────────────────────────────────────────────────────────────┐
│ Espresso                                          [Kategori: Kopi] │
│ ├── Single   | SKU: -     | Stok: 98  | Exp: -        | [+] [-] [📜] │
│ └── Double   | SKU: -     | Stok: 100 | Exp: -        | [+] [-] [📜] │
├─────────────────────────────────────────────────────────────────────┤
│ Croissant                                       [Kategori: Makanan] │
│ └── Plain    | SKU: -     | Stok: 20  | Exp: 2026-03-09 ⚠️ | [+] [-] [📜] │
└─────────────────────────────────────────────────────────────────────┘
```

**Actions per variant:**
- `[+]` → Modal Restock (input qty, notes, expiry_date opsional)
- `[-]` → Modal Adjustment (input qty ± , notes wajib)
- `[📜]` → Navigasi ke halaman History variant

**Visual indicators:**
- Stok ≤ 5 & > 0 → badge kuning "Stok Kritis"
- Stok = 0 → badge merah "Habis"
- Expiry date ≤ 7 hari → badge oranye "⚠️ Near Expiry"

### 5.3 `Owner/Stock/History.vue`

**Tabel riwayat untuk 1 variant:**
| Waktu | Tipe | Qty | Notes | Referensi |
|---|---|---|---|---|
| 2026-03-06 14:30 | sale | -2 | Penjualan dari transaksi #TRX-20260306-001 | TRX-20260306-001 |
| 2026-03-06 10:00 | restock | +50 | Restock dari supplier | - |
| 2026-03-05 16:00 | adjustment | -3 | Bahan expired dibuang | - |

### 5.4 `Owner/Stock/Movements.vue`

**Tabel semua movements (tenant-wide):**
- Filter: by type (sale/restock/adjustment), by date range, by product
- Tabel: waktu, produk, variant, tipe, qty, notes

---

## 6. Routes

Tambahkan ke `routes/web.php` di dalam group owner:

```php
// Stock Management
Route::get('stock', [\App\Http\Controllers\Owner\StockController::class, 'index'])
    ->name('stock.index');
Route::post('stock/{variant}/restock', [\App\Http\Controllers\Owner\StockController::class, 'restock'])
    ->name('stock.restock');
Route::post('stock/{variant}/adjust', [\App\Http\Controllers\Owner\StockController::class, 'adjust'])
    ->name('stock.adjust');
Route::get('stock/{variant}/history', [\App\Http\Controllers\Owner\StockController::class, 'history'])
    ->name('stock.history');
Route::get('stock/movements', [\App\Http\Controllers\Owner\StockController::class, 'movements'])
    ->name('stock.movements');
```

---

## 7. Checklist Phase 4

- [ ] `StockService` — semua method berfungsi: deduct, restore, restock, adjust
- [ ] Restock menambah stok + catat movement + update expiry_date
- [ ] Adjustment bisa positif & negatif + notes wajib + cegah stok negatif
- [ ] `RestockRequest` & `AdjustStockRequest` validasi benar
- [ ] `StockController` — semua endpoint berfungsi
- [ ] `Stock/Index.vue` — tampilkan semua variant + stok + visual indicators
- [ ] Modal restock & adjustment berfungsi
- [ ] `Stock/History.vue` — riwayat per variant tampil benar
- [ ] `Stock/Movements.vue` — semua movements dengan filter
- [ ] Integrasi: checkout di POS → stok berkurang → movement tercatat (tipe: sale)
- [ ] Integrasi: void transaksi → stok kembali → movement tercatat (tipe: adjustment)
- [ ] Stok tidak bisa menjadi negatif setelah adjustment

### Test Manual

1. Login owner → halaman Stock → cek stok awal
2. Restock variant → stok bertambah → movement tercatat
3. Adjustment minus → stok berkurang → movement tercatat, notes terisi
4. Coba adjustment yang bikin stok negatif → error
5. Login kasir → checkout → cek stok variant berkurang
6. Login owner → void transaksi → stok kembali
7. Cek history variant → semua movement terurut
