# Execute Plan: SAPI Mobile API Integration

**Dibuat:** Mei 2026
**Status:** Ready to Execute
**Review by:** Claude Code (verified vs codebase aktual)

---

## Ringkasan Revisi dari Review

Plan asli di `SAPI-Mobile-API-Integration-Plan.md` sudah solid secara arsitektur. Setelah verifikasi codebase, ada **9 revisi** yang harus diterapkan saat eksekusi:

| # | Revisi | Prioritas |
|---|---|---|
| 1 | Tambah Step 0: migration `address`+`phone` ke tabel `tenants` | Blocker |
| 2 | Validation pakai `Rule::exists` scope per-tenant | Keamanan |
| 3 | Hapus `modifiers.*.name` & `extra_price` dari validation (service ambil dari DB) | Kontrak |
| 4 | Tambah field full-parity: `is_open_bill`, `order_type`, `customer_name`, `table_number`, `notes` per item, `reference_code` per payment | Fungsional |
| 5 | Receipt response lengkap: order_type, customer_name, table_number, notes, is_open_bill flag, reference_code | Fungsional |
| 6 | Login response include tenant info (reduce round-trip mobile) | UX |
| 7 | Throttle `5,1` di route `/api/login` | Keamanan |
| 8 | Error handling `store()`: log + forward pesan domain | Keandalan |
| 9 | Modifier mapping: gunakan `modifier_name` bukan `name` | Bug Fix |

**Keputusan scope:**
- Tenant address/phone → tambah migration + UI settings
- Mobile checkout → **full parity** dengan web POS (open bill, dine-in/takeaway, customer/table)
- Idempotency key → defer ke Phase 2

---

## Urutan Eksekusi (Wajib Berurutan)

### Step 0 — Migration: Tambah address & phone ke tenants

**File baru:** `database/migrations/2026_05_XX_add_address_phone_to_tenants_table.php`

```php
Schema::table('tenants', function (Blueprint $table) {
    $table->string('address', 500)->nullable()->after('logo');
    $table->string('phone', 50)->nullable()->after('address');
});
```

**Update `app/Models/Tenant.php`:** tambah `'address'`, `'phone'` ke fillable.

**Update Settings web:** tambah form input address + phone di halaman settings tenant.

---

### Step 1 — EnsureTenantApi Middleware

**File:** `app/Http/Middleware/EnsureTenantApi.php`

Return 401/403 JSON (bukan redirect) jika user belum auth atau tidak punya tenant.

Daftarkan di `bootstrap/app.php` dengan alias `tenant.api`.

---

### Step 2 — Routes di api.php

Tambah di bawah self-order routes:

```php
// ─── MOBILE APP — POS Kasir ───────────────────────────────────────
Route::post('/login', [MobileAuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware(['auth:sanctum', 'tenant.api'])->group(function () {
    Route::post('/logout',                      [MobileAuthController::class, 'logout']);
    Route::get('/tenant/profile',               [MobileTenantController::class, 'profile']);
    Route::get('/products',                     [ApiProductController::class, 'index']);   // reuse
    Route::get('/cash-drawer/status',           [MobileCashDrawerController::class, 'status']);
    Route::post('/transactions',                [MobileTransactionController::class, 'store']);
    Route::get('/transactions/{transaction}/receipt', [MobileTransactionController::class, 'receipt']);
});
```

---

### Step 3 — Controllers (`app/Http/Controllers/Api/Mobile/`)

#### MobileAuthController

- `login()` — validasi email/password, revoke token lama `mobile-app`, buat token baru, return token + user + **tenant info**
- `logout()` — delete current access token

#### MobileTenantController

- `profile()` — return nama, address, phone dari `auth()->user()->tenant`

#### MobileCashDrawerController

- `status()` — cek drawer per user (`whereNull('closed_at')`), return `is_open`, `drawer_id`, `opened_at`

#### MobileTransactionController

- `store()` — **Full parity validation** (lihat Revisi 2, 3, 4 di atas), forward ke `TransactionService::checkout()`, return 201
- `receipt()` — tenant isolation check, load relasi, return **response lengkap** (lihat Revisi 5)

---

## Validation Lengkap untuk `store()`

```php
$validated = $request->validate([
    'items'                          => 'required|array|min:1',
    'items.*.variant_id'             => ['required', Rule::exists('product_variants', 'id')->where('tenant_id', auth()->user()->tenant_id)],
    'items.*.variant_name'           => 'required|string|max:255',
    'items.*.qty'                    => 'required|integer|min:1',
    'items.*.notes'                  => 'nullable|string|max:500',
    'items.*.modifiers'              => 'nullable|array',
    'items.*.modifiers.*.id'         => ['required', Rule::exists('modifiers', 'id')->where('tenant_id', auth()->user()->tenant_id)],

    'is_open_bill'                   => 'nullable|boolean',
    'order_type'                     => 'nullable|in:dine_in,takeaway',
    'customer_name'                  => 'nullable|string|max:255',
    'table_number'                   => 'nullable|string|max:50',
    'notes'                          => 'nullable|string|max:500',

    'payments'                       => 'required_unless:is_open_bill,true|array|min:1',
    'payments.*.payment_method_id'   => ['required', Rule::exists('payment_methods', 'id')->where('tenant_id', auth()->user()->tenant_id)],
    'payments.*.amount'              => 'required|numeric|min:0',
    'payments.*.reference_code'      => 'nullable|string|max:255',
]);
```

