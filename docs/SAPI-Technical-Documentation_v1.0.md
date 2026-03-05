# Technical Documentation — SAPI (Smart Inventory POS)
**Versi:** 1.0  
**Tanggal:** Maret 2026  

---

## 1. Stack Teknologi

| Layer | Teknologi |
|---|---|
| Backend | Laravel 12 |
| Frontend | Inertia.js + Vue 3 |
| Styling | Tailwind CSS v4 |
| Database | MySQL |
| Server | VPS (Linux) |
| Auth | Manual (tanpa Breeze/Jetstream) |

---

## 2. Skema Database

### 2.1 Diagram Relasi Singkat

```
tenants
  └── users
  └── categories
        └── products
              └── product_variants (stok, harga)
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

### 2.2 Detail Tabel

#### tenants
```sql
id              BIGINT PK
name            VARCHAR(255)
slug            VARCHAR(255) UNIQUE
logo            VARCHAR(255) NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### users
```sql
id              BIGINT PK
tenant_id       BIGINT FK → tenants.id
name            VARCHAR(255)
email           VARCHAR(255) UNIQUE
password        VARCHAR(255)
role            ENUM('owner', 'cashier')
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### categories
```sql
id              BIGINT PK
tenant_id       BIGINT FK → tenants.id
name            VARCHAR(255)
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### products
```sql
id              BIGINT PK
tenant_id       BIGINT FK → tenants.id
category_id     BIGINT FK → categories.id NULLABLE
name            VARCHAR(255)
image           VARCHAR(255) NULLABLE
is_active       BOOLEAN DEFAULT true
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### product_variants
```sql
id              BIGINT PK
product_id      BIGINT FK → products.id
name            VARCHAR(255)       -- contoh: "M", "Panas", "Large"
sku             VARCHAR(100) NULLABLE
price           DECIMAL(12,2)      -- harga jual
cost_price      DECIMAL(12,2)      -- harga modal
stock           INT DEFAULT 0
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### modifier_groups
```sql
id              BIGINT PK
tenant_id       BIGINT FK → tenants.id
name            VARCHAR(255)       -- contoh: "Temperature", "Sugar Level"
is_required     BOOLEAN DEFAULT false
is_multiple     BOOLEAN DEFAULT false
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### modifiers
```sql
id              BIGINT PK
modifier_group_id BIGINT FK → modifier_groups.id
name            VARCHAR(255)       -- contoh: "Extra Shot", "Less Sugar"
extra_price     DECIMAL(12,2) DEFAULT 0
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### product_modifier_groups
```sql
id              BIGINT PK
product_id      BIGINT FK → products.id
modifier_group_id BIGINT FK → modifier_groups.id
```

#### payment_methods
```sql
id              BIGINT PK
tenant_id       BIGINT FK → tenants.id
name            VARCHAR(255)
type            ENUM('cash', 'qris_static', 'qris_dynamic', 'bank_transfer')
is_active       BOOLEAN DEFAULT true
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### transactions
```sql
id              BIGINT PK
tenant_id       BIGINT FK → tenants.id
user_id         BIGINT FK → users.id   -- kasir yang melayani
code            VARCHAR(50) UNIQUE     -- contoh: TRX-20260305-001
status          ENUM('open', 'paid', 'cancelled')
total_amount    DECIMAL(12,2)
change_amount   DECIMAL(12,2) DEFAULT 0
notes           TEXT NULLABLE
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

#### transaction_payments
```sql
id              BIGINT PK
transaction_id  BIGINT FK → transactions.id
payment_method_id BIGINT FK → payment_methods.id
amount          DECIMAL(12,2)
reference_code  VARCHAR(255) NULLABLE  -- untuk QRIS/transfer
created_at      TIMESTAMP
```

#### transaction_items
```sql
id              BIGINT PK
transaction_id  BIGINT FK → transactions.id
product_variant_id BIGINT FK → product_variants.id
variant_name    VARCHAR(255)           -- SNAPSHOT nama varian
qty             INT
unit_price      DECIMAL(12,2)          -- SNAPSHOT harga saat transaksi
subtotal        DECIMAL(12,2)
```

