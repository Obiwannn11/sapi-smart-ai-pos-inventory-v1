<?php

use App\Models\DiscountRule;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/**
 * Struk aplikasi mobile membawa potongannya ([BL-103] butir 2a).
 *
 * Endpoint ini menyerialkan, tidak menghitung. Tanpa harga normal dan
 * potongan per unit, aplikasi mobile hanya bisa mencetak harga yang sudah
 * dipotong — persis struk yang tidak transparan yang diperbaiki di web.
 */

/**
 * @return array{cashier: User, transaction: Transaction}
 */
function mobileDiscountedReceiptSale(bool $withDiscount): array
{
    $tenant = Tenant::factory()->active()->create(['min_margin_percent' => 10]);
    Subscription::factory()->seats(10)->create(['tenant_id' => $tenant->id]);

    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $tea = ProductVariant::factory()->create([
        'product_id' => $product->id, 'price' => 10000, 'cost_price' => 5000, 'stock' => 50, 'expiry_date' => null,
    ]);
    $coffee = ProductVariant::factory()->create([
        'product_id' => $product->id, 'price' => 15000, 'cost_price' => 5000, 'stock' => 50, 'expiry_date' => null,
    ]);
    $cash = PaymentMethod::factory()->create(['tenant_id' => $tenant->id, 'type' => 'cash']);

    if ($withDiscount) {
        DiscountRule::factory()->create([
            'tenant_id' => $tenant->id,
            'product_variant_id' => $tea->id,
            'percent' => 30,
        ]);
    }

    actingAs($cashier);

    $transaction = app(TransactionService::class)->checkout([
        'items' => [
            ['variant_id' => $tea->id, 'variant_name' => 'Teh Manis', 'qty' => 2, 'unit_price' => 10000, 'modifiers' => []],
            ['variant_id' => $coffee->id, 'variant_name' => 'Kopi', 'qty' => 1, 'unit_price' => 15000, 'modifiers' => []],
        ],
        'payments' => [['payment_method_id' => $cash->id, 'amount' => 40000]],
    ]);

    return ['cashier' => $cashier, 'transaction' => $transaction];
}

test('struk mobile membawa harga normal, potongan per unit, dan jumlah hemat', function () {
    ['cashier' => $cashier, 'transaction' => $transaction] = mobileDiscountedReceiptSale(withDiscount: true);

    Sanctum::actingAs($cashier, ['mobile:use']);

    $response = getJson("/api/v1/mobile/transactions/{$transaction->id}/receipt")->assertOk();

    $tea = collect($response->json('data.items'))->firstWhere('qty', 2);
    $coffee = collect($response->json('data.items'))->firstWhere('qty', 1);

    // `price` tetap harga yang DIBAYAR per unit — pembaca lama tidak berubah.
    expect($tea)->toMatchArray([
        'price' => '7000.00',
        'original_price' => '10000.00',
        'discount_amount' => '3000.00',
        'subtotal' => '14000.00',
    ])->and($coffee)->toMatchArray([
        'price' => '15000.00',
        'original_price' => '15000.00',
        'discount_amount' => '0.00',
    ]);

    $response->assertJsonPath('data.transaction.discount_total', '6000.00')
        ->assertJsonPath('data.transaction.total_amount', '29000.00');
});

test('struk mobile tanpa potongan melaporkan jumlah hemat nol', function () {
    ['cashier' => $cashier, 'transaction' => $transaction] = mobileDiscountedReceiptSale(withDiscount: false);

    Sanctum::actingAs($cashier, ['mobile:use']);

    getJson("/api/v1/mobile/transactions/{$transaction->id}/receipt")
        ->assertOk()
        ->assertJsonPath('data.transaction.discount_total', '0.00')
        ->assertJsonPath('data.items.0.discount_amount', '0.00');
});
