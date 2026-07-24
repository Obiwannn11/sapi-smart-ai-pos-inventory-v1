<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;

test('paket dasar tersedia langsung dari migrasi', function () {
    $plan = Plan::default();

    expect($plan->slug)->toBe(Plan::SLUG_DEFAULT)
        ->and($plan->included_seats)->toBe(1)
        ->and($plan->is_active)->toBeTrue();
});

test('tenant baru mulai sebagai trial di jalur harga normal', function () {
    $tenant = Tenant::factory()->create()->fresh();

    expect($tenant->status)->toBe(Tenant::STATUS_TRIAL)
        ->and($tenant->pricing_track)->toBe(Subscription::TRACK_NORMAL);
});

test('tenant trial dan aktif boleh menulis, tenggang dan suspend tidak', function () {
    expect(Tenant::factory()->create()->canWrite())->toBeTrue()
        ->and(Tenant::factory()->active()->create()->canWrite())->toBeTrue()
        ->and(Tenant::factory()->readOnly()->create()->canWrite())->toBeFalse()
        ->and(Tenant::factory()->suspended()->create()->canWrite())->toBeFalse();
});

test('ensureFor membuka trial sebulan dan tidak membuat langganan kedua', function () {
    $tenant = Tenant::factory()->create();
    $service = app(SubscriptionService::class);

    $first = $service->ensureFor($tenant);
    $second = $service->ensureFor($tenant);

    expect($first->id)->toBe($second->id)
        ->and(Subscription::where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and($first->pricing_track)->toBe(Subscription::TRACK_NORMAL)
        ->and($first->trial_ends_at->toDateString())
        ->toBe(now()->addDays(SubscriptionService::trialDays())->toDateString());
});

test('jalur subsidi tidak pernah jadi keadaan awal', function () {
    $tenant = Tenant::factory()->create();

    $subscription = app(SubscriptionService::class)->ensureFor($tenant);

    expect($subscription->isSubsidized())->toBeFalse();
});

test('seat terpakai hanya menghitung pengguna aktif', function () {
    $tenant = Tenant::factory()->create();
    $subscription = Subscription::factory()->seats(3)->create(['tenant_id' => $tenant->id]);

    User::factory()->count(2)->create(['tenant_id' => $tenant->id]);
    User::factory()->create(['tenant_id' => $tenant->id, 'is_active' => false]);

    expect($subscription->activeSeatsUsed())->toBe(2)
        ->and($subscription->hasSeatAvailable())->toBeTrue();
});

test('seat penuh menutup penambahan berikutnya', function () {
    $tenant = Tenant::factory()->create();
    $subscription = Subscription::factory()->seats(2)->create(['tenant_id' => $tenant->id]);

    User::factory()->count(2)->create(['tenant_id' => $tenant->id]);

    expect($subscription->hasSeatAvailable())->toBeFalse();
});

test('puncak seat naik saat pemakaian bertambah dan tidak ikut turun saat staf dinonaktifkan', function () {
    $tenant = Tenant::factory()->create();
    $subscription = Subscription::factory()->seats(5)->create([
        'tenant_id' => $tenant->id,
        'seat_high_water' => 1,
    ]);

    $staff = User::factory()->count(4)->create(['tenant_id' => $tenant->id]);
    $subscription->recordSeatUsage();

    expect($subscription->fresh()->seat_high_water)->toBe(4);

    // Menonaktifkan tiga staf sehari sebelum tanggal tagih tidak boleh
    // menurunkan dasar tagihan periode berjalan.
    $staff->take(3)->each(fn (User $user) => $user->update(['is_active' => false]));
    $subscription->recordSeatUsage();

    expect($subscription->fresh()->seat_high_water)->toBe(4)
        ->and($subscription->activeSeatsUsed())->toBe(1);
});

test('satu tenant tidak bisa punya dua langganan', function () {
    $tenant = Tenant::factory()->create();
    Subscription::factory()->create(['tenant_id' => $tenant->id]);

    expect(fn () => Subscription::factory()->create(['tenant_id' => $tenant->id]))
        ->toThrow(Illuminate\Database\QueryException::class);
});
