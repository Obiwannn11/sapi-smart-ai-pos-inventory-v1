<?php

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlatformUser;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * @return array{tenant: Tenant, owner: User, subscription: Subscription}
 */
function makeUpgradeContext(int $seats = 1): array
{
    Plan::default()->update(['extra_seat_price' => 5000]);

    $tenant = Tenant::factory()->active()->create();
    $subscription = Subscription::factory()->seats($seats)->create(['tenant_id' => $tenant->id]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    return ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription];
}

function requestUpgrade(int $seats = 2): Illuminate\Testing\TestResponse
{
    return post('/langganan/tambah-pengguna', ['additional_seats' => $seats]);
}

function uploadProof(Invoice $invoice): Illuminate\Testing\TestResponse
{
    return post("/langganan/tagihan/{$invoice->id}/bukti", [
        'proof' => UploadedFile::fake()->image('bukti.jpg'),
    ]);
}

beforeEach(fn () => Storage::fake('local'));

// --- Permintaan upgrade ---

test('permintaan tambah pengguna menerbitkan tagihan seharga tarif per seat', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);

    actingAs($owner);
    requestUpgrade(2)->assertSessionHas('success');

    $invoice = Invoice::where('tenant_id', $tenant->id)->firstOrFail();

    expect($invoice->kind)->toBe(Invoice::KIND_UPGRADE)
        ->and((float) $invoice->amount)->toBe(10000.0)
        ->and($invoice->grants_seats)->toBe(3)
        ->and($invoice->previous_seats)->toBe(1)
        // Seat belum berubah sebelum ada bukti bayar.
        ->and($subscription->fresh()->seats)->toBe(1);
});

test('hanya satu permintaan upgrade berjalan dalam satu waktu', function () {
    ['owner' => $owner] = makeUpgradeContext();

    actingAs($owner);
    requestUpgrade();
    requestUpgrade()->assertSessionHas('error');

    expect(Invoice::where('kind', Invoice::KIND_UPGRADE)->count())->toBe(1);
});

test('staf tidak bisa menaikkan tagihan usaha tempatnya bekerja', function () {
    ['tenant' => $tenant] = makeUpgradeContext();

    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    actingAs($cashier);
    requestUpgrade()->assertForbidden();
});

// --- Bukti bayar & pemberlakuan provisional ---

test('mengunggah bukti langsung memberlakukan seat tambahan', function () {
    ['owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);

    actingAs($owner);
    requestUpgrade(2);

    $invoice = Invoice::firstOrFail();
    uploadProof($invoice)->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_AWAITING_VERIFICATION)
        ->and($invoice->fresh()->proof_path)->not->toBeNull()
        // Menunggu verifikasi manual berarti kasir yang datang pagi ini tidak
        // bisa bekerja sampai ada yang membuka email.
        ->and($subscription->fresh()->seats)->toBe(3);
});

test('bukti bayar disimpan di disk privat, bukan yang bisa diakses publik', function () {
    ['owner' => $owner] = makeUpgradeContext();

    actingAs($owner);
    requestUpgrade();
    $invoice = Invoice::firstOrFail();
    uploadProof($invoice);

    Storage::disk('local')->assertExists($invoice->fresh()->proof_path);
});

test('tenant tidak bisa mengunggah bukti untuk tagihan tenant lain', function () {
    ['owner' => $owner] = makeUpgradeContext();
    ['tenant' => $lain, 'subscription' => $subscriptionLain] = makeUpgradeContext();

    $invoiceOrangLain = Invoice::factory()->create([
        'tenant_id' => $lain->id,
        'subscription_id' => $subscriptionLain->id,
    ]);

    actingAs($owner);
    uploadProof($invoiceOrangLain)->assertForbidden();
});

// --- Penolakan ---

test('penolakan mengembalikan seat tanpa menonaktifkan staf yang terlanjur dibuat', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);

    actingAs($owner);
    requestUpgrade(2);
    $invoice = Invoice::firstOrFail();
    uploadProof($invoice);

    // Tenant memakai seat barunya.
    User::factory()->count(2)->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);
    expect($subscription->fresh()->activeSeatsUsed())->toBe(3);

    $platformUser = PlatformUser::factory()->withAllModules()->create();
    actingAs($platformUser, 'platform');
    post("/platform/invoices/{$invoice->id}/reject", ['reason' => 'Bukti tidak terbaca.'])
        ->assertSessionHas('success');

    $subscription->refresh();

    expect($subscription->seats)->toBe(1)
        ->and($subscription->provisional_blocked)->toBeTrue()
        // Staf yang sudah bekerja TIDAK diusir. Jumlah aktif melampaui seat,
        // dan itu menutup penambahan berikutnya dengan sendirinya.
        ->and($subscription->activeSeatsUsed())->toBe(3)
        ->and($subscription->hasSeatAvailable())->toBeFalse()
        ->and($tenant->fresh()->status)->toBe(Tenant::STATUS_ACTIVE);
});

test('penolakan memberi tenggang tiga hari untuk memperbaiki', function () {
    ['owner' => $owner] = makeUpgradeContext();

    actingAs($owner);
    requestUpgrade();
    $invoice = Invoice::firstOrFail();
    uploadProof($invoice);

    actingAs(PlatformUser::factory()->withAllModules()->create(), 'platform');
    post("/platform/invoices/{$invoice->id}/reject", ['reason' => 'Nominal kurang.']);

    expect($invoice->fresh()->due_date->toDateString())->toBe(now()->addDays(3)->toDateString());
});

test('tenant yang pernah ditolak tidak lagi dapat pemberlakuan langsung', function () {
    ['owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);
    $subscription->update(['provisional_blocked' => true]);

    actingAs($owner);
    requestUpgrade(2);
    $invoice = Invoice::firstOrFail();
    uploadProof($invoice)->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_AWAITING_VERIFICATION)
        // Tetap boleh membayar dan naik paket — hanya saja menunggu diperiksa.
        ->and($subscription->fresh()->seats)->toBe(1);
});

// --- Verifikasi ---

test('verifikasi upgrade menambah seat tanpa memperpanjang periode atau mengubah tarif bulanan', function () {
    ['owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);
    $subscription->update([
        'provisional_blocked' => true,
        'price_locked' => 50000,
        'current_period_end' => now()->addDays(10)->toDateString(),
    ]);

    actingAs($owner);
    requestUpgrade(2);
    $invoice = Invoice::firstOrFail();
    uploadProof($invoice);

    actingAs(PlatformUser::factory()->withAllModules()->create(), 'platform');
    post("/platform/invoices/{$invoice->id}/verify")->assertSessionHas('success');

    $subscription->refresh();

    expect($subscription->seats)->toBe(3)
        // Biaya sekali-bayar untuk kasir tambahan bukan harga langganan.
        ->and((float) $subscription->price_locked)->toBe(50000.0)
        ->and($subscription->current_period_end->toDateString())
        ->toBe(now()->addDays(10)->toDateString());
});

test('pemilik saas bisa membuka bukti transfernya dan aksesnya tercatat', function () {
    ['owner' => $owner] = makeUpgradeContext();

    actingAs($owner);
    requestUpgrade();
    $invoice = Invoice::firstOrFail();
    uploadProof($invoice);

    actingAs(PlatformUser::factory()->withAllModules()->create(), 'platform');
    $this->get("/platform/invoices/{$invoice->id}/proof")->assertStatus(200);

    expect(App\Models\PlatformAuditLog::where('action', 'invoices.proof.view')->exists())->toBeTrue();
});
