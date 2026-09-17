<?php

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

test('owner can view daily report', function () {
    $this->actingAs($this->owner)
        ->get('/owner/reports/daily')
        ->assertStatus(200);
});

test('owner can view daily report with date filter', function () {
    $this->actingAs($this->owner)
        ->get('/owner/reports/daily?date=2026-03-06')
        ->assertStatus(200);
});

test('owner can view transaction history', function () {
    $this->actingAs($this->owner)
        ->get('/owner/transactions')
        ->assertStatus(200);
});

test('owner can view cash drawer history', function () {
    $this->actingAs($this->owner)
        ->get('/owner/cash-drawers')
        ->assertStatus(200);
});

test('owner can view monthly report', function () {
    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Owner/Reports/Monthly')
            ->where('month', now()->format('Y-m'))
        );
});

test('monthly report sums only the requested calendar month', function () {
    Transaction::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 50000,
        'occurred_at' => '2026-05-10 09:00:00',
    ]);

    // Tepat di batas bulan — hari terakhir harus ikut, bulan berikutnya jangan.
    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 30000,
        'occurred_at' => '2026-05-31 23:30:00',
    ]);

    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 999000,
        'occurred_at' => '2026-06-01 00:10:00',
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-05')
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_revenue', 130000)
            ->where('summary.total_transactions', 3)
            ->where('summary.active_days', 2)
        );
});

test('monthly report counts sales on the day they happened, not the day they synced', function () {
    // Penjualan offline 30 April, baru masuk server 2 Mei: harus tetap April.
    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 75000,
        'channel' => Transaction::CHANNEL_OFFLINE,
        'occurred_at' => '2026-04-30 20:00:00',
        'created_at' => '2026-05-02 08:00:00',
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-04')
        ->assertInertia(fn (Assert $page) => $page->where('summary.total_revenue', 75000));

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-05')
        ->assertInertia(fn (Assert $page) => $page->where('summary.total_revenue', 0));
});

test('monthly report separates voided transactions from revenue', function () {
    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 40000,
        'occurred_at' => '2026-05-03 12:00:00',
    ]);

    Transaction::factory()->voided()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 90000,
        'occurred_at' => '2026-05-03 13:00:00',
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-05')
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_revenue', 40000)
            ->where('summary.total_transactions', 1)
            ->where('summary.voided_count', 1)
        );
});

test('monthly report fills every day of the month, including days without sales', function () {
    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-02')
        ->assertInertia(fn (Assert $page) => $page
            ->has('dailySeries', 28)
            ->where('dailySeries.0.date', '2026-02-01')
            ->where('dailySeries.0.count', 0)
            ->where('summary.best_day', null)
        );
});

test('monthly report compares against the previous month', function () {
    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 100000,
        'occurred_at' => '2026-04-10 10:00:00',
    ]);

    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 150000,
        'occurred_at' => '2026-05-10 10:00:00',
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-05')
        ->assertInertia(fn (Assert $page) => $page
            ->where('comparison.month', '2026-04')
            ->where('comparison.total_revenue', 100000)
            ->where('comparison.revenue_delta_pct', 50)
        );
});

test('monthly report reports no comparison when the previous month is empty', function () {
    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 100000,
        'occurred_at' => '2026-05-10 10:00:00',
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-05')
        ->assertInertia(fn (Assert $page) => $page->where('comparison.revenue_delta_pct', null));
});

test('monthly report falls back to the current month when the parameter is unreadable', function () {
    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=bulan-lalu')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page->where('month', now()->format('Y-m')));
});

test('monthly report ignores another tenant transactions', function () {
    $otherTenant = Tenant::factory()->create();
    $otherOwner = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'role' => 'owner',
    ]);

    Transaction::factory()->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherOwner->id,
        'total_amount' => 500000,
        'occurred_at' => '2026-05-10 10:00:00',
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-05')
        ->assertInertia(fn (Assert $page) => $page->where('summary.total_revenue', 0));
});

