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
 * Pembelian & pelepasan seat, dan alur bukti bayar yang tersisa.
 *
 * **Alur seat berubah total 2026-08-07** (`[BL-053]`): seat tambahan ditagih
 * BULANAN karena dibeli, bukan sekali bayar lewat tagihan `KIND_UPGRADE`. Yang
 * masih diuji di sini karena masih hidup: bukti bayar untuk tagihan langganan,
 * dan penanganan tagihan `KIND_UPGRADE` PENINGGALAN yang terbit sebelum tanggal
 * itu dan belum selesai. Tagihan peninggalan dibangun lewat factory — tidak ada
 * lagi jalur aplikasi yang menerbitkannya.
 */

/**
 * @return array{tenant: Tenant, owner: User, subscription: Subscription}
 */
function makeUpgradeContext(int $seats = 1, int $purchased = 0): array
{
    Plan::default()->update(['extra_seat_price' => 5000]);

    $tenant = Tenant::factory()->active()->create();
    $subscription = Subscription::factory()->seats($seats)->create([
        'tenant_id' => $tenant->id,
        'purchased_extra_seats' => $purchased,
    ]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    return ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription];
}

function requestUpgrade(int $seats = 2): Illuminate\Testing\TestResponse
{
    return post('/langganan/tambah-pengguna', ['additional_seats' => $seats]);
}

function requestRelease(int $seats = 1): Illuminate\Testing\TestResponse
{
    return post('/langganan/lepas-pengguna', ['released_seats' => $seats]);
}

/**
 * Tagihan `KIND_UPGRADE` peninggalan — bentuk yang dulu diterbitkan aplikasi.
 */
function legacyUpgradeInvoice(Tenant $tenant, Subscription $subscription, int $grants, int $previous, float $amount = 10000): Invoice
{
    return Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'kind' => Invoice::KIND_UPGRADE,
        'grants_seats' => $grants,
        'previous_seats' => $previous,
        'amount' => $amount,
    ]);
}

function uploadProof(Invoice $invoice): Illuminate\Testing\TestResponse
{
    return post("/langganan/tagihan/{$invoice->id}/bukti", [
        'proof' => UploadedFile::fake()->image('bukti.jpg'),
    ]);
}

beforeEach(fn () => Storage::fake('local'));

// --- Membeli seat (`[BL-053]`) ---

test('membeli pengguna tambahan berlaku seketika dan tidak menerbitkan tagihan apa pun', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);

    actingAs($owner);
    requestUpgrade(2)->assertSessionHas('success');

    $subscription->refresh();

    // Tak ada tagihan sekali bayar. Seat tambahan sudah jadi komponen tagihan
    // bulanan; menerbitkan tagihan di muka berarti menagih dua kali untuk hak
    // yang sama.
    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(0)
        ->and($subscription->seats)->toBe(3)
        // Angka yang jadi dasar tagihan punya kolomnya sendiri — tidak lagi
        // disimpulkan dari selisih `seats − included_seats`.
        ->and($subscription->purchased_extra_seats)->toBe(2);
});

test('pembelian tercatat di jejak audit dengan angka sebelum dan sesudahnya', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeUpgradeContext(seats: 1);

    actingAs($owner);
    requestUpgrade(2);

    $logged = App\Models\PlatformAuditLog::where('action', 'subscriptions.seats-granted')->first();

    expect($logged)->not->toBeNull()
        ->and($logged->severity)->toBe(App\Models\PlatformAuditLog::SEVERITY_SENSITIVE)
        ->and($logged->meta['tenant_id'])->toBe($tenant->id)
        ->and($logged->meta['extra_seats_before'])->toBe(0)
        ->and($logged->meta['extra_seats_after'])->toBe(2);
});

test('pembelian kedua di bulan yang sama tidak lagi ditolak', function () {
    ['owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);

    actingAs($owner);
    requestUpgrade(1)->assertSessionHas('success');

    // Dulu ditolak: tagihan upgrade memakai `period` berformat `Y-m`, jadi
    // indeks unik `(tenant_id, period, kind)` hanya mengizinkan satu per bulan
    // kalender (`[BL-050]`). Tanpa tagihan, batas itu tidak punya objek lagi —
    // dan warung yang mempekerjakan dua orang dalam sebulan memang biasa.
    requestUpgrade(1)->assertSessionHas('success');

    expect($subscription->fresh()->purchased_extra_seats)->toBe(2);
});

test('tagihan upgrade peninggalan yang belum selesai menahan pembelian baru', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);

    legacyUpgradeInvoice($tenant, $subscription, grants: 3, previous: 1);

    actingAs($owner);
    requestUpgrade(1)->assertSessionHas('error');

    // Melunasi tagihan lama menulis `seats = grants_seats` — angka dari dunia
    // lama yang akan MENURUNKAN jatah tenant yang baru saja membeli di sini.
    expect($subscription->fresh()->purchased_extra_seats)->toBe(0);
});

