<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ProfitService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->actingAs($this->owner);

    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'price' => 25000,
        'cost_price' => 15000,
        'stock' => 100,
    ]);

    $this->service = app(ProfitService::class);
});

/**
 * Helper: buat transaksi completed + 1 item terhadap varian yang diberikan.
 */
function makeCompletedSale(ProductVariant $variant, int $qty, int $subtotal, ?string $code = null): Transaction
{
    $transaction = Transaction::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => $subtotal,
        'code' => $code ?? 'TRX-'.fake()->unique()->numerify('########'),
    ]);

    $transaction->items()->create([
        'product_variant_id' => $variant->id,
        'variant_name' => $variant->name,
        'qty' => $qty,
        'unit_price' => $subtotal / $qty,
        'subtotal' => $subtotal,
    ]);

    return $transaction;
}

test('overallProfit derives revenue, cogs, gross profit and margin', function () {
    // qty 2, subtotal 50000, cost 15000/unit → cogs 30000, gross 20000, margin 40%.
    makeCompletedSale($this->variant, qty: 2, subtotal: 50000);

    $result = $this->service->overallProfit(now()->subDay(), now()->addDay());

    expect($result['revenue'])->toBe(50000.0)
        ->and($result['cogs'])->toBe(30000.0)
        ->and($result['gross_profit'])->toBe(20000.0)
        ->and($result['margin_pct'])->toBe(40.0);
});

test('overallProfit ignores non-completed transactions', function () {
    makeCompletedSale($this->variant, qty: 2, subtotal: 50000);

    $pending = Transaction::factory()->pending()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 99000,
    ]);
    $pending->items()->create([
        'product_variant_id' => $this->variant->id,
        'variant_name' => $this->variant->name,
        'qty' => 3,
        'unit_price' => 33000,
        'subtotal' => 99000,
    ]);

    $result = $this->service->overallProfit(now()->subDay(), now()->addDay());

    expect($result['revenue'])->toBe(50000.0)
        ->and($result['cogs'])->toBe(30000.0);
});

test('overallProfit still counts soft-deleted variants', function () {
    makeCompletedSale($this->variant, qty: 2, subtotal: 50000);

    $this->variant->delete(); // soft delete

    $result = $this->service->overallProfit(now()->subDay(), now()->addDay());

    // Join query builder tidak kena scope SoftDeletes → cogs tetap terhitung.
    expect($result['cogs'])->toBe(30000.0)
        ->and($result['gross_profit'])->toBe(20000.0);
});

test('profitByProduct returns per-variant margins including trashed variant', function () {
    makeCompletedSale($this->variant, qty: 2, subtotal: 50000);
    $this->variant->delete();

    $rows = $this->service->profitByProduct(now()->subDay(), now()->addDay());

    expect($rows)->toHaveCount(1);

    $row = $rows->first();
    expect($row['variant_name'])->toBe($this->variant->name)
        ->and($row['qty'])->toBe(2)
        ->and($row['revenue'])->toBe(50000.0)
        ->and($row['cogs'])->toBe(30000.0)
        ->and($row['margin'])->toBe(20000.0)
        ->and($row['margin_pct'])->toBe(40.0);
});

test('projection computes integer basis days and average daily profit', function () {
    // 2 hari terpisah, masing-masing gross profit 20000 → total 40000 over 3 days basis.
    makeCompletedSale($this->variant, qty: 2, subtotal: 50000, code: 'TRX-A');

    $from = now()->subDays(2);
    $to = now();

    $projection = $this->service->projection($from, $to);

    expect($projection['basis_days'])->toBeInt()
        ->and($projection['basis_days'])->toBe(3)
        ->and($projection['avg_daily_profit'])->toBe(round(20000 / 3, 2))
        ->and($projection['projected_next_period'])->toBe(round(20000 / 3 * 3, 2));
});

test('overallProfit returns zeros when there are no sales', function () {
    $result = $this->service->overallProfit(now()->subDay(), now()->addDay());

    expect($result['revenue'])->toBe(0.0)
        ->and($result['cogs'])->toBe(0.0)
        ->and($result['gross_profit'])->toBe(0.0)
        ->and($result['margin_pct'])->toBe(0.0);
});

// --- Margin di bawah pajak ([BL-065]) ---