test('owner can download the monthly report as csv', function () {
    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 65000,
        'occurred_at' => '2026-05-12 10:00:00',
    ]);

    $response = $this->actingAs($this->owner)
        ->get('/owner/reports/monthly/export?month=2026-05');

    $response->assertStatus(200)
        ->assertHeader('content-disposition', 'attachment; filename=laporan-bulanan-2026-05.csv');

    $csv = $response->streamedContent();

    expect($csv)->toContain('RINGKASAN')
        ->toContain('RINCIAN HARIAN')
        ->toContain('METODE PEMBAYARAN')
        ->toContain('PRODUK TERLARIS')
        ->toContain('2026-05-12')
        ->toContain('65000');
});

// --- Pajak terpungut di laporan ([BL-065] butir (e)) ---

/**
 * Tenant yang memungut pajak, dengan setelan yang sudah menyala.
 */
function taxCollectingTenant(Tenant $tenant, string $mode = Tenant::TAX_MODE_EXCLUSIVE, string $label = 'PPN'): void
{
    $tenant->update([
        'tax_enabled' => true,
        'tax_mode' => $mode,
        'tax_rate' => 11,
        'tax_label' => $label,
    ]);
}

test('daily report splits revenue from the tax collected on top of it', function () {
    taxCollectingTenant($this->tenant);

    Transaction::factory()->taxed(100000)->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'occurred_at' => '2026-05-12 10:00:00',
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/reports/daily?date=2026-05-12')
        ->assertInertia(fn (Assert $page) => $page
            // Yang dibayar pelanggan tetap jadi arti `total_revenue`.
            ->where('summary.total_revenue', 111000)
            ->where('summary.net_revenue', 100000)
            ->where('summary.tax_collected', 11000)
            ->where('tax.active', true)
            ->where('tax.label', 'PPN')
        );
});

test('daily report shows inclusive tax carved out of an unchanged total', function () {
    taxCollectingTenant($this->tenant, Tenant::TAX_MODE_INCLUSIVE);

    // Mode inclusive: pelanggan tetap membayar 111.000, dan omzet tokolah
    // yang turun. Inilah yang tidak terlihat sebelum butir (e) ada.
    Transaction::factory()->taxed(111000, 11, Tenant::TAX_MODE_INCLUSIVE)->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'occurred_at' => '2026-05-12 10:00:00',
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/reports/daily?date=2026-05-12')
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_revenue', 111000)
            ->where('summary.net_revenue', 100000)
            ->where('summary.tax_collected', 11000)
        );
});

test('reports say nothing about tax for a tenant that does not collect it', function () {
    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 65000,
        'occurred_at' => '2026-05-12 10:00:00',
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/reports/daily?date=2026-05-12')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tax.active', false)
            // Tanpa pajak, omzet bersih memang sama dengan yang dibayar.
            ->where('summary.net_revenue', 65000)
            ->where('summary.tax_collected', 0)
        );

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-05')
        ->assertInertia(fn (Assert $page) => $page->where('tax.active', false));
});

test('daily report still shows the tax section on a day without sales', function () {
    taxCollectingTenant($this->tenant);

    $this->actingAs($this->owner)
        ->get('/owner/reports/daily?date=2026-05-12')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tax.active', true)
            ->where('tax.label', 'PPN')
            ->where('summary.tax_collected', 0)
        );
});

test('monthly report sums the tax collected across the month', function () {
    taxCollectingTenant($this->tenant);

    Transaction::factory()->taxed(100000)->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'occurred_at' => '2026-05-10 09:00:00',
    ]);

    Transaction::factory()->taxed(200000)->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'occurred_at' => '2026-05-20 09:00:00',
    ]);

    // Void tidak pernah dipungut, jadi tidak pernah disetorkan.
    Transaction::factory()->taxed(900000)->voided()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'occurred_at' => '2026-05-21 09:00:00',
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-05')
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.total_revenue', 333000)
            ->where('summary.net_revenue', 300000)
            ->where('summary.tax_collected', 33000)
            ->where('tax.active', true)
        );
});