test('staf tidak bisa menaikkan tagihan usaha tempatnya bekerja', function () {
    ['tenant' => $tenant] = makeUpgradeContext();

    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    actingAs($cashier);
    requestUpgrade()->assertForbidden();
});

test('staf juga tidak bisa menurunkannya', function () {
    ['tenant' => $tenant] = makeUpgradeContext(seats: 4, purchased: 2);

    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    actingAs($cashier);
    requestRelease()->assertForbidden();
});

// --- Melepas seat (`[BL-053]`) ---

test('pelepasan berlaku satu periode penuh ke depan, bukan hari ini', function () {
    ['owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 4, purchased: 2);

    actingAs($owner);
    requestRelease(1)->assertSessionHas('success');

    $subscription->refresh();

    // Tagihan periode berikutnya terbit SEBELUM periode berjalan habis dan
    // sudah memuat seat itu. Melepasnya di akhir periode berjalan berarti
    // menagih seat yang sudah tidak bisa dipakai.
    $expected = $subscription->nextAnchoredDateAfter($subscription->current_period_end);

    expect($subscription->seat_release_at->toDateString())->toBe($expected->toDateString())
        ->and($subscription->scheduled_extra_seats)->toBe(1)
        // Haknya BELUM turun — sampai tanggal itu kursinya masih boleh dipakai.
        ->and($subscription->purchased_extra_seats)->toBe(2)
        ->and($subscription->seats)->toBe(4);
});

test('seat yang masih diduduki staf aktif tidak bisa dilepas', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 3, purchased: 1);

    // Owner + 2 kasir = 3 pengguna aktif, persis sebanyak kursinya.
    User::factory()->count(2)->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    actingAs($owner);
    requestRelease(1)->assertSessionHas('error');

    // Menolak, bukan memaksakan. Pelepasan kursi yang diam-diam mematikan akun
    // kasir di tengah jam kerja jauh lebih merugikan daripada sebulan tagihan.
    expect($subscription->fresh()->seat_release_at)->toBeNull()
        ->and($subscription->fresh()->activeSeatsUsed())->toBe(3);
});

test('melepas lebih banyak daripada yang kosong ditolak dengan angkanya', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 5, purchased: 3);

    // Owner + 3 kasir = 4 aktif dari 5 kursi, jadi hanya 1 yang boleh dilepas.
    User::factory()->count(3)->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    actingAs($owner);
    requestRelease(2)->assertSessionHas('error');

    expect($subscription->fresh()->seat_release_at)->toBeNull();

    requestRelease(1)->assertSessionHas('success');

    expect($subscription->fresh()->scheduled_extra_seats)->toBe(2);
});

test('membeli lagi membatalkan pelepasan yang sedang menunggu', function () {
    ['owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 4, purchased: 2);

    actingAs($owner);
    requestRelease(2);

    expect($subscription->fresh()->seat_release_at)->not->toBeNull();

    requestUpgrade(1);

    $subscription->refresh();

    // Tenant yang berubah pikiran jelas tidak sedang meminta keduanya.
    // Membiarkan keduanya hidup berarti kursi yang baru dibeli ikut lenyap di
    // tanggal pelepasan, tanpa seorang pun memintanya.
    expect($subscription->seat_release_at)->toBeNull()
        ->and($subscription->scheduled_extra_seats)->toBeNull()
        ->and($subscription->purchased_extra_seats)->toBe(3);
});

test('pelepasan berlaku sendiri begitu tanggalnya tiba', function () {
    ['owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 4, purchased: 2);

    actingAs($owner);
    requestRelease(2);

    $subscription->refresh();
    $this->travelTo($subscription->seat_release_at->copy()->addDay());

    Pest\Laravel\artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    $subscription->refresh();

    expect($subscription->purchased_extra_seats)->toBe(0)
        ->and($subscription->seats)->toBe(2)
        ->and($subscription->seat_release_at)->toBeNull()
        ->and($subscription->scheduled_extra_seats)->toBeNull();
});

test('pelepasan yang belum jatuh tempo tidak disentuh perintah harian', function () {
    ['owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 4, purchased: 2);

    actingAs($owner);
    requestRelease(2);

    Pest\Laravel\artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect($subscription->fresh()->purchased_extra_seats)->toBe(2)
        ->and($subscription->fresh()->seats)->toBe(4);
});

// --- Apa yang dilihat tenant di halaman langganan ---

test('halaman langganan menyebut asal-usul kursinya, bukan pemakaian puncaknya', function () {
    ['owner' => $owner] = makeUpgradeContext(seats: 4, purchased: 2);

    actingAs($owner);

    Pest\Laravel\get('/langganan')->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
        ->where('subscription.seats', 4)
        // Dipecah supaya layar bisa mengatakan "2 dari paket, 2 yang Anda
        // beli". Total saja tidak menjawab pertanyaan yang benar-benar
        // ditanyakan tenant saat melihat tagihannya.
        ->where('subscription.included_seats', 2)
        ->where('subscription.extra_seats', 2)
        ->where('upgrade.releasable_seats', 2)
        ->where('upgrade.release_at', null)
    );
});

test('pelepasan yang sedang menunggu ikut dikirim ke layar beserta tanggalnya', function () {
    ['owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 4, purchased: 2);

    actingAs($owner);
    requestRelease(1);

    $expected = $subscription->fresh()->seat_release_at->toDateString();

    Pest\Laravel\get('/langganan')->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
        ->where('upgrade.scheduled_seats', 1)
        // Tanggalnya wajib ikut: sampai hari itu kursinya masih boleh dipakai,
        // dan tenant yang hanya melihat "akan dilepas" akan mengira kursinya
        // hilang hari ini.
        ->where('upgrade.release_at', $expected)
    );
});

// --- Bukti bayar & pemberlakuan provisional (tagihan peninggalan) ---

test('mengunggah bukti langsung memberlakukan seat tambahan', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);

    $invoice = legacyUpgradeInvoice($tenant, $subscription, grants: 3, previous: 1);

    actingAs($owner);
    uploadProof($invoice)->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_AWAITING_VERIFICATION)
        ->and($invoice->fresh()->proof_path)->not->toBeNull()
        // Menunggu verifikasi manual berarti kasir yang datang pagi ini tidak
        // bisa bekerja sampai ada yang membuka email.
        ->and($subscription->fresh()->seats)->toBe(3);
});

