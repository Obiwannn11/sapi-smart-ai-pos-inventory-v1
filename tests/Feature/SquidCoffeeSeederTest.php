<?php

use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionCatalogSeeder;
use Database\Seeders\SquidCoffeeSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    Carbon::setTestNow('2026-09-14 21:00:00');
    $this->seed([PermissionCatalogSeeder::class, SquidCoffeeSeeder::class]);
    $this->tenant = Tenant::where('slug', 'squid-coffee')->firstOrFail();
});

afterEach(fn () => Carbon::setTestNow());

it('creates the owner and cashier accounts registered on 15 July 2026', function () {
    expect($this->tenant->created_at->toDateString())->toBe('2026-07-15')
        ->and($this->tenant->status)->toBe(Tenant::STATUS_ACTIVE);

    $owner = User::where('email', 'owner@squid.id')->firstOrFail();
    $cashier = User::where('email', 'kasir@squid.id')->firstOrFail();

    expect($owner->role)->toBe('owner')
        ->and($cashier->role)->toBe('cashier')
        ->and($owner->tenant_id)->toBe($this->tenant->id)
        ->and($cashier->created_at->toDateString())->toBe('2026-07-15');
});

it('books about 50 million in the first month and 70 million in the second', function () {
    $revenueBetween = fn (string $from, string $to) => (float) DB::table('transactions')
        ->where('tenant_id', $this->tenant->id)
        ->where('status', 'completed')
        ->whereBetween('occurred_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
        ->sum('total_amount');

    expect($revenueBetween('2026-07-15', '2026-08-14'))->toBeBetween(50_000_000, 50_400_000)
        ->and($revenueBetween('2026-08-15', '2026-09-14'))->toBeBetween(70_000_000, 70_400_000)
        ->and(DB::table('transactions')->where('tenant_id', $this->tenant->id)->where('occurred_at', '>', now())->exists())->toBeFalse();
});

it('keeps every variant stock equal to its movement history and never negative', function () {
    $variants = DB::table('product_variants')
        ->join('products', 'products.id', '=', 'product_variants.product_id')
        ->where('products.tenant_id', $this->tenant->id)
        ->pluck('product_variants.stock', 'product_variants.id');

    expect($variants)->not->toBeEmpty();

    foreach ($variants as $variantId => $stock) {
        $movementSum = (int) DB::table('stock_movements')->where('product_variant_id', $variantId)->sum('qty');

        expect($stock)->toBeGreaterThan(0)->and($movementSum)->toBe((int) $stock);
    }
});

it('adds owner upsell rules with events only after the rules were written', function () {
    expect(DB::table('upsell_rules')->where('tenant_id', $this->tenant->id)->count())->toBe(8);

    $events = DB::table('upsell_events')->where('tenant_id', $this->tenant->id);

    expect((clone $events)->count())->toBeGreaterThan(0)
        ->and((clone $events)->where('created_at', '<', '2026-08-01 09:00:00')->exists())->toBeFalse()
        ->and((clone $events)->where('status', 'accepted')->where('extra_amount', '<=', 0)->exists())->toBeFalse();
});

it('refuses to seed the same tenant twice', function () {
    $this->seed(SquidCoffeeSeeder::class);

    expect(Tenant::where('slug', 'squid-coffee')->count())->toBe(1);
});
