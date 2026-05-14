# PHASE 1 — Foundation (Database, Models, Auth, Tenant Isolation)

**Status:** Belum dimulai  
**Estimasi:** Fase pertama, harus selesai sebelum fase lainnya  
**Dependency:** Tidak ada (fase awal)  
**Output:** Semua migration, model, trait, scope, middleware, auth flow siap digunakan

---

## Daftar Isi
1. [Prerequisites & Setup](#1-prerequisites--setup)
2. [Database Migrations](#2-database-migrations)
3. [Eloquent Models](#3-eloquent-models)
4. [Tenant Isolation (Trait + Scope)](#4-tenant-isolation)
5. [Middleware](#5-middleware)
6. [Authentication](#6-authentication)
7. [Base Routing Structure](#7-base-routing-structure)
8. [Database Seeder (Development)](#8-database-seeder)
9. [Checklist Phase 1](#9-checklist)

---

## 1. Prerequisites & Setup

### 1.1 Environment Requirements
| Requirement | Version |
|---|---|
| PHP | >= 8.2 |
| MySQL | >= 8.0 |
| Node.js | >= 18 |
| Composer | >= 2.x |
| Laravel | 12.x |

### 1.2 Dependency Installation

```bash
# Backend dependencies (sudah ada dari fresh install)
composer install

# Frontend dependencies
npm install

# Inertia.js + Vue 3
composer require inertiajs/inertia-laravel
npm install @inertiajs/vue3 vue

# Sanctum (sudah included di Laravel 12, verifikasi saja)
# Jika belum ada:
composer require laravel/sanctum

# Intervention Image (untuk Phase 2, tapi install sekarang agar tidak lupa)
composer require intervention/image

# Tailwind CSS v4 (verifikasi sudah terinstall)
npm install -D tailwindcss @tailwindcss/vite
```

### 1.3 Konfigurasi Awal

**`.env`**
```env
APP_NAME=SAPI
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sapi_pos
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SANCTUM_STATEFUL_DOMAINS=localhost:8000,localhost:5173
```

**`config/sanctum.php`** — Pastikan stateful domains sesuai:
```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost')),
```

**`vite.config.js`** — Setup Inertia + Vue:
```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
```

---

## 2. Database Migrations

### Urutan file migration (WAJIB sesuai urutan ini karena FK dependency):

```
database/migrations/
├── 0001_01_01_000000_create_users_table.php          ← KEEP (Laravel default, akan dimodifikasi)
├── 0001_01_01_000001_create_cache_table.php           ← KEEP
├── 0001_01_01_000002_create_jobs_table.php            ← KEEP
├── 2026_03_06_000001_create_tenants_table.php
├── 2026_03_06_000002_update_users_table.php           ← ALTER existing users table
├── 2026_03_06_000003_create_cash_drawers_table.php
├── 2026_03_06_000004_create_categories_table.php
├── 2026_03_06_000005_create_products_table.php
├── 2026_03_06_000006_create_product_variants_table.php
├── 2026_03_06_000007_create_modifier_groups_table.php
├── 2026_03_06_000008_create_modifiers_table.php
├── 2026_03_06_000009_create_product_modifier_groups_table.php
├── 2026_03_06_000010_create_payment_methods_table.php
├── 2026_03_06_000011_create_transactions_table.php
├── 2026_03_06_000012_create_transaction_payments_table.php
├── 2026_03_06_000013_create_transaction_items_table.php
├── 2026_03_06_000014_create_transaction_item_modifiers_table.php
└── 2026_03_06_000015_create_stock_movements_table.php
```

### 2.1 `create_tenants_table`

```php
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('logo')->nullable();
    $table->timestamps();
});
```

### 2.2 `update_users_table`

Karena Laravel sudah punya migration `create_users_table`, kita ALTER saja:

```php
// up()
Schema::table('users', function (Blueprint $table) {
    $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->cascadeOnDelete();
    $table->enum('role', ['owner', 'cashier'])->default('cashier')->after('password');
    
    // Drop kolom yang tidak dipakai (opsional)
    // $table->dropColumn('email_verified_at'); // hapus jika tidak pakai email verification
});

// down()
Schema::table('users', function (Blueprint $table) {
    $table->dropForeign(['tenant_id']);
    $table->dropColumn(['tenant_id', 'role']);
});
```

**Catatan:** `tenant_id` sementara nullable agar migration tidak error. Seeder akan mengisi data awal.

### 2.3 `create_cash_drawers_table`

```php
Schema::create('cash_drawers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->decimal('opening_amount', 12, 2);
    $table->decimal('closing_amount', 12, 2)->nullable();
    $table->decimal('expected_amount', 12, 2)->nullable();
    $table->decimal('difference', 12, 2)->nullable();
    $table->text('notes')->nullable();
    $table->timestamp('opened_at');
    $table->timestamp('closed_at')->nullable();
    $table->timestamps();
});
```

### 2.4 `create_categories_table`

```php
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->string('name');
    $table->timestamps();
    $table->softDeletes(); // ← v1.1: SoftDeletes
});
```

### 2.5 `create_products_table`

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
    $table->string('name');
    $table->string('image')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes(); // ← v1.1: SoftDeletes
});
```

**Catatan:** `category_id` → `nullOnDelete()` agar saat kategori dihapus, produk otomatis pindah ke "tanpa kategori" (category_id = NULL).

### 2.6 `create_product_variants_table`

```php
Schema::create('product_variants', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->string('name');                        // "M", "Panas", "Large"
    $table->string('sku', 100)->nullable();
    $table->decimal('price', 12, 2);               // harga jual
    $table->decimal('cost_price', 12, 2);          // harga modal
    $table->integer('stock')->default(0);
    $table->date('expiry_date')->nullable();        // ← v1.1: tanggal kedaluwarsa
    $table->timestamps();
    $table->softDeletes();                          // ← v1.1: SoftDeletes
});
```

### 2.7 `create_modifier_groups_table`

```php
Schema::create('modifier_groups', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->string('name');                        // "Temperature", "Sugar Level"
    $table->boolean('is_required')->default(false);
    $table->boolean('is_multiple')->default(false);
    $table->timestamps();
    $table->softDeletes(); // ← v1.1
});
```

### 2.8 `create_modifiers_table`

```php
Schema::create('modifiers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('modifier_group_id')->constrained('modifier_groups')->cascadeOnDelete();
    $table->string('name');                        // "Extra Shot", "Less Sugar"
    $table->decimal('extra_price', 12, 2)->default(0);
    $table->timestamps();
    $table->softDeletes(); // ← v1.1
});
```

### 2.9 `create_product_modifier_groups_table`

```php
Schema::create('product_modifier_groups', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
    $table->foreignId('modifier_group_id')->constrained('modifier_groups')->cascadeOnDelete();

    $table->unique(['product_id', 'modifier_group_id'], 'product_modifier_unique');
});
```

**Catatan:** Pivot table — TIDAK pakai SoftDeletes, TIDAK pakai timestamps.

### 2.10 `create_payment_methods_table`

```php
Schema::create('payment_methods', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->string('name');
    $table->enum('type', ['cash', 'qris_static', 'qris_dynamic', 'bank_transfer']);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes(); // ← v1.1
});
```

### 2.11 `create_transactions_table`

```php
Schema::create('transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users');                // kasir
    $table->string('code', 50)->unique();                              // TRX-20260305-001
    $table->enum('status', ['pending', 'completed', 'voided']);        // ← RECONCILED dari v1.0
    $table->decimal('total_amount', 12, 2);
    $table->decimal('change_amount', 12, 2)->default(0);
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

**Referensi CHANGELOG:** `[RECONCILE] Transaction Status Naming`

### 2.12 `create_transaction_payments_table`

```php
Schema::create('transaction_payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
    $table->foreignId('payment_method_id')->constrained('payment_methods');
    $table->decimal('amount', 12, 2);
    $table->string('reference_code')->nullable();  // untuk QRIS/transfer
    $table->timestamp('created_at')->nullable();
});
```

### 2.13 `create_transaction_items_table`

```php
Schema::create('transaction_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
    $table->foreignId('product_variant_id')->constrained('product_variants');
    $table->string('variant_name');                // SNAPSHOT
    $table->integer('qty');
    $table->decimal('unit_price', 12, 2);          // SNAPSHOT
    $table->decimal('subtotal', 12, 2);
});
```

### 2.14 `create_transaction_item_modifiers_table`

```php
Schema::create('transaction_item_modifiers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('transaction_item_id')->constrained('transaction_items')->cascadeOnDelete();
    $table->foreignId('modifier_id')->constrained('modifiers');
    $table->string('modifier_name');               // SNAPSHOT
    $table->decimal('extra_price', 12, 2);         // SNAPSHOT
});
```

### 2.15 `create_stock_movements_table`

```php
Schema::create('stock_movements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
    $table->foreignId('product_variant_id')->constrained('product_variants');
    $table->enum('type', ['sale', 'restock', 'adjustment']);  // ← RECONCILED dari v1.0
    $table->integer('qty');                        // positif = masuk, negatif = keluar
    $table->text('notes')->nullable();
    $table->unsignedBigInteger('reference_id')->nullable();  // FK ke transactions.id jika type=sale
    $table->timestamp('created_at')->nullable();
});
```

**Referensi CHANGELOG:** `[RECONCILE] Stock Movement Type Naming`

---

## 3. Eloquent Models

Semua model dibuat di `app/Models/`. Berikut spesifikasi lengkap per model.

### 3.1 `Tenant.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'logo'];

    // --- Relationships ---
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function cashDrawers(): HasMany
    {
        return $this->hasMany(CashDrawer::class);
    }
}
```

**TIDAK pakai** `BelongsToTenant` trait — ini model tenant itu sendiri.

### 3.2 `User.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'password', 'role',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // --- Helpers ---
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cashDrawers(): HasMany
    {
        return $this->hasMany(CashDrawer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
```

**TIDAK pakai** `BelongsToTenant` trait — auth belum aktif saat login. Filter manual via `tenant_id`.

### 3.3 `CashDrawer.php`

```php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDrawer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'opening_amount', 'closing_amount',
        'expected_amount', 'difference', 'notes', 'opened_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'opening_amount' => 'decimal:2',
            'closing_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    // --- Helpers ---
    public function isOpen(): bool
    {
        return is_null($this->closed_at);
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

### 3.4 `Category.php`

```php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = ['tenant_id', 'name'];

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
```

### 3.5 `Product.php`

```php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'category_id', 'name', 'image', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'product_modifier_groups');
    }
}
```

### 3.6 `ProductVariant.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id', 'name', 'sku', 'price', 'cost_price', 'stock', 'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }

    // --- Relationships ---
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
```

**TIDAK pakai** `BelongsToTenant` — isolasi lewat parent `Product`.

### 3.7 `ModifierGroup.php`

```php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ModifierGroup extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'is_required', 'is_multiple'];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_multiple' => 'boolean',
        ];
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_modifier_groups');
    }
}
```

### 3.8 `Modifier.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Modifier extends Model
{
    use SoftDeletes;

    protected $fillable = ['modifier_group_id', 'name', 'extra_price'];

    protected function casts(): array
    {
        return [
            'extra_price' => 'decimal:2',
        ];
    }

    // --- Relationships ---
    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
```

**TIDAK pakai** `BelongsToTenant` — isolasi lewat parent `ModifierGroup`.

### 3.9 `PaymentMethod.php`

```php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $fillable = ['tenant_id', 'name', 'type', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
```

### 3.10 `Transaction.php`

```php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use BelongsToTenant;

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_VOIDED = 'voided';

    protected $fillable = [
        'tenant_id', 'user_id', 'code', 'status',
        'total_amount', 'change_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
        ];
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class);
    }
}
```

### 3.11 `TransactionItem.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transaction_id', 'product_variant_id',
        'variant_name', 'qty', 'unit_price', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    // --- Relationships ---
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function modifiers(): HasMany
    {
        return $this->hasMany(TransactionItemModifier::class);
    }
}
```

### 3.12 `TransactionPayment.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionPayment extends Model
{
    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $fillable = [
        'transaction_id', 'payment_method_id', 'amount', 'reference_code',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    // --- Relationships ---
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
```

### 3.13 `TransactionItemModifier.php`

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItemModifier extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'transaction_item_id', 'modifier_id', 'modifier_name', 'extra_price',
    ];

    protected function casts(): array
    {
        return [
            'extra_price' => 'decimal:2',
        ];
    }

    // --- Relationships ---
    public function transactionItem(): BelongsTo
    {
        return $this->belongsTo(TransactionItem::class);
    }

    public function modifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class);
    }
}
```

### 3.14 `StockMovement.php`

```php
namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    // Type constants
    const TYPE_SALE = 'sale';
    const TYPE_RESTOCK = 'restock';
    const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'tenant_id', 'product_variant_id', 'type', 'qty', 'notes', 'reference_id',
    ];

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'reference_id');
    }
}
```

---

## 4. Tenant Isolation

### 4.1 Trait `BelongsToTenant`

**File:** `app/Traits/BelongsToTenant.php`

```php
<?php

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

### 4.2 `TenantScope`

**File:** `app/Models/Scopes/TenantScope.php`

```php
<?php

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

### 4.3 Mapping Model → Trait

| Model | `BelongsToTenant` | `SoftDeletes` | Alasan |
|---|---|---|---|
| `Tenant` | ❌ | ❌ | Model tenant itu sendiri |
| `User` | ❌ | ❌ | Auth belum aktif saat login |
| `CashDrawer` | ✅ | ❌ | Punya tenant_id, tidak perlu soft delete |
| `Category` | ✅ | ✅ | Punya tenant_id, direferensi oleh products |
| `Product` | ✅ | ✅ | Punya tenant_id, direferensi oleh variants |
| `ProductVariant` | ❌ | ✅ | Isolasi lewat Product parent |
| `ModifierGroup` | ✅ | ✅ | Punya tenant_id, direferensi oleh modifiers |
| `Modifier` | ❌ | ✅ | Isolasi lewat ModifierGroup parent |
| `PaymentMethod` | ✅ | ✅ | Punya tenant_id, direferensi oleh payments |
| `Transaction` | ✅ | ❌ | Punya tenant_id, data transaksi immutable |
| `TransactionItem` | ❌ | ❌ | Isolasi lewat Transaction parent |
| `TransactionPayment` | ❌ | ❌ | Isolasi lewat Transaction parent |
| `TransactionItemModifier` | ❌ | ❌ | Isolasi lewat TransactionItem parent |
| `StockMovement` | ✅ | ❌ | Punya tenant_id, log immutable |

---

## 5. Middleware

### 5.1 `EnsureTenant`

**File:** `app/Http/Middleware/EnsureTenant.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!auth()->user()->tenant_id) {
            abort(403, 'User tidak terhubung ke tenant manapun.');
        }

        return $next($request);
    }
}
```

### 5.2 `EnsureRole`

**File:** `app/Http/Middleware/EnsureRole.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check() || !in_array(auth()->user()->role, $roles)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
```

### 5.3 Registrasi Middleware

**File:** `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    // Inertia middleware
    $middleware->web(append: [
        \App\Http\Middleware\HandleInertiaRequests::class,
    ]);

    // Alias middleware
    $middleware->alias([
        'tenant' => \App\Http\Middleware\EnsureTenant::class,
        'role'   => \App\Http\Middleware\EnsureRole::class,
    ]);
})
```

---

## 6. Authentication

### 6.1 Inertia Middleware

**File:** `app/Http/Middleware/HandleInertiaRequests.php`

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'tenant_id' => $request->user()->tenant_id,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
```

### 6.2 Blade Root Template

**File:** `resources/views/app.blade.php`

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'SAPI') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
```

### 6.3 Inertia App Setup

**File:** `resources/js/app.js`

```js
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

createInertiaApp({
    title: (title) => title ? `${title} — SAPI` : 'SAPI',
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
```

### 6.4 AuthController

**File:** `app/Http/Controllers/Auth/AuthController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Redirect berdasarkan role
            if ($user->isOwner()) {
                return redirect()->intended('/owner/dashboard');
            }

            return redirect()->intended('/cashier/pos');
        }

        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
```

### 6.5 Login Vue Page (Skeleton)

**File:** `resources/js/Pages/Auth/Login.vue`

```vue
<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center bg-gray-100">
        <div class="w-full max-w-md bg-white rounded-lg shadow-md p-8">
            <h1 class="text-2xl font-bold text-center mb-6">SAPI — Login</h1>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="w-full border rounded-lg px-3 py-2"
                        required
                    />
                    <p v-if="form.errors.email" class="text-red-500 text-sm mt-1">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Password</label>
                    <input
                        v-model="form.password"
                        type="password"
                        class="w-full border rounded-lg px-3 py-2"
                        required
                    />
                </div>

                <div class="flex items-center">
                    <input v-model="form.remember" type="checkbox" class="mr-2" />
                    <label class="text-sm">Ingat saya</label>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full bg-blue-600 text-white py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50"
                >
                    {{ form.processing ? 'Memproses...' : 'Login' }}
                </button>
            </form>
        </div>
    </div>
</template>
```

---

## 7. Base Routing Structure

**File:** `routes/web.php`

```php
<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

// --- Auth (Guest) ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// --- Owner Routes ---
Route::middleware(['auth', 'tenant', 'role:owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        // Diisi di Phase 2, 4, 5
        // Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    });

// --- Cashier Routes (owner juga bisa akses) ---
Route::middleware(['auth', 'tenant', 'role:cashier,owner'])
    ->prefix('cashier')
    ->name('cashier.')
    ->group(function () {
        // Diisi di Phase 3
        // Route::get('/pos', [POSController::class, 'index'])->name('pos');
    });

// Redirect root ke login atau dashboard
Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->isOwner()
            ? redirect('/owner/dashboard')
            : redirect('/cashier/pos');
    }
    return redirect('/login');
});
```

---

## 8. Database Seeder

### 8.1 `DatabaseSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tenant
        $tenant = Tenant::create([
            'name' => 'Kopi Nusantara',
            'slug' => 'kopi-nusantara',
        ]);

        // 2. Users
        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner Demo',
            'email' => 'owner@sapi.test',
            'password' => Hash::make('password'),
            'role' => 'owner',
        ]);

        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Kasir Demo',
            'email' => 'kasir@sapi.test',
            'password' => Hash::make('password'),
            'role' => 'cashier',
        ]);

        // 3. Categories
        $kopi = Category::create(['tenant_id' => $tenant->id, 'name' => 'Kopi']);
        $nonKopi = Category::create(['tenant_id' => $tenant->id, 'name' => 'Non-Kopi']);
        $makanan = Category::create(['tenant_id' => $tenant->id, 'name' => 'Makanan']);

        // 4. Products + Variants
        $espresso = Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $kopi->id,
            'name' => 'Espresso',
            'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $espresso->id,
            'name' => 'Single',
            'price' => 18000,
            'cost_price' => 5000,
            'stock' => 100,
        ]);
        ProductVariant::create([
            'product_id' => $espresso->id,
            'name' => 'Double',
            'price' => 25000,
            'cost_price' => 8000,
            'stock' => 100,
        ]);

        $latte = Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $kopi->id,
            'name' => 'Cafe Latte',
            'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $latte->id,
            'name' => 'Hot',
            'price' => 28000,
            'cost_price' => 8000,
            'stock' => 50,
        ]);
        ProductVariant::create([
            'product_id' => $latte->id,
            'name' => 'Iced',
            'price' => 30000,
            'cost_price' => 9000,
            'stock' => 50,
        ]);

        $matcha = Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $nonKopi->id,
            'name' => 'Matcha Latte',
            'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $matcha->id,
            'name' => 'Regular',
            'price' => 32000,
            'cost_price' => 12000,
            'stock' => 30,
        ]);

        $croissant = Product::create([
            'tenant_id' => $tenant->id,
            'category_id' => $makanan->id,
            'name' => 'Croissant',
            'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $croissant->id,
            'name' => 'Plain',
            'price' => 25000,
            'cost_price' => 10000,
            'stock' => 20,
            'expiry_date' => now()->addDays(3), // Untuk test badge near-expiry
        ]);

        // 5. Modifier Groups + Modifiers
        $tempGroup = ModifierGroup::create([
            'tenant_id' => $tenant->id,
            'name' => 'Temperature',
            'is_required' => true,
            'is_multiple' => false,
        ]);
        Modifier::create(['modifier_group_id' => $tempGroup->id, 'name' => 'Hot', 'extra_price' => 0]);
        Modifier::create(['modifier_group_id' => $tempGroup->id, 'name' => 'Iced', 'extra_price' => 3000]);

        $sugarGroup = ModifierGroup::create([
            'tenant_id' => $tenant->id,
            'name' => 'Sugar Level',
            'is_required' => false,
            'is_multiple' => false,
        ]);
        Modifier::create(['modifier_group_id' => $sugarGroup->id, 'name' => 'Normal', 'extra_price' => 0]);
        Modifier::create(['modifier_group_id' => $sugarGroup->id, 'name' => 'Less Sugar', 'extra_price' => 0]);
        Modifier::create(['modifier_group_id' => $sugarGroup->id, 'name' => 'Extra Sweet', 'extra_price' => 0]);

        $addonGroup = ModifierGroup::create([
            'tenant_id' => $tenant->id,
            'name' => 'Add-ons',
            'is_required' => false,
            'is_multiple' => true,
        ]);
        Modifier::create(['modifier_group_id' => $addonGroup->id, 'name' => 'Extra Shot', 'extra_price' => 5000]);
        Modifier::create(['modifier_group_id' => $addonGroup->id, 'name' => 'Oat Milk', 'extra_price' => 8000]);

        // 6. Attach modifier groups ke produk kopi
        $espresso->modifierGroups()->attach([$tempGroup->id, $sugarGroup->id, $addonGroup->id]);
        $latte->modifierGroups()->attach([$tempGroup->id, $sugarGroup->id, $addonGroup->id]);
        $matcha->modifierGroups()->attach([$tempGroup->id, $sugarGroup->id]);

        // 7. Payment Methods
        PaymentMethod::create(['tenant_id' => $tenant->id, 'name' => 'Cash', 'type' => 'cash', 'is_active' => true]);
        PaymentMethod::create(['tenant_id' => $tenant->id, 'name' => 'QRIS', 'type' => 'qris_static', 'is_active' => true]);
        PaymentMethod::create(['tenant_id' => $tenant->id, 'name' => 'Transfer BCA', 'type' => 'bank_transfer', 'is_active' => true]);
    }
}
```

---

## 9. Checklist Phase 1

Sebelum lanjut ke Phase 2, pastikan semua item berikut ✅:

- [ ] Semua dependency terinstall (`composer install`, `npm install`, packages tambahan)
- [ ] `vite.config.js` dikonfigurasi dengan Vue + Tailwind
- [ ] Semua 15+ migration file dibuat dan bisa di-run tanpa error (`php artisan migrate:fresh`)
- [ ] Semua 14 model dibuat dengan relasi, fillable, dan casts yang benar
- [ ] `BelongsToTenant` trait dan `TenantScope` dibuat dan berfungsi
- [ ] `EnsureTenant` dan `EnsureRole` middleware dibuat dan diregistrasi
- [ ] `HandleInertiaRequests` middleware dibuat dan diregistrasi
- [ ] `AuthController` dengan login/logout berfungsi
- [ ] Root blade template (`app.blade.php`) dan `app.js` Inertia setup benar
- [ ] `Login.vue` bisa diakses dan login berhasil redirect sesuai role
- [ ] Seeder bisa dijalankan (`php artisan db:seed`) tanpa error
- [ ] Login sebagai owner → redirect ke `/owner/dashboard` (halaman kosong OK)
- [ ] Login sebagai kasir → redirect ke `/cashier/pos` (halaman kosong OK)
- [ ] Route structure dengan middleware group sudah terdaftar

### Commands untuk Verifikasi

```bash
php artisan migrate:fresh --seed
php artisan route:list
php artisan serve
npm run dev
```

Login credentials:
- Owner: `owner@sapi.test` / `password`
- Kasir: `kasir@sapi.test` / `password`
