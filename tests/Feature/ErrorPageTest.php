<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Register throwaway routes that abort with a given status, and pretend we are
 * running in production so the exception handler renders the Inertia error page
 * (it is intentionally bypassed in the local/testing environments).
 */
beforeEach(function () {
    app()->detectEnvironment(fn () => 'production');

    Route::middleware('web')->get('/__test/error/{status}', function (int $status) {
        abort($status);
    });
});

test('owner sees the admin error page for a 500', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($owner)
        ->get('/__test/error/500')
        ->assertStatus(500)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Errors/Admin')
            ->where('status', 500)
        );
});

test('cashier sees the user error page for a 500', function () {
    $tenant = Tenant::factory()->create();
    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    actingAs($cashier)
        ->get('/__test/error/500')
        ->assertStatus(500)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Errors/User')
            ->where('status', 500)
        );
});

test('owner sees the admin error page for a 400', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($owner)
        ->get('/__test/error/400')
        ->assertStatus(400)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Errors/Admin')
            ->where('status', 400)
        );
});

test('guest sees the user error page', function () {
    get('/__test/error/400')
        ->assertStatus(400)
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Errors/User')
            ->where('status', 400)
        );
});

test('other status codes are not intercepted by the custom error page', function () {
    get('/__test/error/404')
        ->assertStatus(404);
});
