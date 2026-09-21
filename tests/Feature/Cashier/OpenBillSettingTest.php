<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BusinessPresetService;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

/**
 * Setelan "izinkan tagihan terbuka" ([BL-104]).
 *
 * Yang dikunci di sini tiga batas keputusan 2026-09-06: tombolnya hilang DAN
 * server menolak (dua-duanya, karena API mobile tidak melewati layar kasir),
 * tagihan lama tetap bisa dilunasi, dan tenant yang berjalan tidak berubah
 * perilakunya karena bawaannya menyala.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->active()->create();
    Subscription::factory()->seats(10)->create(['tenant_id' => $this->tenant->id]);

    $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);
    $this->cashier = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'cashier']);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'price' => 25000, 'stock' => 50,
    ]);
    $this->cash = PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'cash']);

    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'closed_at' => null,
    ]);
});

function openBillSettingPayload(ProductVariant $variant): array
{
    return [
        'items' => [[
            'variant_id' => $variant->id,
            'variant_name' => $variant->name,
            'qty' => 2,
            'unit_price' => $variant->price,
            'modifiers' => [],
        ]],
        'payments' => null,
        'is_open_bill' => true,
        'customer_name' => 'Meja 1',
    ];
}

test('tenant baru mengizinkan tagihan terbuka secara bawaan', function () {
    // Bawaan menyala supaya tidak satu pun tenant berjalan kehilangan tombol
    // "Tunda Bayar" hanya karena kolomnya lahir.
    expect(Tenant::factory()->create()->fresh()->open_bill_enabled)->toBeTrue();
});

test('kasir tetap bisa membuka tagihan saat setelannya menyala', function () {
    actingAs($this->cashier);

    post('/cashier/transactions', openBillSettingPayload($this->variant))->assertSessionHas('success');

    expect(Transaction::where('status', Transaction::STATUS_PENDING)->count())->toBe(1);
});

test('server menolak tagihan terbuka dari POS saat setelannya mati', function () {
    $this->tenant->update(['open_bill_enabled' => false]);

    actingAs($this->cashier);

    post('/cashier/transactions', openBillSettingPayload($this->variant))->assertSessionHas('error');

    // Stok juga tidak boleh sempat berkurang: penolakannya harus terjadi di
    // dalam transaksi database, bukan setelah item tercatat.
    expect(Transaction::count())->toBe(0)
        ->and($this->variant->fresh()->stock)->toBe(50);
});

test('API mobile ikut ditolak, karena ia tidak melewati layar kasir', function () {
    $this->tenant->update(['open_bill_enabled' => false]);

    Sanctum::actingAs($this->cashier, ['mobile:use']);

    postJson('/api/v1/mobile/transactions', openBillSettingPayload($this->variant))
        ->assertStatus(422);

    expect(Transaction::count())->toBe(0);
});

test('penjualan langsung bayar tidak terpengaruh setelan ini', function () {
    $this->tenant->update(['open_bill_enabled' => false]);

    actingAs($this->cashier);

    post('/cashier/transactions', [
        ...openBillSettingPayload($this->variant),
        'is_open_bill' => false,
        'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 50000]],
    ])->assertSessionHas('success');

    expect(Transaction::where('status', Transaction::STATUS_COMPLETED)->count())->toBe(1);
});

test('tagihan yang terbuka sebelum setelannya dimatikan tetap bisa dilunasi', function () {
    actingAs($this->cashier);

    post('/cashier/transactions', openBillSettingPayload($this->variant))->assertSessionHas('success');
    $bill = Transaction::where('status', Transaction::STATUS_PENDING)->sole();

    $this->tenant->update(['open_bill_enabled' => false]);

    // Keputusan 2026-09-06: mematikan jalur pelunasan akan mengubur uang yang
    // benar-benar tertagih. Yang dilarang hanya membuat yang baru.
    post("/cashier/transactions/{$bill->id}/pay", [
        'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 50000]],
    ])->assertSessionHas('success');

    expect($bill->fresh()->status)->toBe(Transaction::STATUS_COMPLETED);
});

test('layar kasir menerima saklarnya', function () {
    $this->tenant->update(['open_bill_enabled' => false]);

    actingAs($this->cashier);

    get('/cashier/pos')->assertInertia(fn ($page) => $page
        ->component('Cashier/POS')
        ->where('openBillEnabled', false)
    );
});

test('pemilik bisa mematikan tagihan terbuka dari Cara Kerja Sistem', function () {
    actingAs($this->owner);

    get('/owner/settings/operations')->assertInertia(fn ($page) => $page
        ->where('features.open_bill_enabled', true)
        ->has('featureWarnings.open_bills')
    );

    patch('/owner/settings/operations', ['open_bill_enabled' => false])
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->open_bill_enabled)->toBeFalse();
});

// --- Paket setelan ---

test('paket gerai acara mematikan tagihan terbuka, paket lain membiarkannya menyala', function () {
    $presets = app(BusinessPresetService::class);

    expect($presets->presetColumnsFor('gerai_acara')['open_bill_enabled'])->toBeFalse();

    // Kolom ini bawaannya menyala; paket yang lupa menyebutnya akan diam-diam
    // mencabut "Tunda Bayar" dari tenant yang tidak memintanya.
    foreach (array_diff($presets->styleNames(), ['gerai_acara']) as $style) {
        expect($presets->presetColumnsFor($style)['open_bill_enabled'])->toBeTrue("paket {$style}");
    }
});

test('pendaftar gerai acara mendarat tanpa tagihan terbuka dan diberi tahu', function () {
    post('/register', [
        'business_name' => 'Ayam Kriuk Acara',
        'name' => 'Pemilik',
        'email' => 'pemilik@kriukacara.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
        'selling_style' => 'gerai_acara',
    ]);

    expect(Tenant::where('name', 'Ayam Kriuk Acara')->firstOrFail()->open_bill_enabled)->toBeFalse();

    // Setelan tersembunyi wajib disebut di ringkasan "Disetel otomatis".
    expect(collect(app(BusinessPresetService::class)->hiddenSummaryFor('gerai_acara'))->firstWhere('name', 'open_bill'))
        ->toMatchArray(['value' => 'Mati']);
});
