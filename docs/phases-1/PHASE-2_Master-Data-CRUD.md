# PHASE 2 — Master Data CRUD (Kategori, Produk, Modifier, Payment Method)

**Status:** Belum dimulai  
**Estimasi:** Setelah Phase 1 selesai  
**Dependency:** Phase 1 (semua migration, model, auth, middleware harus ready)  
**Output:** CRUD lengkap untuk semua master data, image upload, form requests, pages Vue

---

## Daftar Isi
1. [Overview Endpoint & Akses](#1-overview-endpoint--akses)
2. [Form Requests (Validasi)](#2-form-requests)
3. [Controllers](#3-controllers)
4. [Image Service](#4-image-service)
5. [Soft Delete Behavior](#5-soft-delete-behavior)
6. [Vue Pages & Components](#6-vue-pages--components)
7. [Routes](#7-routes)
8. [Checklist Phase 2](#8-checklist)

---

## 1. Overview Endpoint & Akses

Semua endpoint master data hanya bisa diakses oleh **owner**.

| Resource | Endpoint | Methods | Controller |
|---|---|---|---|
| Categories | `/owner/categories` | index, store, update, destroy | `CategoryController` |
| Products | `/owner/products` | index, create, store, edit, update, destroy | `ProductController` |
| Variants | `/owner/products/{product}/variants` | store, update, destroy | `VariantController` |
| Modifier Groups | `/owner/modifiers` | index, store, update, destroy | `ModifierController` |
| Payment Methods | `/owner/payment-methods` | index, store, update, destroy | `PaymentMethodController` |

---

## 2. Form Requests

### 2.1 `StoreCategoryRequest`

**File:** `app/Http/Requests/StoreCategoryRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Role sudah dicek di middleware
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
        ];
    }
}
```

### 2.2 `UpdateCategoryRequest`

Sama dengan Store, bisa pakai class yang sama atau buat terpisah jika ada kebutuhan berbeda nanti.

### 2.3 `StoreProductRequest`

**File:** `app/Http/Requests/StoreProductRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:255',
            'category_id'       => 'nullable|exists:categories,id',
            'image'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120', // 5 MB
            'is_active'         => 'boolean',
            'modifier_group_ids' => 'nullable|array',
            'modifier_group_ids.*' => 'exists:modifier_groups,id',

            // Variants (minimal 1 wajib saat create)
            'variants'              => 'required|array|min:1',
            'variants.*.name'       => 'required|string|max:255',
            'variants.*.sku'        => 'nullable|string|max:100',
            'variants.*.price'      => 'required|numeric|min:0',
            'variants.*.cost_price' => 'required|numeric|min:0',
            'variants.*.stock'      => 'required|integer|min:0',
            'variants.*.expiry_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'variants.required' => 'Minimal 1 varian produk harus diisi.',
            'variants.*.price.required' => 'Harga jual wajib diisi.',
            'variants.*.cost_price.required' => 'Harga modal wajib diisi.',
            'image.max' => 'Ukuran gambar maksimal 5 MB.',
        ];
    }
}
```

### 2.4 `UpdateProductRequest`

**File:** `app/Http/Requests/UpdateProductRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:255',
            'category_id'       => 'nullable|exists:categories,id',
            'image'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'is_active'         => 'boolean',
            'modifier_group_ids' => 'nullable|array',
            'modifier_group_ids.*' => 'exists:modifier_groups,id',
        ];
    }
}
```

### 2.5 `StoreVariantRequest`

**File:** `app/Http/Requests/StoreVariantRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'sku'         => 'nullable|string|max:100',
            'price'       => 'required|numeric|min:0',
            'cost_price'  => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'expiry_date' => 'nullable|date',
        ];
    }
}
```

### 2.6 `StoreModifierGroupRequest`

**File:** `app/Http/Requests/StoreModifierGroupRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreModifierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'is_required' => 'boolean',
            'is_multiple' => 'boolean',
            'modifiers'            => 'required|array|min:1',
            'modifiers.*.name'     => 'required|string|max:255',
            'modifiers.*.extra_price' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'modifiers.required' => 'Minimal 1 modifier harus diisi.',
        ];
    }
}
```

### 2.7 `StorePaymentMethodRequest`

**File:** `app/Http/Requests/StorePaymentMethodRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'type'      => 'required|in:cash,qris_static,qris_dynamic,bank_transfer',
            'is_active' => 'boolean',
        ];
    }
}
```

---

## 3. Controllers

### 3.1 `CategoryController`

**File:** `app/Http/Controllers/Owner/CategoryController.php`

```php
<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::withCount('products')->latest()->get();

        return Inertia::render('Owner/Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(StoreCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Produk di bawahnya → category_id = NULL (via DB constraint nullOnDelete)
        $category->delete(); // soft delete

        return back()->with('success', 'Kategori berhasil dihapus.');
    }
}
```

### 3.2 `ProductController`

**File:** `app/Http/Controllers/Owner/ProductController.php`

```php
<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private ImageService $imageService
    ) {}

    public function index(): Response
    {
        $products = Product::with(['category:id,name', 'variants:id,product_id,name,price,stock'])
            ->latest()
            ->get();

        return Inertia::render('Owner/Products/Index', [
            'products' => $products,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Owner/Products/Form', [
            'categories' => Category::select('id', 'name')->get(),
            'modifierGroups' => ModifierGroup::select('id', 'name')->get(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Upload image
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->imageService->upload($request->file('image'));
        }

        // Create product
        $product = Product::create([
            'name' => $data['name'],
            'category_id' => $data['category_id'] ?? null,
            'image' => $imagePath,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Create variants
        foreach ($data['variants'] as $variant) {
            $product->variants()->create($variant);
        }

        // Attach modifier groups
        if (!empty($data['modifier_group_ids'])) {
            $product->modifierGroups()->sync($data['modifier_group_ids']);
        }

        return redirect()->route('owner.products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product): Response
    {
        $product->load(['variants', 'modifierGroups:id']);

        return Inertia::render('Owner/Products/Form', [
            'product' => $product,
            'categories' => Category::select('id', 'name')->get(),
            'modifierGroups' => ModifierGroup::select('id', 'name')->get(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();

        // Upload image (replace old)
        if ($request->hasFile('image')) {
            if ($product->image) {
                $this->imageService->delete($product->image);
            }
            $data['image'] = $this->imageService->upload($request->file('image'));
        }

        $product->update($data);

        // Sync modifier groups
        if (isset($data['modifier_group_ids'])) {
            $product->modifierGroups()->sync($data['modifier_group_ids']);
        }

        return redirect()->route('owner.products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Soft delete product + cascade soft delete variants
        $product->variants()->delete(); // soft delete all variants
        $product->delete();             // soft delete product

        return redirect()->route('owner.products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }
}
```

### 3.3 `VariantController`

**File:** `app/Http/Controllers/Owner/VariantController.php`

```php
<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;

class VariantController extends Controller
{
    public function store(StoreVariantRequest $request, Product $product): RedirectResponse
    {
        $product->variants()->create($request->validated());

        return back()->with('success', 'Varian berhasil ditambahkan.');
    }

    public function update(StoreVariantRequest $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        $variant->update($request->validated());

        return back()->with('success', 'Varian berhasil diperbarui.');
    }

    public function destroy(Product $product, ProductVariant $variant): RedirectResponse
    {
        $variant->delete(); // soft delete

        return back()->with('success', 'Varian berhasil dihapus.');
    }
}
```

### 3.4 `ModifierController`

**File:** `app/Http/Controllers/Owner/ModifierController.php`

```php
<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreModifierGroupRequest;
use App\Models\ModifierGroup;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ModifierController extends Controller
{
    public function index(): Response
    {
        $modifierGroups = ModifierGroup::with('modifiers:id,modifier_group_id,name,extra_price')
            ->withCount('products')
            ->latest()
            ->get();

        return Inertia::render('Owner/Modifiers/Index', [
            'modifierGroups' => $modifierGroups,
        ]);
    }

    public function store(StoreModifierGroupRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $group = ModifierGroup::create([
            'name' => $data['name'],
            'is_required' => $data['is_required'] ?? false,
            'is_multiple' => $data['is_multiple'] ?? false,
        ]);

        foreach ($data['modifiers'] as $modifier) {
            $group->modifiers()->create($modifier);
        }

        return back()->with('success', 'Modifier group berhasil ditambahkan.');
    }

    public function update(StoreModifierGroupRequest $request, ModifierGroup $modifierGroup): RedirectResponse
    {
        $data = $request->validated();

        $modifierGroup->update([
            'name' => $data['name'],
            'is_required' => $data['is_required'] ?? false,
            'is_multiple' => $data['is_multiple'] ?? false,
        ]);

        // Sync modifiers: hapus yang lama, buat yang baru
        // Strategi: soft delete lama, create baru
        // ATAU: update existing + create new + soft delete removed
        // Untuk MVP, kita pakai strategi: kirim semua modifier dari frontend,
        // bandingkan dengan yang ada, update/create/delete sesuai kebutuhan
        $existingIds = $modifierGroup->modifiers()->pluck('id')->toArray();
        $incomingIds = collect($data['modifiers'])->pluck('id')->filter()->toArray();

        // Delete modifiers yang tidak ada di incoming
        $toDelete = array_diff($existingIds, $incomingIds);
        if (!empty($toDelete)) {
            $modifierGroup->modifiers()->whereIn('id', $toDelete)->delete(); // soft delete
        }

        // Update/Create modifiers
        foreach ($data['modifiers'] as $modifierData) {
            if (isset($modifierData['id']) && in_array($modifierData['id'], $existingIds)) {
                $modifierGroup->modifiers()->where('id', $modifierData['id'])->update([
                    'name' => $modifierData['name'],
                    'extra_price' => $modifierData['extra_price'],
                ]);
            } else {
                $modifierGroup->modifiers()->create([
                    'name' => $modifierData['name'],
                    'extra_price' => $modifierData['extra_price'],
                ]);
            }
        }

        return back()->with('success', 'Modifier group berhasil diperbarui.');
    }

    public function destroy(ModifierGroup $modifierGroup): RedirectResponse
    {
        // Soft delete group + cascade soft delete modifiers
        $modifierGroup->modifiers()->delete(); // soft delete all modifiers

        // Hard delete pivot entries (product_modifier_groups)
        $modifierGroup->products()->detach();

        $modifierGroup->delete(); // soft delete group

        return back()->with('success', 'Modifier group berhasil dihapus.');
    }
}
```

### 3.5 `PaymentMethodController`

**File:** `app/Http/Controllers/Owner/PaymentMethodController.php`

```php
<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(): Response
    {
        $paymentMethods = PaymentMethod::latest()->get();

        return Inertia::render('Owner/PaymentMethods/Index', [
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        PaymentMethod::create($request->validated());

        return back()->with('success', 'Metode pembayaran berhasil ditambahkan.');
    }

    public function update(StorePaymentMethodRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->update($request->validated());

        return back()->with('success', 'Metode pembayaran berhasil diperbarui.');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $paymentMethod->delete(); // soft delete

        return back()->with('success', 'Metode pembayaran berhasil dihapus.');
    }
}
```

---

## 4. Image Service

**File:** `app/Services/ImageService.php`

```php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageService
{
    /**
     * Upload gambar, konversi ke WEBP, simpan ke storage.
     *
     * @return string Path relatif yang disimpan di database
     */
    public function upload(UploadedFile $file, string $directory = 'products'): string
    {
        $tenantId = auth()->user()->tenant_id;
        $filename = Str::uuid() . '.webp';
        $path = "{$directory}/{$tenantId}";

        // Konversi ke WEBP menggunakan Intervention Image v3
        $image = Image::read($file);
        $encoded = $image->toWebp(quality: 80);

        // Simpan ke storage/app/public/
        Storage::disk('public')->put("{$path}/{$filename}", (string) $encoded);

        return "{$path}/{$filename}";
    }

    /**
     * Hapus gambar dari storage.
     */
    public function delete(string $path): void
    {
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Get public URL untuk gambar.
     */
    public function url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        return Storage::disk('public')->url($path) . '?v=' . now()->timestamp;
    }
}
```

### 4.1 Intervention Image Config

Setelah install `composer require intervention/image`, publish config:
```bash
php artisan vendor:publish --provider="Intervention\Image\Laravel\ServiceProvider"
```

### 4.2 Storage Link

```bash
php artisan storage:link
```

---

## 5. Soft Delete Behavior

Rangkuman perilaku delete per resource (implementasi sudah ada di controller di atas):

| Aksi | Perilaku Detail |
|---|---|
| **Hapus Category** | Soft delete category. Produk di bawahnya → `category_id = NULL` (otomatis via `nullOnDelete` constraint). |
| **Hapus Product** | Soft delete semua `product_variants` milik produk. Soft delete product. |
| **Hapus ProductVariant** | Soft delete variant saja. Stock movement historis tetap utuh. |
| **Hapus ModifierGroup** | Soft delete semua `modifiers` di group. Hard delete entry di pivot `product_modifier_groups`. Soft delete group. |
| **Hapus Modifier** | Soft delete modifier saja. Data transaksi historis tetap utuh (via snapshot). |
| **Hapus PaymentMethod** | Soft delete saja. Rekap pembayaran historis tetap utuh. |

---

## 6. Vue Pages & Components

### 6.1 Struktur File

```
resources/js/Pages/
└── Owner/
    ├── Categories/
    │   └── Index.vue          ← List + inline create/edit + delete
    ├── Products/
    │   ├── Index.vue          ← List produk + variant summary
    │   └── Form.vue           ← Create/Edit form (produk + variants + modifier groups)
    ├── Modifiers/
    │   └── Index.vue          ← List modifier groups + inline modifiers
    └── PaymentMethods/
        └── Index.vue          ← List + inline create/edit + delete
```

### 6.2 Panduan UI per Page

#### `Owner/Categories/Index.vue`
- Tabel/list semua kategori dengan jumlah produk per kategori
- Tombol "Tambah Kategori" → modal/inline form (nama saja)
- Tiap row: tombol Edit (inline) + Hapus (konfirmasi dialog)
- Hapus menampilkan warning: "Produk di kategori ini akan dipindahkan ke 'Tanpa Kategori'"

#### `Owner/Products/Index.vue`
- Card/grid semua produk (gambar, nama, kategori, status active, jumlah variant)
- Filter: by category, by status (active/inactive)
- Tombol "Tambah Produk" → navigasi ke Form.vue
- Tiap card: link ke Edit, tombol Hapus

#### `Owner/Products/Form.vue` (Create & Edit)
- **Section 1:** Info produk (nama, kategori dropdown, gambar upload, is_active toggle)
- **Section 2:** Variants (dynamic form rows — nama, SKU, harga jual, harga modal, stok, expiry date)
  - Tombol "Tambah Varian" untuk menambah row
  - Minimal 1 varian wajib ada
- **Section 3:** Modifier Groups (multi-select checkbox dari modifier groups yang tersedia)
- Tombol Submit

#### `Owner/Modifiers/Index.vue`
- Accordion/expandable list per modifier group
- Group header: nama, is_required badge, is_multiple badge, jumlah produk terkait
- Expanded: list modifiers (nama + extra price)
- Tombol "Tambah Group" → modal form (nama group + is_required + is_multiple + list modifiers)
- Edit/Delete per group

#### `Owner/PaymentMethods/Index.vue`
- Tabel list payment methods (nama, tipe, status active)
- Tombol "Tambah" → modal form (nama, tipe dropdown, is_active toggle)
- Edit/Delete per row

### 6.3 Shared Components (Opsional tapi Rekomendasi)

```
resources/js/Components/
├── ConfirmDialog.vue       ← Reusable confirm delete dialog
├── FlashMessage.vue        ← Tampilkan success/error dari flash session
├── InlineForm.vue          ← Reusable inline edit form wrapper
└── ImageUpload.vue         ← Komponen upload gambar dengan preview
```

---

## 7. Routes

Tambahkan ke `routes/web.php` di dalam group owner:

```php
// --- Owner Routes ---
Route::middleware(['auth', 'tenant', 'role:owner'])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        // Categories
        Route::resource('categories', \App\Http\Controllers\Owner\CategoryController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        // Products
        Route::resource('products', \App\Http\Controllers\Owner\ProductController::class);

        // Variants (nested under products)
        Route::post('products/{product}/variants', [\App\Http\Controllers\Owner\VariantController::class, 'store'])
            ->name('products.variants.store');
        Route::put('products/{product}/variants/{variant}', [\App\Http\Controllers\Owner\VariantController::class, 'update'])
            ->name('products.variants.update');
        Route::delete('products/{product}/variants/{variant}', [\App\Http\Controllers\Owner\VariantController::class, 'destroy'])
            ->name('products.variants.destroy');

        // Modifier Groups
        Route::resource('modifiers', \App\Http\Controllers\Owner\ModifierController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['modifiers' => 'modifierGroup']);

        // Payment Methods
        Route::resource('payment-methods', \App\Http\Controllers\Owner\PaymentMethodController::class)
            ->only(['index', 'store', 'update', 'destroy']);
    });
```

---

## 8. Checklist Phase 2

- [ ] `ImageService` dibuat dan bisa upload + konversi ke WEBP
- [ ] `php artisan storage:link` sudah dijalankan
- [ ] Semua 7 FormRequest dibuat dengan validasi yang benar
- [ ] `CategoryController` — CRUD + soft delete berfungsi
- [ ] `ProductController` — CRUD + image upload + soft delete cascade ke variants
- [ ] `VariantController` — Nested CRUD berfungsi
- [ ] `ModifierController` — CRUD + sync modifiers + soft delete cascade
- [ ] `PaymentMethodController` — CRUD + soft delete berfungsi
- [ ] Routes terdaftar dan `php artisan route:list` menunjukkan semua endpoint
- [ ] Vue pages minimal bisa merender data (skeleton/basic table OK)
- [ ] Data yang sudah soft-deleted TIDAK muncul di list/dropdown
- [ ] Upload gambar → tersimpan di `storage/app/public/products/{tenant_id}/` sebagai WEBP
- [ ] Hapus produk → semua variant ikut soft delete
- [ ] Hapus modifier group → modifiers soft delete + pivot hard delete
- [ ] Hapus kategori → produk terkait pindah ke category_id = NULL

### Commands untuk Verifikasi

```bash
php artisan route:list --path=owner
php artisan storage:link
```