test('monthly report labels tax with the word frozen on the sale, not the current setting', function () {
    taxCollectingTenant($this->tenant, label: 'PB1');

    Transaction::factory()->taxed(100000, 11, Tenant::TAX_MODE_EXCLUSIVE, 'PPN')->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'occurred_at' => '2026-05-10 09:00:00',
    ]);

    // Tenant sudah mengganti labelnya jadi PB1, tapi penjualan Mei dipungut
    // sebagai PPN. Laporan Mei harus tetap menyebut PPN.
    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-05')
        ->assertInertia(fn (Assert $page) => $page->where('tax.label', 'PPN'));
});

test('monthly csv carries the tax collected, and omits the column when there is none', function () {
    taxCollectingTenant($this->tenant);

    Transaction::factory()->taxed(100000)->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'occurred_at' => '2026-05-12 10:00:00',
    ]);

    $csv = $this->actingAs($this->owner)
        ->get('/owner/reports/monthly/export?month=2026-05')
        ->streamedContent();

    expect($csv)->toContain('Omzet sebelum pajak')
        ->toContain('PPN terpungut')
        ->toContain('11000');

    // Bulan tanpa penjualan berpajak tetap menampilkan kolomnya selama
    // sakelarnya menyala — yang tidak boleh adalah tenant yang tidak memungut.
    $this->tenant->update(['tax_enabled' => false]);
    // `actingAs` memakai instance user yang sama untuk seluruh test, dan relasi
    // tenant-nya sudah termuat dari permintaan di atas — tanpa ini permintaan
    // kedua masih membaca setelan yang lama.
    $this->owner->refresh();

    $quiet = $this->actingAs($this->owner)
        ->get('/owner/reports/monthly/export?month=2026-04')
        ->streamedContent();

    expect($quiet)->not->toContain('Omzet sebelum pajak');
});

// --- Produk terlaris: PRODUK, bukan penanda variannya ---

/**
 * Penjualan satu varian pada tanggal tertentu.
 *
 * `$discountPerUnit` mengikuti bentuk kolomnya: potongan PER UNIT, dan
 * `$subtotal` adalah yang benar-benar tertagih — jadi harga normalnya
 * `$subtotal + $discountPerUnit * $qty`, persis cara laporan menghitungnya
 * ([BL-116]).
 */
function sellVariant(
    App\Models\ProductVariant $variant,
    int $qty,
    int $subtotal,
    string $occurredAt,
    int $discountPerUnit = 0,
    ?string $reason = null,
    bool $belowFloor = false,
): void {
    $transaction = Transaction::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => $subtotal,
        'occurred_at' => $occurredAt,
    ]);

    $unitPrice = $subtotal / $qty;

    $transaction->items()->create([
        'product_variant_id' => $variant->id,
        'variant_name' => $variant->name,
        'qty' => $qty,
        'unit_price' => $unitPrice,
        'subtotal' => $subtotal,
        ...$discountPerUnit > 0 ? [
            'original_unit_price' => $unitPrice + $discountPerUnit,
            'discount_amount' => $discountPerUnit,
            'discount_reason' => $reason,
            'margin_floor_at_sale' => $unitPrice + ($belowFloor ? 1000 : -1000),
            'below_floor_approved_by' => $belowFloor ? test()->owner->id : null,
        ] : [],
    ]);
}

/**
 * Dua produk yang sama-sama punya varian "Hot" — persis bentuk katalog yang
 * membuat pengelompokan lama salah.
 */
