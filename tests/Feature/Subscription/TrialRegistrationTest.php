<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SubscriptionService;

use function Pest\Laravel\post;

function registerTenant(string $email = 'budi@example.com'): Tenant
{
    post('/register', [
        'business_name' => 'Warung Sapi',
        'name' => 'Budi',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    return Tenant::where('name', 'Warung Sapi')->latest('id')->firstOrFail();
}

test('registrasi membuka masa coba sebulan di paket dasar jalur normal', function () {
    $tenant = registerTenant();
    $subscription = $tenant->subscription;

    expect($subscription)->not->toBeNull()
        ->and($subscription->plan->slug)->toBe(Plan::SLUG_DEFAULT)
        ->and($subscription->pricing_track)->toBe(Subscription::TRACK_NORMAL)
        ->and($subscription->trial_ends_at->toDateString())
        ->toBe(now()->addDays(SubscriptionService::trialDays())->toDateString());
});

test('tenant hasil registrasi berstatus trial', function () {
    expect(registerTenant()->status)->toBe(Tenant::STATUS_TRIAL);
});

test('seat awal mengikuti jatah paket dasar', function () {
    $subscription = registerTenant()->subscription;

    expect($subscription->seats)->toBe(Plan::default()->included_seats);
});

test('harga belum dikunci selama masa coba', function () {
    expect(registerTenant()->subscription->price_locked)->toBeNull();
});

test('registrasi tidak pernah meninggalkan tenant tanpa langganan', function () {
    registerTenant('budi@example.com');
    post('/logout');
    registerTenant('ani@example.com');

    $tanpaLangganan = Tenant::whereDoesntHave('subscription')->count();

    expect($tanpaLangganan)->toBe(0);
});