#### transaction_item_modifiers
```sql
id              BIGINT PK
transaction_item_id BIGINT FK → transaction_items.id
modifier_id     BIGINT FK → modifiers.id
modifier_name   VARCHAR(255)           -- SNAPSHOT nama modifier
extra_price     DECIMAL(12,2)          -- SNAPSHOT harga modifier
```

#### stock_movements
```sql
id              BIGINT PK
tenant_id       BIGINT FK → tenants.id
product_variant_id BIGINT FK → product_variants.id
type            ENUM('in', 'out', 'adjustment')
qty             INT                    -- positif untuk in, negatif untuk out
notes           TEXT NULLABLE
reference_id    BIGINT NULLABLE        -- FK ke transaction_id jika type=out
created_at      TIMESTAMP
```

---

## 3. Urutan Migration Laravel

Jalankan migration sesuai urutan dependensi foreign key:

```
1. create_tenants_table
2. create_users_table
3. create_categories_table
4. create_products_table
5. create_product_variants_table
6. create_modifier_groups_table
7. create_modifiers_table
8. create_product_modifier_groups_table
9. create_payment_methods_table
10. create_transactions_table
11. create_transaction_payments_table
12. create_transaction_items_table
13. create_transaction_item_modifiers_table
14. create_stock_movements_table
```

---

## 4. Struktur Folder Laravel

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Auth/
│   │   │   └── AuthController.php
│   │   ├── Owner/
│   │   │   ├── ProductController.php
│   │   │   ├── VariantController.php
│   │   │   ├── ModifierController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── PaymentMethodController.php
│   │   │   ├── ReportController.php
│   │   │   └── DashboardController.php
│   │   └── Cashier/
│   │       ├── POSController.php
│   │       └── CashDrawerController.php
│   ├── Middleware/
│   │   ├── EnsureTenant.php
│   │   └── EnsureRole.php
│   └── Requests/
│       ├── StoreTransactionRequest.php
│       └── StoreProductRequest.php
├── Models/
│   ├── Tenant.php
│   ├── User.php
│   ├── Product.php
│   ├── ProductVariant.php
│   ├── ModifierGroup.php
│   ├── Modifier.php
│   ├── Transaction.php
│   ├── TransactionItem.php
│   ├── TransactionPayment.php
│   ├── TransactionItemModifier.php
│   ├── StockMovement.php
│   └── PaymentMethod.php
├── Services/
│   ├── TransactionService.php     -- logika checkout & database transaction
│   ├── StockService.php           -- logika pengurangan & pencatatan stok
│   └── BadgeHelperService.php     -- logika rule-based badge
└── Policies/
    ├── ProductPolicy.php
    └── TransactionPolicy.php

resources/
└── js/
    ├── Pages/
    │   ├── Auth/
    │   │   └── Login.vue
    │   ├── Owner/
    │   │   ├── Dashboard.vue
    │   │   ├── Products/
    │   │   │   ├── Index.vue
    │   │   │   └── Form.vue
    │   │   ├── Modifiers/
    │   │   │   └── Index.vue
    │   │   └── Reports/
    │   │       └── Daily.vue
    │   └── Cashier/
    │       ├── POS.vue
    │       └── CashDrawer.vue
    └── Components/
        ├── ModifierModal.vue       -- popup pilih modifier
        ├── CartItem.vue
        ├── PaymentModal.vue
        └── BadgeCard.vue