function twoProductsSharingAVariantName(): array
{
    $latte = App\Models\Product::factory()->create([
        'tenant_id' => test()->tenant->id,
        'name' => 'Cafe Latte',
    ]);
    $aren = App\Models\Product::factory()->create([
        'tenant_id' => test()->tenant->id,
        'name' => 'Kopi Susu Gula Aren',
    ]);

    return [
        App\Models\ProductVariant::factory()->create(['product_id' => $latte->id, 'name' => 'Hot']),
        App\Models\ProductVariant::factory()->create(['product_id' => $latte->id, 'name' => 'Iced']),
        App\Models\ProductVariant::factory()->create(['product_id' => $aren->id, 'name' => 'Hot']),
    ];
}

test('monthly top products group by product, not by the variant label they share', function () {
    [$latteHot, $latteIced, $arenHot] = twoProductsSharingAVariantName();

    sellVariant($latteHot, qty: 4, subtotal: 80000, occurredAt: '2026-05-04 09:00:00');
    sellVariant($latteIced, qty: 3, subtotal: 66000, occurredAt: '2026-05-05 09:00:00');
    sellVariant($arenHot, qty: 6, subtotal: 132000, occurredAt: '2026-05-06 09:00:00');

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-05')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Owner/Reports/Monthly')
            ->missing('topProducts')
            ->loadDeferredProps(['rekap'], fn (Assert $reload) => $reload
                // Dua produk, bukan dua baris "Hot" yang menyatu jadi satu.
                ->has('topProducts', 2)
                // Kopi Susu Gula Aren (6) di atas Cafe Latte (4 + 3 = 7)?
                // Tidak — Cafe Latte menang justru karena kedua variannya
                // dijumlahkan, dan itulah yang membedakan "per produk" dari
                // "per varian".
                ->where('topProducts.0.product_name', 'Cafe Latte')
                ->where('topProducts.0.total_qty', 7)
                ->where('topProducts.0.total_revenue', 146000)
                ->has('topProducts.0.variants', 2)
                ->where('topProducts.0.variants.0.variant_name', 'Hot')
                ->where('topProducts.0.variants.0.total_qty', 4)
                ->where('topProducts.1.product_name', 'Kopi Susu Gula Aren')
                ->where('topProducts.1.total_qty', 6)
            )
        );
});

test('monthly csv lists each variant under the name of its own product', function () {
    [$latteHot, , $arenHot] = twoProductsSharingAVariantName();

    sellVariant($latteHot, qty: 4, subtotal: 80000, occurredAt: '2026-05-04 09:00:00');
    sellVariant($arenHot, qty: 6, subtotal: 132000, occurredAt: '2026-05-06 09:00:00');

    $csv = $this->actingAs($this->owner)
        ->get('/owner/reports/monthly/export?month=2026-05')
        ->streamedContent();

    expect($csv)->toContain('Peringkat,Produk,Varian,"Qty Terjual",Omzet')
        ->toContain('1,"Kopi Susu Gula Aren",Hot,6')
        ->toContain('2,"Cafe Latte",Hot,4');
});

test('daily top products also group by product', function () {
    [$latteHot, , $arenHot] = twoProductsSharingAVariantName();

    sellVariant($latteHot, qty: 2, subtotal: 40000, occurredAt: '2026-05-04 09:00:00');
    sellVariant($arenHot, qty: 5, subtotal: 110000, occurredAt: '2026-05-04 11:00:00');

    $this->actingAs($this->owner)
        ->get('/owner/reports/daily?date=2026-05-04')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Owner/Reports/Daily')
            ->loadDeferredProps(['rekap'], fn (Assert $reload) => $reload
                ->has('topProducts', 2)
                ->where('topProducts.0.product_name', 'Kopi Susu Gula Aren')
                ->where('topProducts.0.total_qty', 5)
            )
        );
});

