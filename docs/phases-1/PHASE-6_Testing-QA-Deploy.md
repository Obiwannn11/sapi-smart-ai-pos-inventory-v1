# PHASE 6 — Testing, QA & Deployment Preparation

**Status:** Belum dimulai  
**Estimasi:** Setelah Phase 5 selesai (semua fitur sudah diimplementasi)  
**Dependency:** Phase 1–5 (semua fitur harus sudah jalan)  
**Output:** Test suite lengkap, QA checklist verified, deployment-ready

---

## Daftar Isi
1. [Testing Strategy](#1-testing-strategy)
2. [Feature Tests](#2-feature-tests)
3. [Unit Tests](#3-unit-tests)
4. [Tenant Isolation Tests](#4-tenant-isolation-tests)
5. [QA Checklist](#5-qa-checklist)
6. [Deployment Preparation](#6-deployment-preparation)
7. [Checklist Phase 6](#7-checklist)

---

## 1. Testing Strategy

### 1.1 Framework

Project sudah menggunakan **Pest** (sudah terinstall di `vendor/pestphp`).

### 1.2 Coverage Target

| Area | Priority | Min Coverage |
|---|---|---|
| Tenant Isolation | 🔴 Critical | 100% — semua model dengan BelongsToTenant harus ditest |
| TransactionService | 🔴 Critical | Checkout, void, generate code, stok deduction |
| StockService | 🔴 Critical | Deduct, restore, restock, adjust |
| BadgeHelperService | 🟡 Medium | Semua 4 badge rules |
| Controllers (happy path) | 🟡 Medium | CRUD operations, auth redirect |
| Form Validation | 🟢 Low | Bisa manual test |

### 1.3 Struktur File Test

```
tests/
├── Pest.php
├── TestCase.php
├── Feature/
│   ├── Auth/
│   │   └── AuthTest.php
│   ├── Owner/
│   │   ├── CategoryTest.php
│   │   ├── ProductTest.php
│   │   ├── ModifierTest.php
│   │   ├── PaymentMethodTest.php
│   │   ├── StockTest.php
│   │   ├── DashboardTest.php
│   │   └── ReportTest.php
│   ├── Cashier/
│   │   ├── POSTest.php
│   │   └── CashDrawerTest.php
│   └── TenantIsolation/
│       └── TenantIsolationTest.php
└── Unit/
    ├── Services/
    │   ├── TransactionServiceTest.php
    │   ├── StockServiceTest.php
    │   ├── BadgeHelperServiceTest.php
    │   └── ImageServiceTest.php
    └── Models/
        └── TransactionCodeTest.php
```

---

## 2. Feature Tests

### 2.1 `Auth/AuthTest.php`

```php
<?php

use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);
});

test('login page is accessible', function () {
    $this->get('/login')->assertStatus(200);
});

test('owner can login and is redirected to dashboard', function () {
    $this->post('/login', [
        'email' => $this->owner->email,
        'password' => 'password',
    ])->assertRedirect('/owner/dashboard');
});

test('cashier can login and is redirected to POS', function () {
    $this->post('/login', [
        'email' => $this->cashier->email,
        'password' => 'password',
    ])->assertRedirect('/cashier/pos');
});

test('login fails with wrong credentials', function () {
    $this->post('/login', [
        'email' => $this->owner->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');
});

test('cashier cannot access owner routes', function () {
    $this->actingAs($this->cashier)
        ->get('/owner/dashboard')
        ->assertStatus(403);
});

test('unauthenticated user is redirected to login', function () {
    $this->get('/owner/dashboard')
        ->assertRedirect('/login');
});
```

### 2.2 `Cashier/POSTest.php`

```php
<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'price' => 25000,
        'stock' => 50,
    ]);
    $this->paymentMethod = PaymentMethod::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => 'cash',
    ]);
});

test('cashier is redirected to cash drawer if no open session', function () {
    $this->actingAs($this->cashier)
        ->get('/cashier/pos')
        ->assertRedirect(route('cashier.cash-drawer.index'));
});

test('cashier can access POS with open cash drawer', function () {
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'closed_at' => null,
    ]);

    $this->actingAs($this->cashier)
        ->get('/cashier/pos')
        ->assertStatus(200);
});

test('checkout creates transaction and deducts stock', function () {
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'closed_at' => null,
    ]);

    $this->actingAs($this->cashier)
        ->post('/cashier/transactions', [
            'items' => [
                [
                    'variant_id' => $this->variant->id,
                    'variant_name' => $this->variant->name,
                    'qty' => 2,
                    'unit_price' => $this->variant->price,
                    'modifiers' => [],
                ],
            ],
            'payments' => [
                [
                    'payment_method_id' => $this->paymentMethod->id,
                    'amount' => 50000,
                ],
            ],
        ])
        ->assertSessionHas('success');

    // Verify transaction created
    $this->assertDatabaseHas('transactions', [
        'tenant_id' => $this->tenant->id,
        'status' => 'completed',
        'total_amount' => 50000,
    ]);

    // Verify stock deducted
    $this->assertEquals(48, $this->variant->fresh()->stock);

    // Verify stock movement
    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $this->variant->id,
        'type' => 'sale',
        'qty' => -2,
    ]);
});

test('checkout fails when stock is insufficient', function () {
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'closed_at' => null,
    ]);

    $this->actingAs($this->cashier)
        ->post('/cashier/transactions', [
            'items' => [
                [
                    'variant_id' => $this->variant->id,
                    'variant_name' => $this->variant->name,
                    'qty' => 999, // lebih dari stok
                    'unit_price' => $this->variant->price,
                    'modifiers' => [],
                ],
            ],
            'payments' => [
                [
                    'payment_method_id' => $this->paymentMethod->id,
                    'amount' => 999 * 25000,
                ],
            ],
        ])
        ->assertSessionHas('error');

    // Stock unchanged
    $this->assertEquals(50, $this->variant->fresh()->stock);
});
```

### 2.3 `Cashier/CashDrawerTest.php`

```php
<?php

use App\Models\CashDrawer;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);
});

test('cashier can open cash drawer', function () {
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/open', [
            'opening_amount' => 500000,
        ])
        ->assertRedirect(route('cashier.pos'));

    $this->assertDatabaseHas('cash_drawers', [
        'user_id' => $this->cashier->id,
        'opening_amount' => 500000,
        'closed_at' => null,
    ]);
});

test('cashier cannot open second cash drawer while one is open', function () {
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'closed_at' => null,
    ]);

    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/open', [
            'opening_amount' => 500000,
        ])
        ->assertSessionHas('error');
});

test('cashier can close cash drawer', function () {
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'opening_amount' => 500000,
        'opened_at' => now()->subHours(8),
        'closed_at' => null,
    ]);

    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/close', [
            'closing_amount' => 750000,
            'notes' => 'Shift selesai',
        ])
        ->assertSessionHas('success');

    $drawer = CashDrawer::where('user_id', $this->cashier->id)->first();
    $this->assertNotNull($drawer->closed_at);
    $this->assertNotNull($drawer->expected_amount);
});
```

---

## 3. Unit Tests

### 3.1 `Services/TransactionServiceTest.php`

```php
<?php

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TransactionService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);
    $this->actingAs($this->user);

    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'price' => 25000,
        'stock' => 100,
    ]);
    $this->paymentMethod = PaymentMethod::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->service = app(TransactionService::class);
});

test('checkout creates complete transaction', function () {
    $transaction = $this->service->checkout([
        'items' => [
            [
                'variant_id' => $this->variant->id,
                'variant_name' => 'Test Variant',
                'qty' => 2,
                'unit_price' => 25000,
                'modifiers' => [],
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $this->paymentMethod->id,
                'amount' => 50000,
            ],
        ],
    ]);

    expect($transaction->status)->toBe('completed');
    expect($transaction->total_amount)->toBe('50000.00');
    expect($transaction->items)->toHaveCount(1);
    expect($transaction->payments)->toHaveCount(1);
});

test('transaction code is unique per day per tenant', function () {
    // Create 2 transactions
    $tx1 = $this->service->checkout([
        'items' => [[
            'variant_id' => $this->variant->id,
            'variant_name' => 'V1',
            'qty' => 1,
            'unit_price' => 25000,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 25000,
        ]],
    ]);

    $tx2 = $this->service->checkout([
        'items' => [[
            'variant_id' => $this->variant->id,
            'variant_name' => 'V1',
            'qty' => 1,
            'unit_price' => 25000,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 25000,
        ]],
    ]);

    expect($tx1->code)->not->toBe($tx2->code);
    expect($tx1->code)->toMatch('/^TRX-\d{8}-001$/');
    expect($tx2->code)->toMatch('/^TRX-\d{8}-002$/');
});

test('void returns stock and changes status', function () {
    $transaction = $this->service->checkout([
        'items' => [[
            'variant_id' => $this->variant->id,
            'variant_name' => 'V1',
            'qty' => 5,
            'unit_price' => 25000,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 125000,
        ]],
    ]);

    expect($this->variant->fresh()->stock)->toBe(95);

    // Void — need owner role for this in controller, but service allows it
    $voided = $this->service->void($transaction);

    expect($voided->status)->toBe('voided');
    expect($this->variant->fresh()->stock)->toBe(100);
});
```

### 3.2 `Services/StockServiceTest.php`

```php
<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);

    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'stock' => 50,
    ]);
    $this->service = app(StockService::class);
});

test('restock increases stock', function () {
    $this->service->restock($this->variant, 20, 'Restock dari supplier');

    expect($this->variant->fresh()->stock)->toBe(70);
    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $this->variant->id,
        'type' => 'restock',
        'qty' => 20,
    ]);
});

test('restock can update expiry date', function () {
    $this->service->restock($this->variant, 10, 'Restock', '2026-06-01');

    expect($this->variant->fresh()->expiry_date->format('Y-m-d'))->toBe('2026-06-01');
});

test('positive adjustment increases stock', function () {
    $this->service->adjust($this->variant, 5, 'Temuan audit');

    expect($this->variant->fresh()->stock)->toBe(55);
});

test('negative adjustment decreases stock', function () {
    $this->service->adjust($this->variant, -3, 'Bahan rusak');

    expect($this->variant->fresh()->stock)->toBe(47);
});

test('adjustment cannot make stock negative', function () {
    expect(fn () => $this->service->adjust($this->variant, -999, 'Too much'))
        ->toThrow(\Exception::class, 'stok');
});
```

### 3.3 `Services/BadgeHelperServiceTest.php`

```php
<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BadgeHelperService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
    $this->service = app(BadgeHelperService::class);
});

test('detects low stock variants', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 3,
    ]);

    $badges = $this->service->generate($this->tenant);
    $lowStock = collect($badges)->firstWhere('type', 'low_stock');

    expect($lowStock)->not->toBeNull();
    expect($lowStock['count'])->toBe(1);
});

test('detects out of stock variants', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 0,
    ]);

    $badges = $this->service->generate($this->tenant);
    $outOfStock = collect($badges)->firstWhere('type', 'out_of_stock');

    expect($outOfStock)->not->toBeNull();
});

test('detects near expiry variants', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 10,
        'expiry_date' => now()->addDays(3),
    ]);

    $badges = $this->service->generate($this->tenant);
    $nearExpiry = collect($badges)->firstWhere('type', 'near_expiry');

    expect($nearExpiry)->not->toBeNull();
});

test('does not show badges for other tenants', function () {
    $otherTenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 0, // out of stock, but belongs to OTHER tenant
    ]);

    $badges = $this->service->generate($this->tenant);

    expect($badges)->toBeEmpty();
});
```

---

## 4. Tenant Isolation Tests

**CRITICAL** — Ini adalah tes paling penting untuk keamanan data.

### 4.1 `TenantIsolation/TenantIsolationTest.php`

```php
<?php

use App\Models\Category;
use App\Models\CashDrawer;
use App\Models\ModifierGroup;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    // Tenant A
    $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
    $this->ownerA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'owner',
    ]);

    // Tenant B
    $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
    $this->ownerB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'role' => 'owner',
    ]);

    // Data Tenant A
    $this->categoryA = Category::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Cat A']);
    $this->productA = Product::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Prod A']);

    // Data Tenant B
    $this->categoryB = Category::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Cat B']);
    $this->productB = Product::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Prod B']);
});

// --- Global Scope Tests ---

test('user A only sees categories from tenant A', function () {
    $this->actingAs($this->ownerA);

    $categories = Category::all();

    expect($categories)->toHaveCount(1);
    expect($categories->first()->name)->toBe('Cat A');
});

test('user B only sees categories from tenant B', function () {
    $this->actingAs($this->ownerB);

    $categories = Category::all();

    expect($categories)->toHaveCount(1);
    expect($categories->first()->name)->toBe('Cat B');
});

test('user A only sees products from tenant A', function () {
    $this->actingAs($this->ownerA);

    $products = Product::all();

    expect($products)->toHaveCount(1);
    expect($products->first()->name)->toBe('Prod A');
});

// --- Auto-assign tenant_id ---

test('new category automatically gets tenant_id from logged user', function () {
    $this->actingAs($this->ownerA);

    $category = Category::create(['name' => 'New Cat']);

    expect($category->tenant_id)->toBe($this->tenantA->id);
});

// --- API endpoint isolation ---

test('user A cannot access tenant B products via API', function () {
    $this->actingAs($this->ownerA)
        ->get("/owner/products/{$this->productB->id}/edit")
        ->assertStatus(404); // Global scope makes it invisible
});

test('user A cannot delete tenant B category', function () {
    $this->actingAs($this->ownerA)
        ->delete("/owner/categories/{$this->categoryB->id}")
        ->assertStatus(404);
});

// Repeat for all models with BelongsToTenant:
// - ModifierGroup
// - PaymentMethod
// - Transaction
// - StockMovement
// - CashDrawer

test('all models with BelongsToTenant are properly scoped', function () {
    $modelsToTest = [
        ModifierGroup::class,
        PaymentMethod::class,
        Transaction::class,
        StockMovement::class,
        CashDrawer::class,
    ];

    foreach ($modelsToTest as $modelClass) {
        // Create one record for each tenant
        $modelClass::factory()->create(['tenant_id' => $this->tenantA->id]);
        $modelClass::factory()->create(['tenant_id' => $this->tenantB->id]);
    }

    // Login as owner A
    $this->actingAs($this->ownerA);

    foreach ($modelsToTest as $modelClass) {
        $results = $modelClass::all();
        expect($results->every(fn($r) => $r->tenant_id === $this->tenantA->id))->toBeTrue(
            "Tenant isolation failed for {$modelClass}"
        );
    }
});
```

---

## 5. QA Checklist

Checklist komprehensif yang harus diverifikasi sebelum deployment. Gabungan dari v1.1 Section 13 + tambahan.

### 5.1 Tenant Isolation ✅
- [ ] Semua model dengan `tenant_id` menggunakan trait `BelongsToTenant`
- [ ] User tenant A TIDAK bisa melihat data tenant B (test automated)
- [ ] User tenant A TIDAK bisa edit/delete data tenant B (test automated)
- [ ] Record baru otomatis dapat tenant_id dari user yang login
- [ ] Middleware `EnsureTenant` menolak user tanpa tenant_id

### 5.2 Soft Deletes ✅
- [ ] Semua 6 model yang bisa dihapus user menggunakan `SoftDeletes`
- [ ] Semua dropdown/list di frontend TIDAK menampilkan data soft-deleted
- [ ] Hapus kategori → produk pindah ke `category_id = NULL`
- [ ] Hapus produk → variants ikut soft delete
- [ ] Hapus modifier group → modifiers soft delete + pivot hard delete
- [ ] Laporan historis menggunakan `->withTrashed()` jika perlu data lama

### 5.3 Transaksi ✅
- [ ] Checkout atomic (semua berhasil atau semua rollback)
- [ ] Snapshot harga tersimpan di `transaction_items` (bukan referensi)
- [ ] Snapshot modifier tersimpan di `transaction_item_modifiers`
- [ ] Transaction code unik per hari per tenant
- [ ] `lockForUpdate()` mencegah race condition pada stok
- [ ] `lockForUpdate()` mencegah duplicate transaction code
- [ ] Total bayar >= total belanja (validasi)
- [ ] Kembalian dihitung benar
- [ ] Void mengembalikan stok + catat movement
- [ ] Void hanya bisa owner + hanya transaksi hari ini

### 5.4 Cash Drawer ✅
- [ ] Kasir tidak bisa buka 2 sesi bersamaan
- [ ] Kasir harus buka kas sebelum bisa transaksi
- [ ] Expected amount = opening + semua payment transactions
- [ ] Difference = closing - expected
- [ ] Rekap per metode pembayaran benar

### 5.5 Stok ✅
- [ ] Stok berkurang saat checkout
- [ ] Stok bertambah saat restock
- [ ] Adjustment bisa positif/negatif
- [ ] Stok tidak bisa negatif setelah adjustment
- [ ] Semua perubahan stok tercatat di stock_movements

### 5.6 Badge ✅
- [ ] Stok kritis (≤ 5, > 0) terdeteksi
- [ ] Stok habis (= 0) terdeteksi
- [ ] Dead stock (0 penjualan 30 hari, stok > 0) terdeteksi
- [ ] Near expiry (expiry_date ≤ 7 hari, stok > 0) terdeteksi
- [ ] Badge tidak menampilkan data tenant lain

### 5.7 Image Upload ✅
- [ ] Upload JPG/PNG/WEBP berhasil
- [ ] File > 5 MB ditolak
- [ ] Gambar dikonversi ke WEBP
- [ ] Gambar tersimpan di path yang benar (`storage/products/{tenant_id}/`)
- [ ] `storage:link` sudah dijalankan

### 5.8 Auth & Role ✅
- [ ] Login berhasil → redirect sesuai role
- [ ] Login gagal → error message
- [ ] Cashier tidak bisa akses route owner
- [ ] Owner bisa akses route cashier
- [ ] Logout membersihkan session

---

## 6. Deployment Preparation

### 6.1 Factories (harus dibuat untuk tests)

Buat factories untuk semua model di `database/factories/`:

```
database/factories/
├── TenantFactory.php
├── UserFactory.php           ← update existing
├── CategoryFactory.php
├── ProductFactory.php
├── ProductVariantFactory.php
├── ModifierGroupFactory.php
├── ModifierFactory.php
├── PaymentMethodFactory.php
├── TransactionFactory.php
├── CashDrawerFactory.php
├── StockMovementFactory.php
└── TransactionPaymentFactory.php
```

Template factory:
```php
// TenantFactory.php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
        ];
    }
}
```

### 6.2 Environment Checklist

```bash
# Production .env essentials
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=sapi_pos
DB_USERNAME=sapi_user
DB_PASSWORD=<strong-password>

SESSION_DRIVER=database
SANCTUM_STATEFUL_DOMAINS=your-domain.com
```

### 6.3 Deploy Commands

```bash
# 1. Install dependencies
composer install --optimize-autoloader --no-dev
npm install && npm run build

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database
php artisan migrate --force

# 4. Storage
php artisan storage:link

# 5. Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Seed (hanya pertama kali, atau buat admin seeder terpisah)
# php artisan db:seed --class=ProductionSeeder
```

### 6.4 Production Seeder (Minimal)

Buat `database/seeders/ProductionSeeder.php` yang hanya membuat 1 tenant + 1 owner pertama kali:

```php
<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'My Business']
        );

        User::firstOrCreate(
            ['email' => 'admin@sapi.com'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Admin',
                'password' => Hash::make('change-me-immediately'),
                'role' => 'owner',
            ]
        );

        // Default payment method
        PaymentMethod::firstOrCreate(
            ['tenant_id' => $tenant->id, 'type' => 'cash'],
            ['name' => 'Cash', 'is_active' => true]
        );
    }
}
```

---

## 7. Checklist Phase 6

- [ ] Semua factories dibuat untuk model yang dibutuhkan tests
- [ ] Feature tests passing: Auth, POS, CashDrawer
- [ ] Unit tests passing: TransactionService, StockService, BadgeHelperService
- [ ] Tenant isolation tests passing (CRITICAL)
- [ ] QA checklist Section 5.1–5.8 semua terverifikasi ✅
- [ ] `php artisan test` — semua green
- [ ] `npm run build` — no errors
- [ ] Production seeder dibuat
- [ ] Deployment commands didokumentasikan
- [ ] `.env.example` diperbarui dengan semua variable yang dibutuhkan

### Commands

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test --filter=TenantIsolationTest

# Run with coverage
php artisan test --coverage

# Build frontend
npm run build
```
