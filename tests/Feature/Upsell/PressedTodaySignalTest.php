<?php

use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockRescueService;
use App\Services\Upsell\Strategies\PressedStockStrategy;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * Sinyal pagi ([BL-105] butir 3).
 *
 * Isi sebenarnya kartu ini bukan daftarnya — lencana dashboard sudah punya
 * daftar — melainkan kolom `armed`: apakah barang yang tertekan itu punya
 * potongan yang akan membantunya keluar, atau ia akan disarankan kasir pada
 * harga katalog dan hampir pasti tetap tinggal.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Roti',
        'is_active' => true,
    ]);

    $this->signal = app(StockRescueService::class);
});

function pressedVariant(int $productId, array $attributes = []): ProductVariant
{
    return ProductVariant::factory()->create([
        'product_id' => $productId,
        'stock' => 5,
        'cost_price' => 4000,
        'price' => 10000,
        ...$attributes,
    ]);
}

it('counts what is under pressure today and what it cost to buy', function () {
    pressedVariant($this->product->id, ['name' => 'Sobek', 'expiry_date' => now()->addDays(2)->toDateString(), 'stock' => 5, 'cost_price' => 4000]);
    pressedVariant($this->product->id, ['name' => 'Tawar', 'expiry_date' => null, 'stock' => 3, 'cost_price' => 6000]);

    $signal = $this->signal->pressedToday($this->tenant);

    // Yang kedua tidak punya tanggal kedaluwarsa sama sekali, tapi juga belum
    // pernah terjual — ia masuk lewat jalur dead stock.
    expect($signal['count'])->toBe(2)
        ->and($signal['value'])->toBe(38000.0);
});

it('marks an item armed only when a discount rule is live for it today', function () {
    $armed = pressedVariant($this->product->id, ['name' => 'Sobek', 'expiry_date' => now()->addDay()->toDateString()]);
    pressedVariant($this->product->id, ['name' => 'Tawar', 'expiry_date' => now()->addDays(3)->toDateString()]);

    DiscountRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_variant_id' => $armed->id,
        'trigger' => DiscountRule::TRIGGER_NEAR_EXPIRY,
        'percent' => 10,
        'is_active' => true,
        'starts_on' => null,
        'ends_on' => null,
    ]);

    $signal = $this->signal->pressedToday($this->tenant);

    expect($signal['armed'])->toBe(1)
        ->and($signal['unarmed'])->toBe(1);

    $rows = collect($signal['items'])->keyBy('label');
    expect($rows['Roti - Sobek']['armed'])->toBeTrue()
        ->and($rows['Roti - Tawar']['armed'])->toBeFalse();
});

it('does not call an item armed when its rule is switched off', function () {
    $variant = pressedVariant($this->product->id, ['expiry_date' => now()->addDay()->toDateString()]);

    DiscountRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_variant_id' => $variant->id,
        'is_active' => false,
    ]);

    expect($this->signal->pressedToday($this->tenant)['armed'])->toBe(0);
});

it('does not call an item armed when its rule has not started yet', function () {
    $variant = pressedVariant($this->product->id, ['expiry_date' => now()->addDay()->toDateString()]);

    DiscountRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_variant_id' => $variant->id,
        'is_active' => true,
        'starts_on' => now()->addWeek()->toDateString(),
    ]);

    expect($this->signal->pressedToday($this->tenant)['armed'])->toBe(0);
});

it('leaves out goods that are already spoiled, out of stock, or off the menu', function () {
    // Sudah kedaluwarsa: bukan barang tertekan, tapi barang yang tidak boleh
    // dijual sama sekali — batas keamanan pangan di SellableVariantQuery.
    pressedVariant($this->product->id, ['expiry_date' => now()->subDay()->toDateString()]);

    pressedVariant($this->product->id, ['stock' => 0, 'expiry_date' => now()->addDay()->toDateString()]);

    $inactive = Product::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);
    pressedVariant($inactive->id, ['expiry_date' => now()->addDay()->toDateString()]);

    expect($this->signal->pressedToday($this->tenant)['count'])->toBe(0);
});

it('puts the most urgent first, in the order the cashier will see', function () {
    pressedVariant($this->product->id, ['name' => 'Nanti', 'expiry_date' => now()->addDays(6)->toDateString()]);
    pressedVariant($this->product->id, ['name' => 'Besok', 'expiry_date' => now()->addDay()->toDateString()]);
    pressedVariant($this->product->id, ['name' => 'Diam', 'expiry_date' => null]);

    $labels = collect($this->signal->pressedToday($this->tenant)['items'])->pluck('label')->all();

    // Kedaluwarsa mengalahkan tak-laku, dan yang paling dekat menang — persis
    // skor yang dipakai strip kasir.
    expect($labels)->toBe(['Roti - Besok', 'Roti - Nanti', 'Roti - Diam']);
});

it('names only a few but counts them all', function () {
    foreach (range(1, 9) as $n) {
        pressedVariant($this->product->id, ['name' => "V{$n}", 'expiry_date' => now()->addDays(2)->toDateString()]);
    }

    $signal = $this->signal->pressedToday($this->tenant, limit: 6);

    expect($signal['count'])->toBe(9)
        ->and($signal['items'])->toHaveCount(6)
        ->and($signal['unarmed'])->toBe(9);
});

it('lists exactly what the cashier engine considers, never a second opinion', function () {
    pressedVariant($this->product->id, ['name' => 'A', 'expiry_date' => now()->addDay()->toDateString()]);
    pressedVariant($this->product->id, ['name' => 'B', 'expiry_date' => now()->addDays(4)->toDateString()]);
    pressedVariant($this->product->id, ['name' => 'C', 'expiry_date' => null]);

    $fromEngine = app(PressedStockStrategy::class)
        ->pressedVariants($this->tenant)
        ->pluck('id')
        ->sort()
        ->values()
        ->all();

    $fromSignal = collect($this->signal->pressedToday($this->tenant, limit: 99)['items'])
        ->pluck('variant_id')
        ->sort()
        ->values()
        ->all();

    // Dua daftar yang berselisih membuat owner memasang potongan untuk barang
    // yang tidak pernah disarankan — dan berhenti mempercayai keduanya.
    expect($fromSignal)->toBe($fromEngine);
});

it('hands the dashboard its morning card', function () {
    $variant = pressedVariant($this->product->id, [
        'name' => 'Sobek',
        'expiry_date' => now()->addDay()->toDateString(),
        'stock' => 4,
        'cost_price' => 7500,
    ]);

    DiscountRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_variant_id' => $variant->id,
        'is_active' => true,
        'starts_on' => null,
        'ends_on' => null,
    ]);

    actingAs($this->owner)
        ->get('/owner/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            // Ditunda bersama lencana ([BL-037]): keduanya menyisir stok, jadi
            // keduanya tiba di rombongan yang sama, bukan bergiliran.
            ->missing('pressedToday')
            ->loadDeferredProps('badges', fn (Assert $reload) => $reload
                ->where('pressedToday.count', 1)
                ->where('pressedToday.value', 30000)
                ->where('pressedToday.armed', 1)
                ->where('pressedToday.unarmed', 0)
                ->where('pressedToday.items.0.label', 'Roti - Sobek')
                ->where('pressedToday.items.0.armed', true)
            )
        );
});

it('says nothing at all when nothing is under pressure', function () {
    expect($this->signal->pressedToday($this->tenant))
        ->toMatchArray(['count' => 0, 'value' => 0.0, 'armed' => 0, 'unarmed' => 0, 'items' => []]);
});
