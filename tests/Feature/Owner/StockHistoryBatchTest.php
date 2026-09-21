<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StockService;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * Riwayat stok menyebut batch yang disentuh tiap mutasi ([BL-111]).
 *
 * `stock_movement_batches` sudah mencatatnya sejak batch ada, tapi tidak ada
 * satu layar pun yang membacanya. Tanpa kolom ini, "-3 penjualan" di riwayat
 * tidak bisa menjawab apakah yang terjual croissant 18 Sep atau yang basi.
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
        'stock' => 0,
        'expiry_date' => null,
    ]);

    $this->soon = now()->addDays(3)->toDateString();
    $this->later = now()->addDays(10)->toDateString();

    $stock = app(StockService::class);
    $stock->restock($this->variant, 5, 'Kiriman minggu ini', $this->soon);
    $stock->restock($this->variant, 6, 'Kiriman terbaru', $this->later);

    $sale = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
    ]);

    // 7 unit: 5 dari batch terdekat habis, 2 dari batch berikutnya.
    $stock->deduct($this->variant->fresh(), 7, $sale->id);
});

/**
 * Baris riwayat per jenis mutasi, dari prop `movements` yang ditunda.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function stockHistoryBatchRows(object $case, string $url): array
{
    $rows = [];

    actingAs($case->owner)
        ->get($url)
        ->assertInertia(function (Assert $page) use (&$rows) {
            $page->loadDeferredProps(function (Assert $reload) use (&$rows) {
                $rows = collect($reload->toArray()['props']['movements']['data'])
                    ->groupBy('type')
                    ->map(fn ($group) => $group->values()->all())
                    ->all();
            });
        });

    return $rows;
}

test('the variant history names the batch each movement touched', function () {
    $rows = stockHistoryBatchRows($this, "/owner/stock/{$this->variant->id}/history");

    expect($rows['sale'][0]['batches'])->toBe([
        ['expiry_date' => $this->soon, 'qty' => -5],
        ['expiry_date' => $this->later, 'qty' => -2],
    ]);

    $restocked = collect($rows['restock'])->pluck('batches')->flatten(1)->sortBy('expiry_date')->values()->all();

    expect($restocked)->toBe([
        ['expiry_date' => $this->soon, 'qty' => 5],
        ['expiry_date' => $this->later, 'qty' => 6],
    ]);
});

test('the all-movements page carries the same batch detail', function () {
    $rows = stockHistoryBatchRows($this, '/owner/stock/movements');

    expect($rows['sale'][0]['batches'])->toBe([
        ['expiry_date' => $this->soon, 'qty' => -5],
        ['expiry_date' => $this->later, 'qty' => -2],
    ])
        ->and($rows['sale'][0]['variant']['name'])->toBe('Plain')
        ->and($rows['sale'][0]['variant']['product']['name'])->toBe('Croissant');
});
