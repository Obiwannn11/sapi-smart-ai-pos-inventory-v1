<?php

use App\Mcp\Servers\SapiBusinessServer;
use App\Mcp\Tools\GetMenuTool;
use App\Mcp\Tools\GetProfitTool;
use App\Mcp\Tools\GetSalesSummaryTool;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Kopi Senja']);
    $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);

    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'name' => 'Kopi Susu',
        'price' => 25000,
        'cost_price' => 15000,
        'stock' => 100,
    ]);
});

function seedMcpSale(Tenant $tenant, User $user, ProductVariant $variant, int $qty, int $subtotal): void
{
    $transaction = Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => $subtotal,
        'code' => 'TRX-'.fake()->unique()->numerify('########'),
    ]);

    $transaction->items()->create([
        'product_variant_id' => $variant->id,
        'variant_name' => $variant->name,
        'qty' => $qty,
        'unit_price' => $subtotal / $qty,
        'subtotal' => $subtotal,
    ]);
}

test('get-profit returns aggregated profit for the owner', function () {
    seedMcpSale($this->tenant, $this->owner, $this->variant, qty: 3, subtotal: 75000);

    $response = SapiBusinessServer::actingAs($this->owner)->tool(GetProfitTool::class, []);

    $response->assertOk()
        ->assertHasNoErrors()
        ->assertSee('Kopi Susu')
        ->assertSee('45000'); // cogs = 3 × 15000
});

test('get-sales-summary returns aggregated sales for the owner', function () {
    seedMcpSale($this->tenant, $this->owner, $this->variant, qty: 3, subtotal: 75000);

    $response = SapiBusinessServer::actingAs($this->owner)->tool(GetSalesSummaryTool::class, []);

    $response->assertOk()
        ->assertSee('75000')
        ->assertSee('Kopi Susu');
});

test('get-menu returns the active product catalog', function () {
    $response = SapiBusinessServer::actingAs($this->owner)->tool(GetMenuTool::class, []);

    $response->assertOk()->assertSee('Kopi Susu');
});

test('business tools reject non-owner users', function () {
    $cashier = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'cashier']);

    $response = SapiBusinessServer::actingAs($cashier)->tool(GetProfitTool::class, []);

    $response->assertHasErrors(['Hanya owner yang dapat mengakses data bisnis MCP.']);
});

test('period validation rejects a to date before from', function () {
    $response = SapiBusinessServer::actingAs($this->owner)->tool(GetProfitTool::class, [
        'from' => '2026-01-10',
        'to' => '2026-01-01',
    ]);

    $response->assertHasErrors();
});

test('business data is scoped to the authenticated owner tenant', function () {
    seedMcpSale($this->tenant, $this->owner, $this->variant, qty: 1, subtotal: 40000);

    $otherTenant = Tenant::factory()->create();
    $otherOwner = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'owner']);
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id, 'is_active' => true]);
    $otherVariant = ProductVariant::factory()->create([
        'product_id' => $otherProduct->id,
        'name' => 'RahasiaTenantB',
        'price' => 90000,
        'cost_price' => 10000,
        'stock' => 50,
    ]);
    seedMcpSale($otherTenant, $otherOwner, $otherVariant, qty: 1, subtotal: 90000);

    $response = SapiBusinessServer::actingAs($this->owner)->tool(GetSalesSummaryTool::class, []);

    $response->assertOk()
        ->assertSee('40000')
        ->assertDontSee('RahasiaTenantB')
        ->assertDontSee('90000');
});
