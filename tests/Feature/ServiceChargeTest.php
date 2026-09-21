<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ProfitService;
use App\Services\TransactionService;

use function Pest\Laravel\actingAs;

/**
 * Biaya layanan dari ujung ke ujung ([BL-097] Tahap 2 & 3).
 *
 * `TaxCalculatorTest` sudah menjaga aritmetikanya. Yang diuji di sini adalah
 * hal yang tidak bisa dilihat dari kalkulator: bahwa jalur penjualan benar-benar
 * MEMANGGILNYA, bahwa konteksnya dibekukan, dan bahwa mengedit penjualan lama
 * memakai tarif hari itu — bukan tarif hari ini.
 *
 * @return array{tenant: Tenant, owner: User, cashier: User, variant: ProductVariant, cash: PaymentMethod}
 */
function makeServiceChargeContext(array $tenantAttributes = []): array
{
    $tenant = Tenant::factory()->create($tenantAttributes);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'price' => 10000, 'stock' => 100,
    ]);
    $cash = PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    return compact('tenant', 'owner', 'cashier', 'variant', 'cash');
}

function checkoutOne(User $cashier, ProductVariant $variant, PaymentMethod $cash, float $paid): Transaction
{
    actingAs($cashier);

    return app(TransactionService::class)->checkout([
        'items' => [[
            'variant_id' => $variant->id,
            'variant_name' => 'Variant',
            'qty' => 1,
            'unit_price' => 10000,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $cash->id,
            'amount' => $paid,
        ]],
    ]);
}

test('checkout memungut biaya layanan dan membekukan konteksnya', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'cash' => $cash] = makeServiceChargeContext([
        'service_charge_enabled' => true,
        'service_charge_rate' => 5,
        'service_charge_label' => 'Biaya Layanan',
    ]);

    $transaction = checkoutOne($cashier, $variant, $cash, 10500);

    expect((float) $transaction->subtotal_amount)->toBe(10000.0)
        ->and((float) $transaction->service_charge_amount)->toBe(500.0)
        ->and((float) $transaction->total_amount)->toBe(10500.0)
        // Konteksnya ikut BEKU, bukan cuma nominalnya: tanpa ini struk cetak
        // ulang tahun depan menghitung dengan tarif tahun depan.
        ->and((float) $transaction->service_charge_rate)->toBe(5.0)
        ->and($transaction->service_charge_label)->toBe('Biaya Layanan');
});

test('pajak dipungut atas subtotal ditambah biaya layanan pada penjualan nyata', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'cash' => $cash] = makeServiceChargeContext([
        'service_charge_enabled' => true,
        'service_charge_rate' => 5,
        'service_charge_label' => 'Biaya Layanan',
        'tax_enabled' => true,
        'tax_mode' => Tenant::TAX_MODE_EXCLUSIVE,
        'tax_rate' => 11,
        'tax_label' => 'PB1',
    ]);

    $transaction = checkoutOne($cashier, $variant, $cash, 11655);

    // Pajak atas 10500, bukan atas 10000. Kalau urutannya terbalik angkanya
    // 1100 dan tenant menyetor kurang dari yang terutang.
    expect((float) $transaction->tax_amount)->toBe(1155.0)
        ->and((float) $transaction->total_amount)->toBe(11655.0)
        ->and((float) $transaction->subtotal_amount
            + (float) $transaction->service_charge_amount
            + (float) $transaction->tax_amount)
        ->toBe((float) $transaction->total_amount);
});

test('tenant tanpa biaya layanan tidak terpengaruh sama sekali', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'cash' => $cash] = makeServiceChargeContext();

    $transaction = checkoutOne($cashier, $variant, $cash, 10000);

    expect((float) $transaction->total_amount)->toBe(10000.0)
        ->and((float) $transaction->service_charge_amount)->toBe(0.0)
        ->and($transaction->service_charge_rate)->toBeNull();
});

test('mengedit penjualan lama memakai tarif BEKU, bukan tarif hari ini', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'cashier' => $cashier, 'variant' => $variant, 'cash' => $cash]
        = makeServiceChargeContext([
            'service_charge_enabled' => true,
            'service_charge_rate' => 5,
            'service_charge_label' => 'Biaya Layanan',
        ]);

    $transaction = checkoutOne($cashier, $variant, $cash, 10500);

    // Pemilik toko menaikkan tarifnya SETELAH penjualan itu terjadi — sesuatu
    // yang boleh ia lakukan kapan pun, karena biaya layanan tidak dikunci.
    $tenant->update(['service_charge_rate' => 20]);

    actingAs($owner)
        ->put(route('cashier.transactions.update', $transaction->id), [
            'items' => [['variant_id' => $variant->id, 'qty' => 2]],
            'payments' => [['payment_method_id' => $cash->id, 'amount' => 21000]],
            'reason' => 'koreksi qty',
        ])
        ->assertRedirect();

    $edited = $transaction->fresh();

    // 20000 x 5% = 1000, memakai tarif hari penjualan. Kalau tarif hari ini
    // yang dipakai angkanya 4000, dan pelanggan ditagih atas kesepakatan yang
    // tidak pernah ia setujui.
    expect((float) $edited->service_charge_amount)->toBe(1000.0)
        ->and((float) $edited->service_charge_rate)->toBe(5.0)
        ->and((float) $edited->total_amount)->toBe(21000.0);
});

