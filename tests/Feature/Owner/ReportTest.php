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
