<?php

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantConsent;
use App\Models\TenantMonthlyMetric;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PricingService;

use function Pest\Laravel\artisan;

/**
 * @return array{tenant: Tenant, owner: User}
 */
function makeMetricContext(string $track = Subscription::TRACK_SUBSIDIZED): array
{
    $tenant = Tenant::factory()->active()->create(['pricing_track' => $track]);
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'pricing_track' => $track]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    // Persetujuan yang masih berlaku adalah gerbang kedua job penghitung omzet.
    // Tanpa barisnya, tenant berjalur subsidi pun tidak dihitung — dan memang
    // begitu seharusnya: tak ada izin, tak ada pengumpulan data.
    if ($track === Subscription::TRACK_SUBSIDIZED) {
        TenantConsent::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'type' => TenantConsent::TYPE_SUBSIDIZED,
        ]);
    }

    return ['tenant' => $tenant, 'owner' => $owner];
}

function makeSale(Tenant $tenant, User $user, float $amount, string $status, ?Carbon\Carbon $occurredAt = null): Transaction
{
    return Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'total_amount' => $amount,
        'status' => $status,
        'occurred_at' => $occurredAt ?? now()->subMonth()->startOfMonth()->addDays(3),
    ]);
}

// --- Gerbang privasi ---

test('hanya tenant jalur subsidi yang punya baris ringkasan omset', function () {
    ['tenant' => $subsidi, 'owner' => $ownerSubsidi] = makeMetricContext(Subscription::TRACK_SUBSIDIZED);
    ['tenant' => $normal, 'owner' => $ownerNormal] = makeMetricContext(Subscription::TRACK_NORMAL);

    makeSale($subsidi, $ownerSubsidi, 500000, Transaction::STATUS_COMPLETED);
    makeSale($normal, $ownerNormal, 900000, Transaction::STATUS_COMPLETED);

    artisan('subscriptions:compute-revenue')->assertSuccessful();

    // Tenant jalur normal bukan "disembunyikan di UI" — datanya memang tidak
    // pernah ada.
    expect(TenantMonthlyMetric::where('tenant_id', $subsidi->id)->exists())->toBeTrue()
        ->and(TenantMonthlyMetric::where('tenant_id', $normal->id)->exists())->toBeFalse();
});

test('ringkasan omset tidak pernah menyimpan laba atau margin', function () {
    $kolom = Illuminate\Support\Facades\Schema::getColumnListing('tenant_monthly_metrics');

    // Bukan disimpan lalu disembunyikan: memang tidak pernah dihitung.
    expect($kolom)->not->toContain('profit')
        ->not->toContain('margin')
        ->not->toContain('cogs')
        ->not->toContain('hpp');
});

// --- Ketepatan perhitungan ---

test('hanya transaksi selesai yang dihitung', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeMetricContext();

    makeSale($tenant, $owner, 300000, Transaction::STATUS_COMPLETED);
    makeSale($tenant, $owner, 200000, Transaction::STATUS_COMPLETED);
    makeSale($tenant, $owner, 999000, Transaction::STATUS_VOIDED);
    makeSale($tenant, $owner, 888000, Transaction::STATUS_PENDING);

    artisan('subscriptions:compute-revenue');

    $metric = TenantMonthlyMetric::where('tenant_id', $tenant->id)->firstOrFail();

    expect((float) $metric->revenue)->toBe(500000.0)
        ->and($metric->transaction_count)->toBe(2);
});

test('transaksi offline dihitung ke bulan kejadiannya, bukan bulan sinkronnya', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeMetricContext();

    $bulanLalu = now()->subMonth();

    // Dibuat di server hari ini (created_at bulan ini) tapi terjadi bulan lalu.
    $transaksi = makeSale($tenant, $owner, 750000, Transaction::STATUS_COMPLETED, $bulanLalu->copy()->startOfMonth()->addDays(5));
    $transaksi->forceFill(['created_at' => now()])->save();

    artisan('subscriptions:compute-revenue');

    $metric = TenantMonthlyMetric::where('tenant_id', $tenant->id)
        ->where('period', $bulanLalu->format('Y-m'))
        ->firstOrFail();

    expect((float) $metric->revenue)->toBe(750000.0);
});