test('the variant breakdown merges rows that recorded the same variant under different names', function () {
    // Riwayat panjang menyimpan `variant_name` dengan dua bentuk: sebagian
    // baris lama memuat label lengkap ("Espresso - Single"), sebagian hanya
    // nama variannya ("Single"). Keduanya varian yang sama, dan rinciannya
    // tidak boleh memecahnya jadi dua baris di bawah satu produk.
    $espresso = App\Models\Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Espresso',
    ]);
    $single = App\Models\ProductVariant::factory()->create([
        'product_id' => $espresso->id,
        'name' => 'Single',
    ]);

    sellVariant($single, qty: 5, subtotal: 90000, occurredAt: '2026-05-04 09:00:00');

    // Baris kedua menunjuk varian yang sama, tapi namanya tercatat lengkap.
    $legacy = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => 36000,
        'occurred_at' => '2026-05-05 09:00:00',
    ]);
    $legacy->items()->create([
        'product_variant_id' => $single->id,
        'variant_name' => 'Espresso - Single',
        'qty' => 2,
        'unit_price' => 18000,
        'subtotal' => 36000,
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-05')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(['rekap'], fn (Assert $reload) => $reload
                ->where('topProducts.0.product_name', 'Espresso')
                ->where('topProducts.0.total_qty', 7)
                // Satu baris rincian, bukan dua — dan namanya nama katalognya.
                ->has('topProducts.0.variants', 1)
                ->where('topProducts.0.variants.0.variant_name', 'Single')
                ->where('topProducts.0.variants.0.variant_id', $single->id)
                ->where('topProducts.0.variants.0.total_qty', 7)
                ->where('topProducts.0.variants.0.total_revenue', 126000)
            )
        );
});

// --- Potongan harga sebulan ([BL-116]) ---

test('monthly report sums the discounts given across the month', function () {
    [$hot] = twoProductsSharingAVariantName();

    // Dua penjualan berpotongan di bulan yang diminta, satu tanpa potongan,
    // dan satu berpotongan di bulan lain yang tidak boleh ikut terhitung.
    sellVariant($hot, qty: 2, subtotal: 16000, occurredAt: '2026-06-03 09:00:00', discountPerUnit: 2000, reason: 'Dekat kedaluwarsa');
    sellVariant($hot, qty: 1, subtotal: 9000, occurredAt: '2026-06-11 09:00:00', discountPerUnit: 1000, reason: 'Stok mati');
    sellVariant($hot, qty: 3, subtotal: 60000, occurredAt: '2026-06-20 09:00:00');
    sellVariant($hot, qty: 1, subtotal: 5000, occurredAt: '2026-07-02 09:00:00', discountPerUnit: 5000, reason: 'Bulan lain');

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-06')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Owner/Reports/Monthly')
            // Ditunda bersama rekap lain — angkanya baru ada di permintaan
            // lanjutan.
            ->missing('discountSummary')
            ->loadDeferredProps(['rekap'], fn (Assert $reload) => $reload
                // Inti [BL-116]: harga normal dan yang benar-benar tertagih
                // berdampingan, bukan di dua layar berbeda.
                ->where('discountSummary.gross_sales', 90000)
                ->where('discountSummary.net_sales', 85000)
                ->where('discountSummary.total_given', 5000)
                ->where('discountSummary.items_discounted', 2)
                ->etc()
            )
        );
});

test('monthly report separates loss sales from healthy discounts', function () {
    [$hot] = twoProductsSharingAVariantName();

    sellVariant($hot, qty: 1, subtotal: 19000, occurredAt: '2026-06-04 09:00:00', discountPerUnit: 1000, reason: 'Promo');
    sellVariant($hot, qty: 1, subtotal: 5000, occurredAt: '2026-06-05 09:00:00', discountPerUnit: 15000, reason: 'Kemasan rusak', belowFloor: true);

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-06')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(['rekap'], fn (Assert $reload) => $reload
                ->where('discountSummary.total_given', 16000)
                // Angka yang paling ingin dilihat owner: tanpa dipisahkan,
                // penjualan rugi ini tenggelam di dalam Rp 16.000 di atas.
                ->where('discountSummary.below_floor_total', 15000)
                ->where('discountSummary.below_floor_items', 1)
                ->has('discountSummary.below_floor_lines', 1)
                ->where('discountSummary.below_floor_lines.0.reason', 'Kemasan rusak')
                ->where('discountSummary.below_floor_lines.0.approved_by', $this->owner->name)
                ->etc()
            )
        );
});

