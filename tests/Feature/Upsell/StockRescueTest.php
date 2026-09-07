<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\UpsellEvent;
use App\Models\User;
use App\Services\BadgeHelperService;
use App\Services\BusinessClock;
use App\Services\StockRescueService;

use function Pest\Laravel\actingAs;

/**
 * Dua angka Penyelamat Stok ([BL-105]).
 *
 * Yang diuji di sini bukan cuma jumlahnya benar, tapi juga apa yang sengaja
 * DITINGGALKAN dari tiap angka — karena di situlah kedua angka ini gampang
 * berbohong: omzet dari jenis saran lain yang ikut terhitung, saran pada
 * transaksi yang sudah dibatalkan, dan barang kedaluwarsa yang stoknya
 * sebenarnya sudah nol.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->service = app(StockRescueService::class);

    $this->from = BusinessClock::daysAgo(29);
    $this->to = BusinessClock::today();
});

it('counts only accepted pressed-stock suggestions as rescued revenue', function () {
    // Yang seharusnya masuk.
    UpsellEvent::factory()->ofType(UpsellEvent::TYPE_PRESSED_STOCK)->accepted(7500)
        ->create(['tenant_id' => $this->tenant->id]);
    UpsellEvent::factory()->ofType(UpsellEvent::TYPE_PRESSED_STOCK)->accepted(2500)
        ->create(['tenant_id' => $this->tenant->id]);

    // Tampil tapi tidak diambil: ikut menaikkan `shown`, tidak menaikkan rupiah.
    UpsellEvent::factory()->ofType(UpsellEvent::TYPE_PRESSED_STOCK)
        ->create(['tenant_id' => $this->tenant->id]);

    // Jenis lain — omzetnya nyata, tapi bukan hasil menyelamatkan barang.
    UpsellEvent::factory()->ofType(UpsellEvent::TYPE_UPSIZE)->accepted(50000)
        ->create(['tenant_id' => $this->tenant->id]);
    UpsellEvent::factory()->ofType(UpsellEvent::TYPE_ATTACH)->accepted(50000)
        ->create(['tenant_id' => $this->tenant->id]);

    expect($this->service->rescued($this->tenant, $this->from, $this->to))
        ->toMatchArray([
            'amount' => 10000.0,
            'accepted' => 2,
            'shown' => 3,
        ]);
});

it('leaves out a suggestion riding a voided transaction', function () {
    $voided = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'status' => Transaction::STATUS_VOIDED,
    ]);

    $completed = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'status' => Transaction::STATUS_COMPLETED,
    ]);

    UpsellEvent::factory()->ofType(UpsellEvent::TYPE_PRESSED_STOCK)->accepted(9000)
        ->create(['tenant_id' => $this->tenant->id, 'transaction_id' => $voided->id]);

    UpsellEvent::factory()->ofType(UpsellEvent::TYPE_PRESSED_STOCK)->accepted(4000)
        ->create(['tenant_id' => $this->tenant->id, 'transaction_id' => $completed->id]);

    // Penjualan yang dibatalkan bukan penjualan ([BL-092]) — kalau ini bocor,
    // kasir yang membatalkan lalu memasukkan ulang satu transaksi membuat satu
    // penyelamatan terhitung dua kali.
    expect($this->service->rescued($this->tenant, $this->from, $this->to))
        ->toMatchArray(['amount' => 4000.0, 'accepted' => 1, 'shown' => 1]);
});

it('never reads another shop upsell events', function () {
    $otherTenant = Tenant::factory()->create();

    UpsellEvent::factory()->ofType(UpsellEvent::TYPE_PRESSED_STOCK)->accepted(99000)
        ->create(['tenant_id' => $otherTenant->id]);

    // Dipanggil TANPA sesi: `TenantScope` mati saat tidak ada yang login, jadi
    // yang menjaga batas di sini adalah saringan eksplisit di servicenya.
    expect($this->service->rescued($this->tenant, $this->from, $this->to)['amount'])
        ->toBe(0.0);
});

it('values expired stock still on the shelf at cost price', function () {
    ProductVariant::factory()->withExpiry(BusinessClock::daysAgo(3))->create([
        'product_id' => $this->product->id,
        'cost_price' => 4000,
        'price' => 10000,
        'stock' => 6,
    ]);

    ProductVariant::factory()->withExpiry(BusinessClock::daysAgo(1))->create([
        'product_id' => $this->product->id,
        'cost_price' => 2500,
        'price' => 8000,
        'stock' => 4,
    ]);

    // Modal, bukan harga jual: barang yang tidak pernah terjual tidak pernah
    // menghasilkan margin. (6 × 4.000) + (4 × 2.500) = 34.000
    expect($this->service->spoiled($this->tenant))
        ->toMatchArray([
            'amount' => 34000.0,
            'variants' => 2,
            'units' => 10,
        ]);
});

it('leaves out variants that have not expired or have run out', function () {
    // Masih hidup — belum lewat tanggalnya.
    ProductVariant::factory()->withExpiry(BusinessClock::today())->create([
        'product_id' => $this->product->id,
        'cost_price' => 5000,
        'stock' => 10,
    ]);

    // Sudah lewat, tapi stoknya habis: tidak ada modal yang tertahan.
    ProductVariant::factory()->withExpiry(BusinessClock::daysAgo(2))->outOfStock()->create([
        'product_id' => $this->product->id,
        'cost_price' => 5000,
    ]);

    // Tidak punya tanggal kedaluwarsa sama sekali.
    ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'cost_price' => 5000,
        'stock' => 10,
        'expiry_date' => null,
    ]);

    expect($this->service->spoiled($this->tenant))
        ->toMatchArray(['amount' => 0.0, 'variants' => 0, 'units' => 0]);
});

it('hands the upsell report both numbers', function () {
    UpsellEvent::factory()->ofType(UpsellEvent::TYPE_PRESSED_STOCK)->accepted(12000)
        ->create(['tenant_id' => $this->tenant->id]);

    ProductVariant::factory()->withExpiry(BusinessClock::daysAgo(5))->create([
        'product_id' => $this->product->id,
        'cost_price' => 3000,
        'stock' => 7,
    ]);

    actingAs($this->owner)
        ->get('/owner/reports/upsell')
        ->assertInertia(fn ($page) => $page
            ->where('rescue.rescued.amount', 12000)
            ->where('rescue.rescued.accepted', 1)
            ->where('rescue.spoiled.amount', 21000)
            ->where('rescue.spoiled.variants', 1)
            ->where('rescue.spoiled.units', 7)
        );
});

it('names the money in the expired badge, not just the count', function () {
    ProductVariant::factory()->withExpiry(BusinessClock::daysAgo(4))->create([
        'product_id' => $this->product->id,
        'cost_price' => 9000,
        'stock' => 5,
    ]);

    actingAs($this->owner);

    $badges = collect(app(BadgeHelperService::class)->generate($this->tenant));
    $expired = $badges->firstWhere('type', 'expired');

    // Menghitung varian tidak pernah membuat siapa pun bertindak; menyebut
    // modal yang mati di dalamnya membuatnya bertindak.
    expect($expired['value'])->toBe(45000.0)
        ->and($expired['message'])->toContain('Rp 45.000');
});
