<?php

use App\Models\Plan;
use App\Models\PricingRule;
use App\Services\Pricing\PublicPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Migrasi `plans` dan `pricing_rules` sama-sama menanam baris bawaan (empat
    // paket, bracket A–D). Test ini menyusun tangganya sendiri supaya yang
    // diuji bentuk pembacanya, bukan isi benih migrasi yang bisa berubah.
    Plan::query()->delete();
    PricingRule::query()->delete();
    $this->reader = app(PublicPricing::class);
});

test('paket dibacakan termurah lebih dulu, dengan seat dan kuota AI-nya', function () {
    Plan::factory()->create(['slug' => 'paid-2', 'name' => 'Paid 2', 'base_price' => 150_000, 'included_seats' => 5, 'extra_seat_price' => 12_500, 'limits' => ['ai_daily' => 30]]);
    Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'base_price' => 0, 'included_seats' => 2, 'extra_seat_price' => 20_000, 'limits' => ['ai_daily' => 5]]);

    $plans = $this->reader->snapshot()['plans'];

    expect(array_column($plans, 'slug'))->toBe(['free', 'paid-2'])
        ->and($plans[0])->toMatchArray([
            'name' => 'Free',
            'price' => 0.0,
            'included_seats' => 2,
            'extra_seat_price' => 20_000.0,
            'ai_daily' => 5,
            'is_free' => true,
        ])
        ->and($plans[1]['ai_daily'])->toBe(30);
});

test('paket nonaktif tidak pernah dibacakan ke publik', function () {
    // Paket nonaktif adalah paket yang pemiliknya berhenti menjual. Memajangnya
    // mengundang pendaftaran ke sesuatu yang tidak ada tempatnya.
    Plan::factory()->create(['slug' => 'dijual', 'base_price' => 100_000]);
    Plan::factory()->inactive()->create(['slug' => 'ditarik', 'base_price' => 50_000]);

    expect(array_column($this->reader->snapshot()['plans'], 'slug'))->toBe(['dijual']);
});

test('paket tanpa batas AI dibacakan null, bukan nol', function () {
    // `Plan::limit()` membedakan "tidak menyetel batas" dari "menyetel nol", dan
    // bedanya menentukan: yang pertama berarti ikut bawaan platform, yang kedua
    // berarti paket ini sengaja tidak menjual AI. Meleburnya jadi satu membuat
    // halaman publik memajang "0 analisis/hari" untuk paket yang sebenarnya
    // dapat jatah bawaan.
    Plan::factory()->create(['slug' => 'ikut-bawaan', 'base_price' => 10_000, 'limits' => null]);
    Plan::factory()->create(['slug' => 'tanpa-ai', 'base_price' => 20_000, 'limits' => ['ai_daily' => 0]]);

    $plans = collect($this->reader->snapshot()['plans'])->keyBy('slug');

    expect($plans['ikut-bawaan']['ai_daily'])->toBeNull()
        ->and($plans['tanpa-ai']['ai_daily'])->toBe(0);
});

test('tangga adaptif dibacakan apa adanya dari pricing_rules, berikut ambangnya', function () {
    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);
    PricingRule::factory()->revenueBetween(2_000_000, 15_000_000)->create(['label' => 'B', 'price' => 25_000]);
    PricingRule::factory()->revenueBetween(15_000_000, 50_000_000)->create(['label' => 'C', 'price' => 75_000]);

    $adaptive = $this->reader->snapshot()['adaptive'];

    expect(array_column($adaptive['ladder'], 'label'))->toBe(['A', 'B', 'C'])
        ->and(array_column($adaptive['ladder'], 'price'))->toBe([10_000.0, 25_000.0, 75_000.0])
        ->and($adaptive['ceiling'])->toBe(50_000_000.0);
});

test('tangga adaptif tanpa batas atas dibacakan sebagai tanpa ambang, bukan ambang nol', function () {
    // Arah gagal yang diperingatkan `[BL-048]`: memperlakukan "tidak ada ambang"
    // sebagai "ambangnya nol" membuat halaman publik memberi tahu SETIAP
    // pengunjung bahwa omzetnya terlalu tinggi untuk Harga Adaptif.
    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);
    PricingRule::factory()->revenueBetween(2_000_000)->create(['label' => 'B', 'price' => 25_000]);

    expect($this->reader->snapshot()['adaptive']['ceiling'])->toBeNull();
});

test('paket penampung jalur adaptif disebut namanya', function () {
    Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'base_price' => 0]);
    $host = Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000]);
    $host->setAdaptiveFallback(true);

    $snapshot = $this->reader->snapshot();

    expect($snapshot['adaptive']['host_plan'])->toBe('Paid 1')
        ->and(collect($snapshot['plans'])->firstWhere('slug', 'paid-1')['hosts_adaptive'])->toBeTrue()
        ->and(collect($snapshot['plans'])->firstWhere('slug', 'free')['hosts_adaptive'])->toBeFalse();
});

test('syarat masa gratis dan perpindahan jalur ikut dibacakan', function () {
    config(['subscription.trial_months' => 2, 'subscription.track_switch_minimum_months' => 3]);

    expect($this->reader->snapshot())
        ->trial_months->toBe(2)
        ->track_switch_minimum_months->toBe(3);
});

test('pembacanya tidak menyentuh tenant sama sekali', function () {
    // Syarat berdirinya kelas ini: ia dipanggil dari halaman tanpa sesi. Begitu
    // ia menyentuh tenant, halaman publik akan meledak bagi tamu — atau lebih
    // buruk, membacakan data satu tenant kepada semua orang.
    Plan::factory()->create(['slug' => 'paid-1', 'base_price' => 100_000]);

    expect(fn () => $this->reader->snapshot())->not->toThrow(Throwable::class)
        ->and($this->reader->snapshot()['plans'])->toHaveCount(1);
});