test('monthly top products separate the normal price from what was actually billed', function () {
    [$latteHot, , $arenHot] = twoProductsSharingAVariantName();

    sellVariant($latteHot, qty: 4, subtotal: 80000, occurredAt: '2026-06-04 09:00:00', discountPerUnit: 5000, reason: 'Dekat kedaluwarsa');
    sellVariant($arenHot, qty: 2, subtotal: 44000, occurredAt: '2026-06-05 09:00:00');

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly?month=2026-06')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(['rekap'], fn (Assert $reload) => $reload
                ->where('topProducts.0.product_name', 'Cafe Latte')
                // Harga normal = yang tertagih + potongannya, supaya ketiga
                // kolomnya selalu berjumlah tepat.
                ->where('topProducts.0.total_gross', 100000)
                ->where('topProducts.0.total_discount', 20000)
                ->where('topProducts.0.total_revenue', 80000)
                ->where('topProducts.0.variants.0.total_gross', 100000)
                ->where('topProducts.0.variants.0.total_discount', 20000)
                // Produk tanpa potongan tetap membawa kolomnya, bernilai nol —
                // yang menghilang untuk tenant tanpa diskon adalah KOLOMNYA di
                // layar, bukan angkanya di payload.
                ->where('topProducts.1.total_discount', 0)
                ->where('topProducts.1.total_gross', 44000)
                ->etc()
            )
        );
});

test('monthly csv carries the discount block, and omits it entirely when nothing was discounted', function () {
    [$hot] = twoProductsSharingAVariantName();

    sellVariant($hot, qty: 2, subtotal: 16000, occurredAt: '2026-06-03 09:00:00', discountPerUnit: 2000, reason: 'Dekat kedaluwarsa');
    sellVariant($hot, qty: 1, subtotal: 5000, occurredAt: '2026-06-05 09:00:00', discountPerUnit: 15000, reason: 'Kemasan rusak', belowFloor: true);
    sellVariant($hot, qty: 1, subtotal: 20000, occurredAt: '2026-07-06 09:00:00');

    $withDiscounts = $this->actingAs($this->owner)
        ->get('/owner/reports/monthly/export?month=2026-06')
        ->streamedContent();

    expect($withDiscounts)->toContain('POTONGAN HARGA')
        ->toContain('"Harga normal barang terjual",40000')
        ->toContain('"Total dipotong",19000')
        ->toContain('"Tertagih setelah potongan",21000')
        ->toContain('"Di bawah batas untung",15000')
        // Baris rugi ikut lengkap dengan alasan dan siapa yang menyetujui —
        // di situlah owner meninjau keputusannya sendiri.
        ->toContain('PENJUALAN DI BAWAH LANTAI UNTUNG')
        ->toContain('"Kemasan rusak"')
        // Kolom potongan ikut ke tabel produk.
        ->toContain('Peringkat,Produk,Varian,"Qty Terjual","Harga Normal",Potongan,Omzet');

    // Bulan tanpa satu pun potongan: bloknya hilang seluruhnya, dan tabel
    // produknya kembali ke empat kolom. Kolom nol bukan kejujuran.
    $quiet = $this->actingAs($this->owner)
        ->get('/owner/reports/monthly/export?month=2026-07')
        ->streamedContent();

    expect($quiet)->not->toContain('POTONGAN HARGA')
        ->not->toContain('"Harga Normal"')
        ->toContain('Peringkat,Produk,Varian,"Qty Terjual",Omzet');
});
