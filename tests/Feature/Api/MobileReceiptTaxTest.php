<?php

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TransactionService;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

/**
 * Struk aplikasi mobile membawa pembagian pajaknya ([BL-065]).
 *
 * Endpoint ini menyerialkan, tidak menghitung — tapi tanpa ketiga angkanya
 * aplikasi mobile hanya punya satu pilihan: menjumlahkan baris item sebagai
 * subtotal. Di mode inclusive itu SALAH, karena `unit_price` sudah mengandung
 * pajak dan jumlah baris adalah totalnya.
 */
function mobileTaxedSale(string $mode, float $rate = 11): array
{
    $tenant = Tenant::factory()->active()->create([
        'tax_enabled' => true,
        'tax_mode' => $mode,
        'tax_rate' => $rate,
        'tax_label' => 'PPN',
    ]);
    Subscription::factory()->seats(10)->create(['tenant_id' => $tenant->id]);

    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'price' => 10000, 'stock' => 50,
    ]);
    $cash = PaymentMethod::factory()->create(['tenant_id' => $tenant->id, 'type' => 'cash']);

    actingAs($cashier);

    $transaction = app(TransactionService::class)->checkout([
        'items' => [[
            'variant_id' => $variant->id,
            'variant_name' => 'Kopi',
            'qty' => 2,
            'unit_price' => 10000,
            'modifiers' => [],
        ]],
        'payments' => [['payment_method_id' => $cash->id, 'amount' => 25000]],
    ]);

    return ['cashier' => $cashier, 'transaction' => $transaction];
}

test('struk mobile membawa subtotal dan pajak di mode exclusive', function () {
    ['cashier' => $cashier, 'transaction' => $transaction] = mobileTaxedSale(Tenant::TAX_MODE_EXCLUSIVE);

    Sanctum::actingAs($cashier);

    getJson("/api/v1/mobile/transactions/{$transaction->id}/receipt")
        ->assertOk()
        ->assertJsonPath('data.transaction.subtotal_amount', '20000.00')
        ->assertJsonPath('data.transaction.tax_amount', '2200.00')
        ->assertJsonPath('data.transaction.total_amount', '22200.00')
        ->assertJsonPath('data.transaction.tax_mode', Tenant::TAX_MODE_EXCLUSIVE)
        ->assertJsonPath('data.transaction.tax_label', 'PPN');
});

test('struk mobile mode inclusive tidak bisa direkonstruksi dari baris item', function () {
    ['cashier' => $cashier, 'transaction' => $transaction] = mobileTaxedSale(Tenant::TAX_MODE_INCLUSIVE);

    Sanctum::actingAs($cashier);

    $response = getJson("/api/v1/mobile/transactions/{$transaction->id}/receipt")->assertOk();

    // Jumlah baris item = 20.000, yang di mode ini adalah TOTALNYA. Subtotal
    // sebenarnya 18.018 — dan hanya bisa diketahui dari kolom ini.
    $itemsSum = collect($response->json('data.items'))->sum(fn ($i) => (float) $i['subtotal']);

    expect($itemsSum)->toBe(20000.0)
        ->and((float) $response->json('data.transaction.total_amount'))->toBe(20000.0)
        ->and((float) $response->json('data.transaction.subtotal_amount'))->toBe(18018.0)
        ->and((float) $response->json('data.transaction.tax_amount'))->toBe(1982.0);
});

test('struk mobile tenant tanpa pajak tidak membawa konteks', function () {
    $tenant = Tenant::factory()->active()->create();
    Subscription::factory()->seats(10)->create(['tenant_id' => $tenant->id]);

    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'price' => 10000, 'stock' => 50,
    ]);
    $cash = PaymentMethod::factory()->create(['tenant_id' => $tenant->id, 'type' => 'cash']);

    actingAs($cashier);
    $transaction = app(TransactionService::class)->checkout([
        'items' => [[
            'variant_id' => $variant->id,
            'variant_name' => 'Kopi',
            'qty' => 1,
            'unit_price' => 10000,
            'modifiers' => [],
        ]],
        'payments' => [['payment_method_id' => $cash->id, 'amount' => 10000]],
    ]);

    Sanctum::actingAs($cashier);

    getJson("/api/v1/mobile/transactions/{$transaction->id}/receipt")
        ->assertOk()
        ->assertJsonPath('data.transaction.subtotal_amount', '10000.00')
        ->assertJsonPath('data.transaction.tax_amount', '0.00')
        ->assertJsonPath('data.transaction.tax_mode', null);
});