test('menghitung ulang periode yang sama memperbarui barisnya, bukan menambah', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeMetricContext();

    makeSale($tenant, $owner, 100000, Transaction::STATUS_COMPLETED);
    artisan('subscriptions:compute-revenue');

    makeSale($tenant, $owner, 400000, Transaction::STATUS_COMPLETED);
    artisan('subscriptions:compute-revenue');

    $metrics = TenantMonthlyMetric::where('tenant_id', $tenant->id)->get();

    expect($metrics)->toHaveCount(1)
        ->and((float) $metrics->first()->revenue)->toBe(500000.0);
});

test('periode bisa dipilih untuk menghitung ulang bulan tertentu', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeMetricContext();

    $duaBulanLalu = now()->subMonths(2);
    makeSale($tenant, $owner, 250000, Transaction::STATUS_COMPLETED, $duaBulanLalu->copy()->startOfMonth()->addDay());

    artisan('subscriptions:compute-revenue', ['--period' => $duaBulanLalu->format('Y-m')])->assertSuccessful();

    expect(TenantMonthlyMetric::where('period', $duaBulanLalu->format('Y-m'))->exists())->toBeTrue();
});

test('format periode yang salah ditolak', function () {
    artisan('subscriptions:compute-revenue', ['--period' => 'Agustus'])->assertFailed();
});

// --- Bracket ---

test('bracket dipilih dari angka omset', function () {
    $pricing = app(PricingService::class);

    expect($pricing->bracketFor(1_500_000)['label'])->toBe('A')
        ->and($pricing->bracketFor(3_000_000)['label'])->toBe('B')
        ->and($pricing->bracketFor(9_000_000)['label'])->toBe('C')
        ->and($pricing->bracketFor(50_000_000)['label'])->toBe('D');
});

test('batas bracket tidak tumpang tindih di titik sambungnya', function () {
    $pricing = app(PricingService::class);

    // Tepat di batas masuk ke bracket ATAS, bukan tetap di bawah.
    expect($pricing->bracketFor(2_000_000)['label'])->toBe('B')
        ->and($pricing->bracketFor(1_999_999)['label'])->toBe('A');
});

test('bracket berjalan tenant dibaca dari tabel ringkasan', function () {
    ['tenant' => $tenant] = makeMetricContext();

    TenantMonthlyMetric::factory()->revenue(3_500_000)->create(['tenant_id' => $tenant->id]);

    $bracket = app(PricingService::class)->currentBracketFor($tenant);

    expect($bracket['label'])->toBe('B')
        ->and($bracket['price'])->toBe(25_000.0)
        ->and($bracket['revenue'])->toBe(3_500_000.0);
});

// --- Retensi ---

test('ringkasan yang lewat retensi dipangkas', function () {
    ['tenant' => $tenant] = makeMetricContext();

    $lama = now()->subMonths(30)->format('Y-m');
    $baru = now()->subMonth()->format('Y-m');

    TenantMonthlyMetric::factory()->forPeriod($lama)->create(['tenant_id' => $tenant->id]);
    TenantMonthlyMetric::factory()->forPeriod($baru)->create(['tenant_id' => $tenant->id]);

    artisan('subscriptions:prune-metrics')->assertSuccessful();

    expect(TenantMonthlyMetric::where('period', $lama)->exists())->toBeFalse()
        ->and(TenantMonthlyMetric::where('period', $baru)->exists())->toBeTrue();
});

test('dry-run tidak menghapus apa pun', function () {
    ['tenant' => $tenant] = makeMetricContext();

    $lama = now()->subMonths(30)->format('Y-m');
    TenantMonthlyMetric::factory()->forPeriod($lama)->create(['tenant_id' => $tenant->id]);

    artisan('subscriptions:prune-metrics', ['--dry-run' => true])->assertSuccessful();

    expect(TenantMonthlyMetric::where('period', $lama)->exists())->toBeTrue();
});
