<?php

use App\Models\Product;
use App\Models\ProductStockBatch;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockService;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * Adjustment memilih batchnya sendiri ([BL-111]).
 *
 * Sebelum ini koreksi turun selalu mengambil batch basi lebih dulu dan koreksi
 * naik selalu masuk ke batch terakhir, tanpa tampil di layar. Dua croissant
 * segar yang jatuh tercatat mengurangi batch basi.
 *
 * Satu varian, tiga batch: 4 basi, 5 segera basi, 6 masih lama.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Croissant']);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Plain',
        'sku' => 'ADJ-CRS',
        'stock' => 0,
        'expiry_date' => null,
    ]);

    $stock = app(StockService::class);
    $stock->restock($this->variant, 4, 'Kiriman lama', now()->subDays(2)->toDateString());
    $stock->restock($this->variant, 5, 'Kiriman minggu ini', now()->addDays(3)->toDateString());
    $stock->restock($this->variant, 6, 'Kiriman terbaru', now()->addDays(10)->toDateString());

    [$this->expired, $this->soon, $this->later] = ProductStockBatch::where('product_variant_id', $this->variant->id)
        ->orderBy('expiry_date')
        ->get()
        ->all();
});

function adjustBatchPost(object $case, array $payload)
{
    return actingAs($case->owner)->post("/owner/stock/{$case->variant->id}/adjust", $payload);
}

test('a reduction takes only from the batch the owner picked', function () {
    adjustBatchPost($this, ['qty' => -2, 'notes' => 'Dua jatuh', 'batch_id' => $this->soon->id])
        ->assertSessionHas('success');

    expect($this->soon->fresh()->qty_remaining)->toBe(3)
        ->and($this->expired->fresh()->qty_remaining)->toBe(4)
        ->and($this->variant->fresh()->stock)->toBe(13);
});

test('a reduction refuses more than the picked batch holds', function () {
    adjustBatchPost($this, ['qty' => -6, 'notes' => 'Terlalu banyak', 'batch_id' => $this->soon->id])
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'hanya tersisa 5'));

    expect($this->variant->fresh()->stock)->toBe(15)
        ->and($this->soon->fresh()->qty_remaining)->toBe(5);
});

test('an automatic reduction still discards expired stock first', function () {
    adjustBatchPost($this, ['qty' => -4, 'notes' => 'Dibuang: kedaluwarsa'])
        ->assertSessionHas('success');

    expect($this->expired->fresh()->qty_remaining)->toBe(0)
        ->and($this->soon->fresh()->qty_remaining)->toBe(5);
});

test('an increase can go into a picked batch', function () {
    adjustBatchPost($this, ['qty' => 2, 'notes' => 'Hitung ulang', 'batch_id' => $this->soon->id])
        ->assertSessionHas('success');

    expect($this->soon->fresh()->qty_remaining)->toBe(7)
        ->and($this->later->fresh()->qty_remaining)->toBe(6);
});

test('an increase can open a new dated batch', function () {
    $date = now()->addDays(20)->toDateString();

    adjustBatchPost($this, ['qty' => 3, 'notes' => 'Retur', 'new_batch' => true, 'expiry_date' => $date])
        ->assertSessionHas('success');

    $new = ProductStockBatch::where('product_variant_id', $this->variant->id)->latest('id')->first();

    expect($new->qty_remaining)->toBe(3)
        ->and($new->expiry_date->toDateString())->toBe($date)
        ->and($new->source)->toBe(ProductStockBatch::SOURCE_ADJUSTMENT);
});

test('a new batch on a dated variant needs a date unless declared undated', function () {
    adjustBatchPost($this, ['qty' => 3, 'notes' => 'Retur', 'new_batch' => true])
        ->assertSessionHasErrors('expiry_date');

    adjustBatchPost($this, ['qty' => 3, 'notes' => 'Retur', 'new_batch' => true, 'no_expiry' => true])
        ->assertSessionHas('success');

    $undated = ProductStockBatch::where('product_variant_id', $this->variant->id)
        ->whereNull('expiry_date')
        ->sum('qty_remaining');

    expect((int) $undated)->toBe(3);
});

test('a batch that belongs to another variant is refused', function () {
    $other = ProductVariant::factory()->create(['product_id' => $this->variant->product_id, 'stock' => 5]);
    $foreign = ProductStockBatch::where('product_variant_id', $other->id)->firstOrFail();

    adjustBatchPost($this, ['qty' => -1, 'notes' => 'Salah pilih', 'batch_id' => $foreign->id])
        ->assertSessionHasErrors('batch_id');

    expect($foreign->fresh()->qty_remaining)->toBe(5);
});

test('a new batch cannot be used to reduce stock', function () {
    adjustBatchPost($this, [
        'qty' => -1,
        'notes' => 'Salah',
        'new_batch' => true,
        'expiry_date' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors('qty');
});

test('stock rows carry batch ids and the expired units the discard shortcut uses', function () {
    actingAs($this->owner)
        ->get('/owner/stock?q=Croissant')
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('stock', fn (Assert $reload) => $reload
            ->where('variants.data.0.expired_units', 4)
            ->where('variants.data.0.expired_since', now()->subDays(2)->toDateString())
            ->where('variants.data.0.batches.0.id', $this->expired->id)
            ->where('variants.data.0.batches.1.id', $this->soon->id)
        ));
});
