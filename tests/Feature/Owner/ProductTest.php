<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

test('owner can view products', function () {
    Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->get('/owner/products')
        ->assertStatus(200);
});

test('owner can view create product form', function () {
    $this->actingAs($this->owner)
        ->get('/owner/products/create')
        ->assertStatus(200);
});

test('owner can create product with variants', function () {
    $category = Category::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->post('/owner/products', [
            'name' => 'Nasi Goreng',
            'category_id' => $category->id,
            'is_active' => true,
            'variants' => [
                [
                    'name' => 'Regular',
                    'sku' => 'NG-REG',
                    'price' => 25000,
                    'cost_price' => 15000,
                    'stock' => 50,
                ],
            ],
        ])
        ->assertRedirect(route('owner.products.index'));

    $this->assertDatabaseHas('products', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Nasi Goreng',
    ]);

    $this->assertDatabaseHas('product_variants', [
        'name' => 'Regular',
        'price' => 25000,
    ]);
});

test('owner can view edit product form', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductVariant::factory()->create(['product_id' => $product->id]);

    $this->actingAs($this->owner)
        ->get("/owner/products/{$product->id}/edit")
        ->assertStatus(200);
});

test('owner can delete product (soft delete)', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->delete("/owner/products/{$product->id}")
        ->assertRedirect();

    $this->assertSoftDeleted('products', ['id' => $product->id]);
});

// --- Gambar produk ---

test('an uploaded image is stored as a square webp in two fixed sizes', function () {
    Storage::fake('local');

    $this->actingAs($this->owner)
        ->post('/owner/products', [
            'name' => 'Kopi Susu',
            'image' => UploadedFile::fake()->image('foto.jpg', 1600, 900),
            'variants' => [
                ['name' => 'Regular', 'price' => 18000, 'cost_price' => 9000, 'stock' => 10],
            ],
        ])
        ->assertRedirect(route('owner.products.index'));

    $product = Product::where('name', 'Kopi Susu')->sole();
    $disk = Storage::disk('local');
    $thumbPath = str_replace('.webp', '_thumb.webp', $product->image);

    expect($product->image)->toStartWith("products/{$this->tenant->id}/");
    $disk->assertExists($product->image);
    $disk->assertExists($thumbPath);

    $main = ImageManager::gd()->read($disk->get($product->image));
    $thumb = ImageManager::gd()->read($disk->get($thumbPath));

    expect([$main->width(), $main->height()])->toBe([800, 800])
        ->and([$thumb->width(), $thumb->height()])->toBe([200, 200]);
});

test('replacing an image removes both renditions of the old one', function () {
    Storage::fake('local');

    $this->actingAs($this->owner)
        ->post('/owner/products', [
            'name' => 'Teh Tarik',
            'image' => UploadedFile::fake()->image('lama.jpg', 400, 400),
            'variants' => [
                ['name' => 'Regular', 'price' => 12000, 'cost_price' => 6000, 'stock' => 5],
            ],
        ]);

    $product = Product::where('name', 'Teh Tarik')->sole();
    $oldPath = $product->image;

    $this->actingAs($this->owner)
        ->put("/owner/products/{$product->id}", [
            'name' => 'Teh Tarik',
            'image' => UploadedFile::fake()->image('baru.jpg', 400, 400),
        ])
        ->assertRedirect(route('owner.products.index'));

    $disk = Storage::disk('local');

    $disk->assertMissing($oldPath);
    $disk->assertMissing(str_replace('.webp', '_thumb.webp', $oldPath));
    $disk->assertExists($product->fresh()->image);
});

test('deleting a product clears its image files', function () {
    Storage::fake('local');

    $this->actingAs($this->owner)
        ->post('/owner/products', [
            'name' => 'Es Jeruk',
            'image' => UploadedFile::fake()->image('foto.jpg', 400, 400),
            'variants' => [
                ['name' => 'Regular', 'price' => 10000, 'cost_price' => 4000, 'stock' => 5],
            ],
        ]);

    $product = Product::where('name', 'Es Jeruk')->sole();
    $path = $product->image;

    $this->actingAs($this->owner)->delete("/owner/products/{$product->id}");

    Storage::disk('local')->assertMissing($path);
    Storage::disk('local')->assertMissing(str_replace('.webp', '_thumb.webp', $path));
});

test('an image never lands on the publicly served disk', function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->actingAs($this->owner)
        ->post('/owner/products', [
            'name' => 'Roti Bakar',
            'image' => UploadedFile::fake()->image('foto.jpg', 400, 400),
            'variants' => [
                ['name' => 'Regular', 'price' => 15000, 'cost_price' => 7000, 'stock' => 5],
            ],
        ]);

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});