/**
 * Helper: penjualan berpajak, satu item, dengan konteks pajak dibekukan.
 *
 * `$base` diperlakukan seperti di kasir — subtotal di mode exclusive, total di
 * mode inclusive — dan barisnya memakai harga katalog yang dilihat pelanggan.
 */
function makeTaxedSale(ProductVariant $variant, int $qty, int $base, string $mode): Transaction
{
    $transaction = Transaction::factory()->taxed($base, 11, $mode)->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'code' => 'TRX-'.fake()->unique()->numerify('########'),
    ]);

    $transaction->items()->create([
        'product_variant_id' => $variant->id,
        'variant_name' => $variant->name,
        'qty' => $qty,
        'unit_price' => $base / $qty,
        'subtotal' => $base,
    ]);

    return $transaction;
}

test('overallProfit measures margin against the shop revenue, not the tax on top of it', function () {
    // Exclusive 11% atas subtotal 50.000: pelanggan membayar 55.500, tapi
    // 5.500-nya tidak pernah jadi milik toko. COGS 30.000 → margin tetap 40%.
    makeTaxedSale($this->variant, qty: 2, base: 50000, mode: Tenant::TAX_MODE_EXCLUSIVE);

    $result = $this->service->overallProfit(now()->subDay(), now()->addDay());

    expect($result['revenue'])->toBe(55500.0)
        ->and($result['net_revenue'])->toBe(50000.0)
        ->and($result['tax'])->toBe(5500.0)
        ->and($result['gross_profit'])->toBe(20000.0)
        // Memakai yang dibayar pelanggan akan melaporkan 45,95%.
        ->and($result['margin_pct'])->toBe(40.0);
});

test('overallProfit sees the margin drop that inclusive tax hides', function () {
    // Inclusive 11%: pelanggan tetap membayar 50.000 seperti sebelum pajak
    // menyala, jadi `total_amount` tidak bergerak sama sekali — dan margin
    // akan terbaca 40% seperti dulu kalau dihitung dari sana. Padahal 4.955
    // dari angka itu kini milik negara.
    makeTaxedSale($this->variant, qty: 2, base: 50000, mode: Tenant::TAX_MODE_INCLUSIVE);

    $result = $this->service->overallProfit(now()->subDay(), now()->addDay());

    expect($result['revenue'])->toBe(50000.0)
        ->and($result['net_revenue'])->toBe(45045.0)
        ->and($result['tax'])->toBe(4955.0)
        ->and($result['gross_profit'])->toBe(15045.0)
        ->and($result['margin_pct'])->toBe(33.40);
});

test('profitByProduct agrees with the overall summary under exclusive tax', function () {
    makeTaxedSale($this->variant, qty: 2, base: 50000, mode: Tenant::TAX_MODE_EXCLUSIVE);

    $overall = $this->service->overallProfit(now()->subDay(), now()->addDay());
    $row = $this->service->profitByProduct(now()->subDay(), now()->addDay())->first();

    // Dua angka margin untuk periode yang sama dikirim berdampingan ke model;
    // keduanya harus sepakat.
    expect($row['net_revenue'])->toBe(50000.0)
        ->and($row['margin_pct'])->toBe($overall['margin_pct']);
});

test('profitByProduct carves inclusive tax out of the catalog price', function () {
    makeTaxedSale($this->variant, qty: 2, base: 50000, mode: Tenant::TAX_MODE_INCLUSIVE);

    $overall = $this->service->overallProfit(now()->subDay(), now()->addDay());
    $row = $this->service->profitByProduct(now()->subDay(), now()->addDay())->first();

    // Harga katalog apa adanya tetap dibawa; yang jadi dasar margin yang bersih.
    expect($row['revenue'])->toBe(50000.0)
        ->and($row['net_revenue'])->toBe(45045.05)
        ->and($row['margin_pct'])->toBe($overall['margin_pct']);
});

test('profitByProduct leaves untaxed sales exactly as recorded', function () {
    makeCompletedSale($this->variant, qty: 2, subtotal: 50000);

    $row = $this->service->profitByProduct(now()->subDay(), now()->addDay())->first();

    expect($row['revenue'])->toBe(50000.0)
        ->and($row['net_revenue'])->toBe(50000.0)
        ->and($row['margin_pct'])->toBe(40.0);
});
