<?php

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * @return array{tenant: Tenant, owner: User, subscription: Subscription}
 */
function makeSeatContext(int $seats = 2): array
{
    $tenant = Tenant::factory()->active()->create();
    $subscription = Subscription::factory()->seats($seats)->create(['tenant_id' => $tenant->id]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    return ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription];
}

function addStaff(string $email = 'staf@usaha.test'): Illuminate\Testing\TestResponse
{
    return post('/owner/staff', [
        'name' => 'Staf',
        'email' => $email,
        'password' => 'password123',
    ]);
}

// --- Penegakan batas ---

test('staf masih bisa ditambah selama seat tersisa', function () {
    ['owner' => $owner] = makeSeatContext(seats: 2);

    actingAs($owner);
    addStaff()->assertSessionHas('success');

    expect(User::where('email', 'staf@usaha.test')->exists())->toBeTrue();
});

test('staf melebihi batas ditolak sebelum akunnya dibuat', function () {
    ['owner' => $owner] = makeSeatContext(seats: 1);

    actingAs($owner);
    addStaff()->assertSessionHas('error');

    // Ditolak SEBELUM dibuat — bukan dibuat lalu ditagih belakangan.
    expect(User::where('email', 'staf@usaha.test')->exists())->toBeFalse();
});

test('pesan penolakan menyebut batasnya dan menunjuk jalan keluar', function () {
    ['owner' => $owner] = makeSeatContext(seats: 1);

    actingAs($owner);
    addStaff();

    $message = session('error');

    expect($message)->toContain('1 pengguna aktif')
        ->and($message)->toContain('Nonaktifkan')
        ->and($message)->toContain('Langganan');
});

test('menonaktifkan staf membebaskan seat untuk penggantinya', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeSeatContext(seats: 2);

    $keluar = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    actingAs($owner);
    addStaff('penuh@usaha.test')->assertSessionHas('error');

    $this->patch("/owner/staff/{$keluar->id}/active")->assertSessionHas('success');

    addStaff('pengganti@usaha.test')->assertSessionHas('success');
    expect($keluar->fresh()->is_active)->toBeFalse();
});

test('mengaktifkan kembali staf ikut melewati gerbang seat', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeSeatContext(seats: 2);

    $nonaktif = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'cashier',
        'is_active' => false,
    ]);
    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    // Owner + satu kasir aktif sudah memenuhi 2 seat.
    actingAs($owner);
    $this->patch("/owner/staff/{$nonaktif->id}/active")->assertSessionHas('error');

    expect($nonaktif->fresh()->is_active)->toBeFalse();
});

test('puncak seat tercatat saat staf ditambah', function () {
    ['owner' => $owner, 'subscription' => $subscription] = makeSeatContext(seats: 3);
    $subscription->update(['seat_high_water' => 1]);

    actingAs($owner);
    addStaff();

    expect($subscription->fresh()->seat_high_water)->toBe(2);
});

test('owner tidak bisa menyentuh staf tenant lain', function () {
    ['owner' => $owner] = makeSeatContext();
    ['tenant' => $lain] = makeSeatContext();

    $stafOrangLain = User::factory()->create(['tenant_id' => $lain->id, 'role' => 'cashier']);

    actingAs($owner);
    $this->patch("/owner/staff/{$stafOrangLain->id}/active")->assertForbidden();
});

// --- Pengguna nonaktif tidak bisa masuk ---

test('staf nonaktif ditolak saat login', function () {
    ['tenant' => $tenant] = makeSeatContext();

    User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'cashier',
        'email' => 'nonaktif@usaha.test',
        'password' => 'password123',
        'is_active' => false,
    ]);

    post('/login', ['email' => 'nonaktif@usaha.test', 'password' => 'password123'])
        ->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

test('sesi yang sedang berjalan ikut terputus begitu akunnya dinonaktifkan', function () {
    ['tenant' => $tenant] = makeSeatContext();

    $kasir = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    actingAs($kasir);
    $this->get('/langganan')->assertStatus(200);

    $kasir->update(['is_active' => false]);

    // Penonaktifan yang hanya menolak login berikutnya membiarkan staf yang
    // baru dipecat terus bekerja sampai ia kebetulan keluar sendiri.
    $this->get('/langganan')->assertRedirect('/login');
});

test('token mobile milik staf nonaktif dicabut saat dipakai', function () {
    ['tenant' => $tenant] = makeSeatContext();

    $kasir = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'cashier',
        'is_active' => false,
    ]);

    Sanctum::actingAs($kasir, ['mobile:use']);

    $this->getJson('/api/v1/mobile/products')->assertStatus(403);
});

test('staf nonaktif ditolak di login mobile', function () {
    ['tenant' => $tenant] = makeSeatContext();

    User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'cashier',
        'email' => 'nonaktif@usaha.test',
        'password' => 'password123',
        'is_active' => false,
    ]);

    $this->postJson('/api/v1/mobile/login', [
        'email' => 'nonaktif@usaha.test',
        'password' => 'password123',
    ])->assertStatus(422);
});
