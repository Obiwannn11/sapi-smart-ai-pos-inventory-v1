<?php

use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->tenantA = Tenant::factory()->create();
    $this->ownerA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'owner',
    ]);

    $this->tenantB = Tenant::factory()->create();
    $this->ownerB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'role' => 'owner',
    ]);

    $path = "products/{$this->tenantA->id}/rahasia.webp";
    Storage::disk('local')->put($path, 'isi-gambar-utuh');
    Storage::disk('local')->put("products/{$this->tenantA->id}/rahasia_thumb.webp", 'isi-gambar-kisi');

    $this->productA = Product::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Kopi Susu',
        'image' => $path,
    ]);
});

function mediaUrl(Product $product, string $size = 'full'): string
{
    return "/media/products/{$product->id}/{$size}";
}

test('owner can fetch an image belonging to their own tenant', function () {
    $response = $this->actingAs($this->ownerA)->get(mediaUrl($this->productA));

    $response->assertOk()->assertHeader('Content-Type', 'image/webp');

    expect($response->streamedContent())->toBe('isi-gambar-utuh');
});

test('thumb size serves the grid rendition, not the full one', function () {
    $response = $this->actingAs($this->ownerA)->get(mediaUrl($this->productA, 'thumb'));

    expect($response->streamedContent())->toBe('isi-gambar-kisi');
});

test('a user from another tenant cannot peek at the image', function () {
    $this->actingAs($this->ownerB)
        ->get(mediaUrl($this->productA))
        ->assertNotFound();
});

test('a guest is sent to the login page instead of the file', function () {
    $this->get(mediaUrl($this->productA))
        ->assertRedirect(route('login'));
});

test('an unknown rendition size is not routable', function () {
    $this->actingAs($this->ownerA)
        ->get(mediaUrl($this->productA, 'original'))
        ->assertNotFound();
});

test('a product without an image has no media to serve', function () {
    $bare = Product::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'image' => null,
    ]);

    $this->actingAs($this->ownerA)
        ->get(mediaUrl($bare))
        ->assertNotFound();
});

test('a missing file on disk is a 404, not a server error', function () {
    Storage::disk('local')->delete($this->productA->image);

    $this->actingAs($this->ownerA)
        ->get(mediaUrl($this->productA))
        ->assertNotFound();
});

test('serialised products expose the guarded route, never the storage path', function () {
    $this->actingAs($this->ownerA);

    $payload = $this->productA->fresh()->toArray();

    expect($payload['image_url'])->toContain("/media/products/{$this->productA->id}/full")
        ->and($payload['image_thumb_url'])->toContain("/media/products/{$this->productA->id}/thumb")
        ->and($payload['image_url'])->not->toContain('/storage/');
});

test('replacing the image changes the cache-busting version', function () {
    $this->actingAs($this->ownerA);

    $before = $this->productA->image_url;

    $this->productA->update(['image' => "products/{$this->tenantA->id}/lain.webp"]);

    expect($this->productA->fresh()->image_url)->not->toBe($before);
});