```

---

## 5. Logic Kritis

### 5.1 Database Transaction pada Checkout

Gunakan `DB::transaction()` untuk menjamin atomicity — jika satu langkah gagal, semua rollback.

```php
// TransactionService.php
DB::transaction(function () use ($data) {
    // 1. Buat transaksi
    $transaction = Transaction::create([...]);

    // 2. Simpan items + modifiers
    foreach ($data['items'] as $item) {
        $txItem = $transaction->items()->create([
            'product_variant_id' => $item['variant_id'],
            'variant_name'       => $item['variant_name'],   // snapshot
            'qty'                => $item['qty'],
            'unit_price'         => $item['unit_price'],     // snapshot
            'subtotal'           => $item['subtotal'],
        ]);

        foreach ($item['modifiers'] as $modifier) {
            $txItem->modifiers()->create([
                'modifier_id'   => $modifier['id'],
                'modifier_name' => $modifier['name'],        // snapshot
                'extra_price'   => $modifier['extra_price'], // snapshot
            ]);
        }
    }

    // 3. Simpan pembayaran
    foreach ($data['payments'] as $payment) {
        $transaction->payments()->create([...]);
    }

    // 4. Kurangi stok + catat movement
    StockService::deductFromTransaction($transaction);

    // 5. Update status
    $transaction->update(['status' => 'paid']);
});
```

### 5.2 Snapshot Harga

Selalu simpan nama dan harga **saat transaksi terjadi** — bukan ambil dari tabel produk. Ini memastikan laporan historis tetap akurat meski harga berubah di kemudian hari.

### 5.3 Stock Movement Log

Setiap perubahan stok — baik dari transaksi, restock, maupun adjustment manual — wajib dicatat di `stock_movements`. Kolom ini adalah sumber data utama untuk analitik dan Badge Helper.

### 5.4 Badge Helper — Rule-Based Logic

```php
// BadgeHelperService.php
public function generate(Tenant $tenant): array
{
    $badges = [];

    // Rule 1: Stok kritis
    $lowStock = ProductVariant::whereTenantId($tenant->id)
        ->where('stock', '<=', 5)
        ->where('stock', '>', 0)
        ->get();

    // Rule 2: Dead stock (tidak terjual 30 hari, stok > 0)
    $deadStock = ProductVariant::whereTenantId($tenant->id)
        ->where('stock', '>', 0)
        ->whereDoesntHave('transactionItems', function ($q) {
            $q->where('created_at', '>=', now()->subDays(30));
        })->get();

    // Rule 3: Stok habis
    $outOfStock = ProductVariant::whereTenantId($tenant->id)
        ->where('stock', '<=', 0)
        ->get();

    return $badges;
}
```

---

## 6. Routing

```php
// routes/web.php

// Auth
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Owner
Route::middleware(['auth', 'role:owner'])->prefix('owner')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::resource('products', ProductController::class);
    Route::resource('modifiers', ModifierController::class);
    Route::resource('payment-methods', PaymentMethodController::class);
    Route::get('/reports/daily', [ReportController::class, 'daily']);
});

// Kasir
Route::middleware(['auth', 'role:cashier,owner'])->prefix('cashier')->group(function () {
    Route::get('/pos', [POSController::class, 'index']);
    Route::post('/transactions', [POSController::class, 'store']);
    Route::post('/cash-drawer/open', [CashDrawerController::class, 'open']);
    Route::post('/cash-drawer/close', [CashDrawerController::class, 'close']);
});
```

---

## 7. Middleware Tenant & Role

```php
// EnsureTenant.php
// Pastikan user hanya bisa akses data tenant mereka sendiri
// Inject tenant ke semua query via Global Scope atau manual check

// EnsureRole.php
public function handle(Request $request, Closure $next, ...$roles)
{
    if (!in_array(auth()->user()->role, $roles)) {
        abort(403);
    }
    return $next($request);
}
```

---

## 8. Keputusan Arsitektur

| Keputusan | Pilihan | Alasan |
|---|---|---|
| Multi-tenant | Single database + tenant_id | Cukup untuk skala awal, lebih simpel dikelola |
| Frontend | Inertia + Vue 3 | Satu repo, UX seperti SPA, tidak perlu manage CORS |
| Auth | Manual | Kontrol penuh, tidak terikat opini Breeze |
| Stok | Langsung di product_variants | Simple dan cukup untuk MVP |
| Harga di transaksi | Snapshot (disimpan ulang) | Data historis akurat meski harga berubah |
| ML | Rule-based dulu | Data belum cukup, rule-based lebih predictable |

---

## 9. Yang Sengaja Tidak Dimasukkan di MVP

| Fitur | Alasan Ditunda |
|---|---|
| Voice Assistant | Terlalu kompleks, belum ada validasi kebutuhan nyata |
| ML Pipeline Otomatis | Butuh minimal 3 bulan data transaksi dulu |
| QRIS Dinamis | Butuh integrasi payment gateway, scope bulan 3+ |
| React Native | Prematur — web harus selesai dan terbukti dulu |
| Akuntansi lengkap | Bukan diferensiasi SAPI, bisa tambah belakangan |
| Multi-outlet | Kompleksitas tidak sebanding dengan nilai MVP |
