<?php

use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;

/**
 * Setelan pajak dan pengunciannya ([BL-065] butir 2-6).
 *
 * Yang dijaga di sini adalah bentuk penguncian yang tepat, karena keduanya
 * mudah salah ke arah yang berlawanan: mengunci terlalu awal menjebak tenant
 * yang baru mencoba, dan tidak mengunci sama sekali membiarkan riwayat
 * pungutan berlubang.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->actingAs($this->owner);
});

/** Satu penjualan berpajak — inilah yang mengunci, bukan sakelarnya. */
function sellOnceWithTax(Tenant $tenant, string $mode = Tenant::TAX_MODE_EXCLUSIVE, float $rate = 11): Transaction
{
    $tenant->update([
        'tax_enabled' => true,
        'tax_mode' => $mode,
        'tax_rate' => $rate,
        'tax_label' => 'PPN',
    ]);

    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'price' => 10000, 'stock' => 50,
    ]);
    $cash = PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    test()->actingAs($cashier);

    return app(TransactionService::class)->checkout([
        'items' => [[
            'variant_id' => $variant->id,
            'variant_name' => 'Kopi',
            'qty' => 1,
            'unit_price' => 10000,
            'modifiers' => [],
        ]],
        'payments' => [['payment_method_id' => $cash->id, 'amount' => 11100]],
    ]);
}

test('halaman setelan membawa status pajak dan penguncian', function () {
    $this->get(route('owner.settings.operations.index'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('tax.tax_enabled', false)
            ->where('tax.locked', false)
            ->has('taxModes')
        );
});

test('menyalakan pajak tanpa memilih jenisnya ditolak', function () {
    $this->patch(route('owner.settings.operations.tax.update'), [
        'tax_enabled' => true,
        'tax_mode' => Tenant::TAX_MODE_EXCLUSIVE,
        'tax_rate' => 11,
        'tax_label' => '',
    ])->assertSessionHasErrors('tax_label');

    expect($this->tenant->fresh()->tax_enabled)->toBeFalse();
});

test('pajak bisa dinyalakan lengkap dengan jenis dan tarifnya', function () {
    $this->patch(route('owner.settings.operations.tax.update'), [
        'tax_enabled' => true,
        'tax_mode' => Tenant::TAX_MODE_INCLUSIVE,
        'tax_rate' => 10,
        'tax_label' => 'PB1',
    ])->assertSessionHasNoErrors();

    $tenant = $this->tenant->fresh();

    expect($tenant->tax_enabled)->toBeTrue()
        ->and($tenant->tax_mode)->toBe(Tenant::TAX_MODE_INCLUSIVE)
        ->and((float) $tenant->tax_rate)->toBe(10.0)
        ->and($tenant->tax_label)->toBe('PB1');
});

test('mode masih bebas diubah selama belum ada penjualan berpajak', function () {
    $this->tenant->update([
        'tax_enabled' => true,
        'tax_mode' => Tenant::TAX_MODE_EXCLUSIVE,
        'tax_rate' => 11,
        'tax_label' => 'PPN',
    ]);

    // Menyalakan saja tidak mengunci — tenant yang berubah pikiran sebelum
    // menjual apa pun tidak boleh terjebak.
    $this->patch(route('owner.settings.operations.tax.update'), [
        'tax_enabled' => true,
        'tax_mode' => Tenant::TAX_MODE_INCLUSIVE,
        'tax_rate' => 11,
        'tax_label' => 'PPN',
    ])->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->tax_mode)->toBe(Tenant::TAX_MODE_INCLUSIVE);
});

test('mode terkunci setelah penjualan berpajak pertama', function () {
    sellOnceWithTax($this->tenant);

    $this->actingAs($this->owner)
        ->patch(route('owner.settings.operations.tax.update'), [
            'tax_enabled' => true,
            'tax_mode' => Tenant::TAX_MODE_INCLUSIVE,
            'tax_rate' => 11,
            'tax_label' => 'PPN',
        ])->assertSessionHasErrors('tax_mode');

    expect($this->tenant->fresh()->tax_mode)->toBe(Tenant::TAX_MODE_EXCLUSIVE);
});

test('mematikan pajak terkunci setelah penjualan berpajak pertama', function () {
    sellOnceWithTax($this->tenant);

    $this->actingAs($this->owner)
        ->patch(route('owner.settings.operations.tax.update'), [
            'tax_enabled' => false,
            'tax_mode' => Tenant::TAX_MODE_EXCLUSIVE,
            'tax_rate' => 11,
            'tax_label' => 'PPN',
        ])->assertSessionHasErrors('tax_enabled');

    expect($this->tenant->fresh()->tax_enabled)->toBeTrue();
});

test('tarif dan jenis pajak tetap bisa diubah setelah terkunci', function () {
    sellOnceWithTax($this->tenant);

    // Tarif memang berubah di dunia nyata — PPN pernah naik 10% ke 11%, dan
    // Perda daerah bisa mengubah tarif PBJT. Mengunci ini berarti memaksa
    // tenant melanggar aturan yang berlaku.
    $this->actingAs($this->owner)
        ->patch(route('owner.settings.operations.tax.update'), [
            'tax_enabled' => true,
            'tax_mode' => Tenant::TAX_MODE_EXCLUSIVE,
            'tax_rate' => 12,
            'tax_label' => 'PB1',
        ])->assertSessionHasNoErrors();

    $tenant = $this->tenant->fresh();

    expect((float) $tenant->tax_rate)->toBe(12.0)
        ->and($tenant->tax_label)->toBe('PB1');
});

test('endpoint pajak tidak bisa menulis field milik endpoint lain', function () {
    $this->patch(route('owner.settings.operations.tax.update'), [
        'tax_enabled' => false,
        'tax_mode' => Tenant::TAX_MODE_EXCLUSIVE,
        'tax_rate' => 0,
        'tax_label' => null,
        'kitchen_queue_enabled' => true,
        'ai_api_key' => 'sk-kunci-selundupan',
    ])->assertSessionHasNoErrors();

    $tenant = $this->tenant->fresh();

    expect($tenant->kitchen_queue_enabled)->toBeFalse()
        ->and($tenant->ai_api_key)->toBeNull();
});

test('tarif di luar nalar ditolak', function () {
    $this->patch(route('owner.settings.operations.tax.update'), [
        'tax_enabled' => true,
        'tax_mode' => Tenant::TAX_MODE_EXCLUSIVE,
        'tax_rate' => 150,
        'tax_label' => 'PPN',
    ])->assertSessionHasErrors('tax_rate');
});
