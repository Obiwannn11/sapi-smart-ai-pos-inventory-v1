<?php

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlatformUser;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\InvoiceSettlement;
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

// --- Tagihan Rp 0 (`[BL-049]`) ---

test('penambahan pengguna gratis langsung berlaku tanpa menuntut bukti transfer', function () {
    ['owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);
    Plan::default()->update(['extra_seat_price' => 0]);

    actingAs($owner);
    requestUpgrade(2)->assertSessionHas('success');

    $invoice = Invoice::firstOrFail();

    expect($invoice->status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->settled_via)->toBe(InvoiceSettlement::SOURCE_ZERO_AMOUNT)
        // Tak ada yang memeriksa apa pun — tak ada yang perlu diperiksa.
        ->and($invoice->verified_by)->toBeNull()
        ->and($invoice->proof_path)->toBeNull()
        ->and($subscription->fresh()->seats)->toBe(3);
});

test('permintaan kedua di bulan yang sama ditolak dengan kalimat, bukan galat 500', function () {
    ['owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);
    Plan::default()->update(['extra_seat_price' => 0]);

    actingAs($owner);
    requestUpgrade(1)->assertSessionHas('success');

    // Yang pertama sudah lunas, jadi `openUpgradeInvoice()` tidak lagi
    // menahannya — dan tanpa penjaga periode, `Invoice::create()` menabrak
    // indeks unik `(tenant_id, period, kind)`.
    requestUpgrade(1)->assertSessionHas('error');

    expect(Invoice::where('kind', Invoice::KIND_UPGRADE)->count())->toBe(1)
        ->and($subscription->fresh()->seats)->toBe(2);
});

test('bukti yang pernah ditolak tidak mengunci penambahan yang memang gratis', function () {
    ['owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);
    $subscription->update(['provisional_blocked' => true]);
    Plan::default()->update(['extra_seat_price' => 0]);

    actingAs($owner);
    requestUpgrade(2);

    // `provisional_blocked` menahan seat yang naik tanpa dibayar. Di sini tak
    // ada yang harus dibayar, jadi menegakkannya hanya membuat jalan buntu.
    expect($subscription->fresh()->seats)->toBe(3);
});

test('tagihan langganan Rp 0 TIDAK ikut dilunasi sendiri', function () {
    ['tenant' => $tenant, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'kind' => Invoice::KIND_SUBSCRIPTION,
        'amount' => 0,
    ]);

    expect(app(InvoiceSettlement::class)->settleIfFree($invoice))->toBeFalse()
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID)
        // Melunasinya akan menulis `price_locked = 0` lalu memperpanjang
        // periodenya — mewariskan tarif nol yang belum pernah diputuskan.
        ->and((float) $subscription->fresh()->price_locked)->toBe(0.0)
        ->and($tenant->fresh()->status)->toBe(Tenant::STATUS_ACTIVE);
});

test('perintah melunasi tagihan gratis yang terlanjur menggantung', function () {
    ['tenant' => $tenant, 'subscription' => $subscription] = makeUpgradeContext(seats: 2);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'kind' => Invoice::KIND_UPGRADE,
        'amount' => 0,
        'grants_seats' => 5,
        'previous_seats' => 2,
    ]);

    $this->artisan('subscriptions:settle-free-upgrades', ['--dry-run' => true])->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID)
        ->and($subscription->fresh()->seats)->toBe(2);

    $this->artisan('subscriptions:settle-free-upgrades')->assertSuccessful();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($subscription->fresh()->seats)->toBe(5);

    // Idempoten: tidak ada lagi yang tersisa untuk dilunasi.
    $this->artisan('subscriptions:settle-free-upgrades')
        ->expectsOutputToContain('Tidak ada tagihan penambahan pengguna Rp 0 yang menggantung.')
        ->assertSuccessful();
});

test('perintah tidak menyentuh tagihan gratis yang buktinya sudah ditolak', function () {
    ['tenant' => $tenant, 'subscription' => $subscription] = makeUpgradeContext(seats: 2);

    $invoice = Invoice::factory()->rejected()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'kind' => Invoice::KIND_UPGRADE,
        'amount' => 0,
        'grants_seats' => 5,
        'previous_seats' => 2,
    ]);

    $this->artisan('subscriptions:settle-free-upgrades')->assertSuccessful();

    // Ada orang yang pernah memutuskan ini. Membatalkannya diam-diam bukan
    // tugas sebuah perintah backfill.
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_REJECTED)
        ->and($subscription->fresh()->seats)->toBe(2);
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