> Tidak ada `items.*.unit_price`, `modifiers.*.name`, `modifiers.*.extra_price` — semua dari DB.

---

## Receipt Response Lengkap

```php
'transaction' => [
    'code'           => $transaction->code,
    'date'           => $transaction->created_at->format('d/m/Y H:i'),
    'cashier'        => $transaction->user->name,
    'total_amount'   => $transaction->total_amount,
    'change_amount'  => $transaction->change_amount,
    'status'         => $transaction->status,
    'order_type'     => $transaction->order_type,
    'customer_name'  => $transaction->customer_name,
    'table_number'   => $transaction->table_number,
    'notes'          => $transaction->notes,
    'is_open_bill'   => $transaction->status === 'pending',
],
'items' => $transaction->items->map(fn($item) => [
    'name'      => $item->variant_name,
    'qty'       => $item->qty,
    'price'     => $item->unit_price,
    'subtotal'  => $item->subtotal,
    'notes'     => $item->notes,
    'modifiers' => $item->modifiers->map(fn($m) => [
        'name'        => $m->modifier_name,   // ← modifier_name bukan name
        'extra_price' => $m->extra_price,
    ]),
]),
'payments' => $transaction->payments->map(fn($p) => [
    'method'         => $p->paymentMethod->name,
    'amount'         => $p->amount,
    'reference_code' => $p->reference_code,
]),
```

---

## Catatan Pola Tenant Isolation

- Pertahanan utama: `BelongsToTenant` global scope di model (otomatis filter by `tenant_id`)
- `EnsureTenantApi`: cek user punya `tenant_id` (defensive middleware)
- `Validation Rule::exists`: cek `variant_id`, `modifier_id`, `payment_method_id` milik tenant yang sama
- Receipt manual check: `$transaction->tenant_id !== auth()->user()->tenant_id` → belt-and-suspenders

---

## Yang Tidak Dikerjakan Sekarang (Defer)

| Item | Alasan |
|---|---|
| Idempotency-Key di POST /transactions | Defer Phase 2 — mitigasi: mobile disable tombol setelah tap |
| Thermal printer ESC/POS | Butuh printer fisik untuk test |
| Buka/tutup kas dari mobile | Bukan prioritas demo |
| Rekap & laporan dari mobile | Mobile Phase 2 |
| Role check (kasir vs owner) | Semua role terima untuk sekarang |

---

## Checklist Eksekusi

- [ ] Step 0: Migration `add_address_phone_to_tenants_table` dijalankan
- [ ] Step 0: `Tenant` model fillable diupdate
- [ ] Step 0: Form address/phone ditambah di Settings web
- [ ] Step 1: `EnsureTenantApi` middleware dibuat dan alias `tenant.api` didaftarkan
- [ ] Step 2: Routes mobile ditambah di `api.php` + throttle login
- [ ] Step 3: `MobileAuthController` — login (return tenant info) + logout
- [ ] Step 3: `MobileTenantController` — profile
- [ ] Step 3: `MobileCashDrawerController` — status
- [ ] Step 3: `MobileTransactionController` — store (full parity) + receipt (lengkap)
- [ ] Validation rules scope ke tenant_id via Rule::exists
- [ ] Modifier mapping pakai `modifier_name`
- [ ] Error handling checkout: log + forward pesan domain
- [ ] `php artisan route:list` — semua 7 endpoint mobile terdaftar
- [ ] Semua 8 urutan test Postman lolos (Step 4 dari plan asli)
- [ ] Test open bill: `is_open_bill=true` tanpa payments → 201 status pending
- [ ] Test cross-tenant security: variant_id tenant lain → 422
- [ ] Test throttle: 6x login salah → 429
- [ ] `docs/phases-2/API-DOCS.md` selesai setelah semua test lolos

---

## File yang Akan Disentuh

| File | Aksi |
|---|---|
| `database/migrations/2026_05_XX_add_address_phone_to_tenants_table.php` | BARU |
| `app/Models/Tenant.php` | UPDATE fillable |
| `resources/js/Pages/Settings/...` | UPDATE form |
| `app/Http/Middleware/EnsureTenantApi.php` | BARU |
| `bootstrap/app.php` | UPDATE alias |
| `routes/api.php` | UPDATE tambah routes mobile |
| `app/Http/Controllers/Api/Mobile/MobileAuthController.php` | BARU |
| `app/Http/Controllers/Api/Mobile/MobileTenantController.php` | BARU |
| `app/Http/Controllers/Api/Mobile/MobileCashDrawerController.php` | BARU |
| `app/Http/Controllers/Api/Mobile/MobileTransactionController.php` | BARU |
| `docs/phases-2/API-DOCS.md` | BARU (setelah testing) |