test('biaya layanan tidak dihitung sebagai pendapatan toko', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'cash' => $cash] = makeServiceChargeContext([
        'service_charge_enabled' => true,
        'service_charge_rate' => 5,
        'service_charge_label' => 'Biaya Layanan',
    ]);

    checkoutOne($cashier, $variant, $cash, 10500);

    $profit = app(ProfitService::class)->overallProfit(now()->subDay(), now()->addDay());

    // `revenue` tetap berarti yang dibayar pelanggan; `net_revenue` tidak
    // memuat biaya layanan ([BL-097] jawaban 3), dan margin diukur terhadapnya.
    expect($profit['revenue'])->toBe(10500.0)
        ->and($profit['net_revenue'])->toBe(10000.0)
        ->and($profit['service_charge'])->toBe(500.0);
});

test('pemilik dapat menyalakan biaya layanan lewat setelannya', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeServiceChargeContext();

    actingAs($owner)
        ->patch(route('owner.settings.operations.service-charge.update'), [
            'service_charge_enabled' => true,
            'service_charge_rate' => 5,
            'service_charge_label' => 'Service Charge',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $tenant->refresh();

    expect($tenant->service_charge_enabled)->toBeTrue()
        ->and((float) $tenant->service_charge_rate)->toBe(5.0)
        ->and($tenant->service_charge_label)->toBe('Service Charge');
});

test('menyalakan biaya layanan tanpa memilih namanya ditolak', function () {
    ['owner' => $owner] = makeServiceChargeContext();

    actingAs($owner)
        ->patch(route('owner.settings.operations.service-charge.update'), [
            'service_charge_enabled' => true,
            'service_charge_rate' => 5,
            'service_charge_label' => null,
        ])
        // Kata yang tercetak di struk tidak pernah ditebak — pola yang sama
        // dengan `tax_label` dan pelajaran `[BL-079]`.
        ->assertSessionHasErrors('service_charge_label');
});

test('biaya layanan TIDAK terkunci oleh penjualan yang sudah memungutnya', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'cashier' => $cashier, 'variant' => $variant, 'cash' => $cash]
        = makeServiceChargeContext([
            'service_charge_enabled' => true,
            'service_charge_rate' => 5,
            'service_charge_label' => 'Biaya Layanan',
        ]);

    checkoutOne($cashier, $variant, $cash, 10500);

    // Inilah bedanya dari pajak, dan ia disengaja: pemilik toko boleh
    // mematikannya kapan pun tanpa meminta operator platform membukakan apa
    // pun. Riwayat yang berlubang di sini tidak melanggar aturan mana pun.
    actingAs($owner)
        ->patch(route('owner.settings.operations.service-charge.update'), [
            'service_charge_enabled' => false,
            'service_charge_rate' => 0,
            'service_charge_label' => 'Biaya Layanan',
        ])
        ->assertSessionHasNoErrors();

    expect($tenant->fresh()->service_charge_enabled)->toBeFalse();
});

test('halaman setelan mengirim konteks biaya layanan ke layarnya', function () {
    ['owner' => $owner] = makeServiceChargeContext([
        'service_charge_enabled' => true,
        'service_charge_rate' => 7.5,
        'service_charge_label' => 'Biaya Pelayanan',
    ]);

    actingAs($owner)
        ->get(route('owner.settings.operations.index'))
        ->assertInertia(fn ($page) => $page
            ->where('serviceCharge.service_charge_enabled', true)
            ->where('serviceCharge.service_charge_rate', 7.5)
            ->where('serviceCharge.service_charge_label', 'Biaya Pelayanan')
        );
});

test('layar kasir mengirim konteks biaya layanan untuk dipakai offline', function () {
    ['cashier' => $cashier] = makeServiceChargeContext([
        'service_charge_enabled' => true,
        'service_charge_rate' => 5,
        'service_charge_label' => 'Biaya Layanan',
    ]);

    // Layar kasir menuntut laci yang terbuka sebelum ia mau tampil.
    CashDrawer::factory()->create([
        'tenant_id' => $cashier->tenant_id,
        'user_id' => $cashier->id,
        'opened_at' => now()->subHour(),
    ]);

    // Tanpa konteks ini keranjang offline menghitung total tanpa biaya
    // layanan, dan tiap penjualan mendarat `needs_review` saat sinkronisasi.
    actingAs($cashier)
        ->get(route('cashier.pos'))
        ->assertInertia(fn ($page) => $page
            ->where('serviceCharge.enabled', true)
            ->where('serviceCharge.rate', 5)
            ->where('serviceCharge.label', 'Biaya Layanan')
        );
});

test('laporan harian memisahkan biaya layanan dari omzet toko', function () {
    ['owner' => $owner, 'cashier' => $cashier, 'variant' => $variant, 'cash' => $cash]
        = makeServiceChargeContext([
            'service_charge_enabled' => true,
            'service_charge_rate' => 5,
            'service_charge_label' => 'Biaya Layanan',
        ]);

    checkoutOne($cashier, $variant, $cash, 10500);

    actingAs($owner)
        ->get(route('owner.reports.daily'))
        ->assertInertia(fn ($page) => $page
            ->where('summary.total_revenue', 10500)
            ->where('summary.net_revenue', 10000)
            ->where('summary.service_charge_collected', 500)
            ->where('serviceCharge.active', true)
            ->where('serviceCharge.label', 'Biaya Layanan')
        );
});