test('bukti bayar disimpan di disk privat, bukan yang bisa diakses publik', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext();

    $invoice = Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
    ]);

    actingAs($owner);
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

// --- Penolakan (tagihan peninggalan) ---

test('penolakan mengembalikan seat tanpa menonaktifkan staf yang terlanjur dibuat', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);

    $invoice = legacyUpgradeInvoice($tenant, $subscription, grants: 3, previous: 1);

    actingAs($owner);
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
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext();

    $invoice = legacyUpgradeInvoice($tenant, $subscription, grants: 3, previous: 1);

    actingAs($owner);
    uploadProof($invoice);

    actingAs(PlatformUser::factory()->withAllModules()->create(), 'platform');
    post("/platform/invoices/{$invoice->id}/reject", ['reason' => 'Nominal kurang.']);

    expect($invoice->fresh()->due_date->toDateString())->toBe(now()->addDays(3)->toDateString());
});

test('tenant yang pernah ditolak tidak lagi dapat pemberlakuan langsung', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);
    $subscription->update(['provisional_blocked' => true]);

    $invoice = legacyUpgradeInvoice($tenant, $subscription, grants: 3, previous: 1);

    actingAs($owner);
    uploadProof($invoice)->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_AWAITING_VERIFICATION)
        // Tetap boleh membayar dan naik paket — hanya saja menunggu diperiksa.
        ->and($subscription->fresh()->seats)->toBe(1);
});

// --- Tagihan Rp 0 (`[BL-049]`) ---

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

    $invoice = legacyUpgradeInvoice($tenant, $subscription, grants: 5, previous: 2, amount: 0);

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

// --- Verifikasi (tagihan peninggalan) ---

test('verifikasi upgrade menambah seat tanpa memperpanjang periode atau mengubah tarif bulanan', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext(seats: 1);
    $subscription->update([
        'provisional_blocked' => true,
        'price_locked' => 50000,
        'current_period_end' => now()->addDays(10)->toDateString(),
    ]);

    $invoice = legacyUpgradeInvoice($tenant, $subscription, grants: 3, previous: 1);

    actingAs($owner);
    uploadProof($invoice);

    actingAs(PlatformUser::factory()->withAllModules()->create(), 'platform');
    post("/platform/invoices/{$invoice->id}/verify")->assertSessionHas('success');

    $subscription->refresh();

    expect($subscription->seats)->toBe(3)
        // Tagihan upgrade bukan harga langganan.
        ->and((float) $subscription->price_locked)->toBe(50000.0)
        ->and($subscription->current_period_end->toDateString())
        ->toBe(now()->addDays(10)->toDateString());
});

test('pemilik saas bisa membuka bukti transfernya dan aksesnya tercatat', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeUpgradeContext();

    $invoice = legacyUpgradeInvoice($tenant, $subscription, grants: 3, previous: 1);

    actingAs($owner);
    uploadProof($invoice);

    actingAs(PlatformUser::factory()->withAllModules()->create(), 'platform');
    $this->get("/platform/invoices/{$invoice->id}/proof")->assertStatus(200);

    expect(App\Models\PlatformAuditLog::where('action', 'invoices.proof.view')->exists())->toBeTrue();
});
