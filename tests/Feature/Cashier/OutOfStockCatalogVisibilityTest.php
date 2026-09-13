<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Uji integrasi untuk perilaku yang diminta pemilik: produk dengan stok 0
 * TETAP muncul di katalog kasir (bukan disembunyikan) supaya kasir masih
 * bisa melihatnya, tapi checkout untuknya harus tetap ditolak.
 *
 * `POSController::catalogWithDiscounts()` sengaja tidak memfilter produk
 * berdasarkan stok (lihat app/Http/Controllers/Cashier/POSController.php) —
 * penandaan "Habis" dan penonaktifan tombol terjadi di klien
 * (resources/js/Components/ProductCard.vue::hasStock()). Uji ini memverifikasi
 * separuh sisi server dari kontrak itu: backend memang mengirim variannya, dan
 * checkout memang menolaknya — bukan cuma UI yang kebetulan menyembunyikannya.
 */
function makeOutOfStockContext(): array
{
    $tenant = Tenant::factory()->create();

    $cashier = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'cashier',
    ]);

    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 25000,
        'stock' => 0,
    ]);

    $paymentMethod = PaymentMethod::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => 'cash',
    ]);

    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    return compact('tenant', 'cashier', 'product', 'variant', 'paymentMethod');
}

test('produk dengan stok habis tetap dikirim server ke katalog kasir, bukan disembunyikan', function () {
    ['cashier' => $cashier, 'product' => $product, 'variant' => $variant] = makeOutOfStockContext();

    actingAs($cashier);

    // Kunjungan HTML biasa dulu untuk tahu versi aset saat ini — partial
    // reload di bawah ditolak 409 (Inertia::location) kalau X-Inertia-Version
    // yang dikirim tidak cocok dengan versi server, dan versi server tidak
    // pernah terbaca dari respons 409 itu sendiri (badannya kosong).
    preg_match('/data-page="([^"]+)"/', get('/cashier/pos')->getContent(), $matches);
    $version = json_decode(html_entity_decode($matches[1]), true)['version'];

    // Minta ulang prop `products` yang ditunda (Inertia::defer) lewat
    // partial reload — cara sama yang dipakai klien saat memuat katalog.
    $response = get('/cashier/pos', [
        'X-Inertia' => 'true',
        'Accept' => 'application/json',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Data' => 'products',
        'X-Inertia-Partial-Component' => 'Cashier/POS',
    ]);

    $response->assertStatus(200);

    $products = collect($response->json('props.products'));
    $matched = $products->firstWhere('id', $product->id);

    expect($matched)->not->toBeNull(implode(' ', [
        'Produk dengan stok 0 tidak lagi dikirim ke katalog kasir.',
        'Kalau ini disengaja, POS.vue perlu penanda "Habis" lain karena',
        'ia mengandalkan variant.stock yang dikirim dari sini.',
    ]));

    $variantPayload = collect($matched['variants'])->firstWhere('id', $variant->id);
    expect($variantPayload['stock'])->toBe(0);
});

test('checkout ditolak saat stok varian sudah habis', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'paymentMethod' => $paymentMethod] = makeOutOfStockContext();

    actingAs($cashier);

    post('/cashier/transactions', [
        'items' => [
            [
                'variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'qty' => 1,
                'unit_price' => $variant->price,
                'modifiers' => [],
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $paymentMethod->id,
                'amount' => $variant->price,
            ],
        ],
    ])->assertSessionHas('error');

    expect(\App\Models\Transaction::query()->where('tenant_id', $tenant->id)->exists())->toBeFalse();
    expect($variant->fresh()->stock)->toBe(0);
});
