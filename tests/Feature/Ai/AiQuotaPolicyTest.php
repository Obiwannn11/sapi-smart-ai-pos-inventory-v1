<?php

use App\Models\AiQuotaPolicy;
use App\Models\AiUsage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Ai\AiQuota;

/**
 * Urutan pembacaan kuota AI (`[BL-047]`(a)).
 *
 * Yang diuji di sini bukan angkanya melainkan URUTANNYA, karena itulah satu
 * hal yang tidak boleh bercabang: `RunAiAnalysisJob` dan halaman Pengaturan
 * memanggil kelas yang sama, dan seluruh gunanya hilang begitu ada lapis yang
 * hanya terbaca salah satunya.
 */
beforeEach(function () {
    config(['ai.free_tier.daily_limit' => 5]);
    $this->tenant = Tenant::factory()->create();
});

/** Kuota selalu dibaca lewat instance baru — memo per instance jangan bocor antar kasus. */
function quota(): AiQuota
{
    return app()->make(AiQuota::class);
}

function subscribeTo(Tenant $tenant, ?int $aiDaily): void
{
    $plan = Plan::factory()->create([
        'limits' => $aiDaily === null ? [] : ['ai_daily' => $aiDaily],
    ]);

    Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id]);
}

test('tanpa kebijakan apa pun, yang berlaku tetap bawaan config', function () {
    expect(quota()->dailyLimitFor($this->tenant))->toBe(5);
});

test('kebijakan bawaan menggantikan angka config', function () {
    AiQuotaPolicy::factory()->baseline(12)->create();

    expect(quota()->dailyLimitFor($this->tenant))->toBe(12);
});

test('batas paket tetap mendahului kebijakan bawaan', function () {
    subscribeTo($this->tenant, 30);
    AiQuotaPolicy::factory()->baseline(12)->create();

    expect(quota()->dailyLimitFor($this->tenant))->toBe(30);
});

test('promo menambah di atas batas paket, bukan sekadar mengisi kekosongan', function () {
    // Inti alasan promo tidak dijadikan mata rantai di urutan fallback: setiap
    // paket menetapkan batasnya sendiri, jadi lapis yang hanya mengisi
    // kekosongan tidak akan pernah menyentuh tenant yang berlangganan.
    subscribeTo($this->tenant, 30);
    AiQuotaPolicy::factory()->bonus(10)->create();

    expect(quota()->dailyLimitFor($this->tenant))->toBe(40);
});

test('promo juga menambah di atas kebijakan bawaan', function () {
    AiQuotaPolicy::factory()->baseline(12)->create();
    AiQuotaPolicy::factory()->bonus(3)->create();

    expect(quota()->dailyLimitFor($this->tenant))->toBe(15);
});

test('promo tidak membukakan AI untuk paket yang batasnya nol', function () {
    subscribeTo($this->tenant, 0);
    AiQuotaPolicy::factory()->bonus(10)->create();

    expect(quota()->dailyLimitFor($this->tenant))->toBe(0);
});

test('kebijakan yang sudah lewat tanggal akhirnya berhenti sendiri', function () {
    AiQuotaPolicy::factory()->baseline(50)->expired()->create();

    expect(quota()->dailyLimitFor($this->tenant))->toBe(5);
});

test('kebijakan yang belum mulai belum berlaku', function () {
    AiQuotaPolicy::factory()->baseline(50)->upcoming()->create();

    expect(quota()->dailyLimitFor($this->tenant))->toBe(5);
});

test('di antara dua kebijakan sejenis yang tumpang tindih, yang paling baru berlaku menang', function () {
    AiQuotaPolicy::factory()->baseline(10)->create(['effective_from' => now()->subDays(5)->toDateString()]);
    AiQuotaPolicy::factory()->baseline(20)->create(['effective_from' => now()->subDay()->toDateString()]);

    expect(quota()->dailyLimitFor($this->tenant))->toBe(20);
});

test('kebijakan yang dihentikan berhenti dibaca tapi barisnya tetap ada', function () {
    $policy = AiQuotaPolicy::factory()->baseline(20)->create();
    $policy->delete();

    expect(quota()->dailyLimitFor($this->tenant))->toBe(5)
        ->and(AiQuotaPolicy::withTrashed()->count())->toBe(1);
});

test('snapshot menyebut promonya, bukan meleburkannya ke angka batas', function () {
    // Owner yang tak diberi tahu asal tambahan jatahnya akan mengira
    // aplikasinya rusak pada hari promonya berakhir.
    AiQuotaPolicy::factory()->baseline(10)->create(['label' => 'Bawaan 2026']);
    AiQuotaPolicy::factory()->bonus(5)->create(['label' => 'Promo Agustus']);

    $snapshot = quota()->snapshotFor($this->tenant);

    expect($snapshot['daily_limit'])->toBe(15)
        ->and($snapshot['bonus'])->toBe(5)
        ->and($snapshot['bonus_label'])->toBe('Promo Agustus')
        ->and($snapshot['limit_source'])->toBe('policy');
});

test('limit_source membedakan kebijakan dari bawaan config', function () {
    expect(quota()->snapshotFor($this->tenant)['limit_source'])->toBe('platform');

    AiQuotaPolicy::factory()->baseline(10)->create();

    expect(quota()->snapshotFor($this->tenant)['limit_source'])->toBe('policy');
});

test('reset hanya menyentuh hitungan hari ini', function () {
    $lain = Tenant::factory()->create();

    AiUsage::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'date' => now()->toDateString(), 'count' => 4]);
    AiUsage::withoutGlobalScopes()->create(['tenant_id' => $lain->id, 'date' => now()->toDateString(), 'count' => 2]);
    AiUsage::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'date' => now()->subDay()->toDateString(), 'count' => 9]);

    $affected = quota()->resetUsageToday();

    expect($affected)->toBe(2)
        ->and(quota()->usedTodayBy($this->tenant))->toBe(0)
        ->and(quota()->usedTodayBy($lain))->toBe(0)
        // Riwayat kemarin bukan bagian dari "kuota hari ini" dan tidak boleh
        // ikut lenyap — ia satu-satunya dasar menjawab pemakaian bulan berjalan.
        ->and(AiUsage::withoutGlobalScopes()->count())->toBe(1);
});

test('ringkasan pemakaian menjumlahkan tanpa merinci per tenant', function () {
    $lain = Tenant::factory()->create();

    AiUsage::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'date' => now()->toDateString(), 'count' => 4]);
    AiUsage::withoutGlobalScopes()->create(['tenant_id' => $lain->id, 'date' => now()->toDateString(), 'count' => 3]);

    expect(quota()->usageTodaySummary())->toBe(['tenants' => 2, 'analyses' => 7]);
});
