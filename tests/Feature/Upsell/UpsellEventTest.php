<?php

use App\Models\CashDrawer;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\UpsellEvent;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    // Permukaan self-order digerbang `feature.api:self_order`, yang bawaannya
    // mati untuk tenant baru. Dinyalakan eksplisit supaya test saran upsell
    // menguji sarannya, bukan gerbangnya.
    $this->tenant = Tenant::factory()->create(['self_order_enabled' => true]);

    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 20000,
        'stock' => 50,
    ]);

    $this->upsizeVariant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 25000,
        'stock' => 50,
    ]);

    $this->cash = PaymentMethod::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => 'cash',
    ]);

    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'closed_at' => null,
    ]);
});

/**
 * Payload checkout POS dengan daftar event upsell yang menyertainya.
 */
function checkoutWith(array $context, array $upsellEvents): array
{
    return [
        'items' => [[
            'variant_id' => $context['variant']->id,
            'variant_name' => 'Kopi Susu - Reguler',
            'qty' => 1,
            'unit_price' => 20000,
            'modifiers' => [],
            'notes' => null,
        ]],
        'payments' => [[
            'payment_method_id' => $context['cash']->id,
            'amount' => 20000,
        ]],
        'client_uuid' => (string) Str::uuid(),
        'upsell_events' => $upsellEvents,
    ];
}

// ── Jalur POS online ────────────────────────────────────────────────────────

test('checkout menyimpan saran yang diambil maupun yang diabaikan', function () {
    actingAs($this->cashier);

    post('/cashier/transactions', checkoutWith(
        ['variant' => $this->variant, 'cash' => $this->cash],
        [
            [
                'type' => 'upsize',
                'status' => 'accepted',
                'reason' => 'price_step',
                'label' => 'Kopi Susu - Large',
                'extra_amount' => 5000,
                'trigger_variant_id' => $this->variant->id,
                'suggested_variant_id' => $this->upsizeVariant->id,
            ],
            [
                'type' => 'pressed_stock',
                'status' => 'ignored',
                'reason' => 'near_expiry',
                'label' => 'Roti Sobek - Cokelat',
                'extra_amount' => 0,
                'suggested_variant_id' => $this->upsizeVariant->id,
            ],
        ],
    ))->assertSessionHas('success');

    expect(UpsellEvent::where('tenant_id', $this->tenant->id)->count())->toBe(2);

    $accepted = UpsellEvent::where('status', UpsellEvent::STATUS_ACCEPTED)->first();

    expect($accepted->type)->toBe('upsize')
        ->and((float) $accepted->extra_amount)->toBe(5000.0)
        ->and($accepted->surface)->toBe(UpsellEvent::SURFACE_POS)
        ->and($accepted->transaction_id)->not->toBeNull();
});

test('saran yang diabaikan tidak pernah membawa tambahan omzet', function () {
    actingAs($this->cashier);

    post('/cashier/transactions', checkoutWith(
        ['variant' => $this->variant, 'cash' => $this->cash],
        [[
            'type' => 'attach',
            'status' => 'ignored',
            'reason' => 'cooccurrence',
            'label' => 'Extra Keju',
            // Client mengirim angka; server tetap menolkannya.
            'extra_amount' => 99000,
        ]],
    ))->assertSessionHas('success');

    expect((float) UpsellEvent::first()->extra_amount)->toBe(0.0);
});

test('event yang merujuk varian tenant lain tetap tercatat tapi tanpa FK asing', function () {
    $otherTenant = Tenant::factory()->create();
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    $foreignVariant = ProductVariant::factory()->create(['product_id' => $otherProduct->id]);

    actingAs($this->cashier);

    post('/cashier/transactions', checkoutWith(
        ['variant' => $this->variant, 'cash' => $this->cash],
        [[
            'type' => 'pressed_stock',
            'status' => 'accepted',
            'reason' => 'dead_stock',
            'label' => 'Barang Tenant Lain',
            'extra_amount' => 10000,
            'suggested_variant_id' => $foreignVariant->id,
        ]],
    ))->assertSessionHas('success');

    $event = UpsellEvent::where('tenant_id', $this->tenant->id)->first();

    expect($event)->not->toBeNull()
        ->and($event->suggested_variant_id)->toBeNull()
        ->and($event->label)->toBe('Barang Tenant Lain');
});

test('event dengan jenis tak dikenal ditolak validasi tanpa menyentuh transaksi', function () {
    actingAs($this->cashier);

    post('/cashier/transactions', checkoutWith(
        ['variant' => $this->variant, 'cash' => $this->cash],
        [[
            'type' => 'diskon_rahasia',
            'status' => 'accepted',
            'label' => 'Entah',
        ]],
    ))->assertSessionHasErrors('upsell_events.0.type');

    expect(Transaction::count())->toBe(0)
        ->and(UpsellEvent::count())->toBe(0);
});

test('checkout tanpa event sama sekali tetap berhasil', function () {
    actingAs($this->cashier);

    $payload = checkoutWith(['variant' => $this->variant, 'cash' => $this->cash], []);
    unset($payload['upsell_events']);

    post('/cashier/transactions', $payload)->assertSessionHas('success');

    expect(UpsellEvent::count())->toBe(0);
});

// ── Jalur offline ───────────────────────────────────────────────────────────

test('sinkronisasi offline menyimpan event yang menumpang outbox', function () {
    $transaction = app(TransactionService::class)->commitOffline([
        'client_uuid' => (string) Str::uuid(),
        'occurred_at' => now()->subHour()->toIso8601String(),
        'device_id' => 'till-01',
        'items' => [[
            'variant_id' => $this->variant->id,
            'variant_name' => 'Kopi Susu - Reguler',
            'qty' => 1,
            'unit_price' => 20000,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->cash->id,
            'amount' => 20000,
        ]],
        'upsell_events' => [[
            'type' => 'attach',
            'status' => 'accepted',
            'reason' => 'catalog',
            'label' => 'Extra Susu',
            'extra_amount' => 3000,
            'trigger_variant_id' => $this->variant->id,
        ]],
    ], $this->cashier);

    $event = UpsellEvent::where('transaction_id', $transaction->id)->first();

    expect($event)->not->toBeNull()
        ->and($event->surface)->toBe(UpsellEvent::SURFACE_POS)
        ->and((float) $event->extra_amount)->toBe(3000.0);
});

// ── Jalur self-order ────────────────────────────────────────────────────────

test('self order menyimpan event dengan permukaan self_order', function () {
    actingAs($this->cashier);

    $transaction = app(TransactionService::class)->createSelfOrder([
        'items' => [[
            'variant_id' => $this->variant->id,
            'variant_name' => 'Kopi Susu - Reguler',
            'qty' => 1,
            'modifiers' => [],
        ]],
        'upsell_events' => [[
            'type' => 'pressed_stock',
            'status' => 'accepted',
            'reason' => 'near_expiry',
            'label' => 'Roti Sobek',
            'extra_amount' => 12000,
            'suggested_variant_id' => $this->upsizeVariant->id,
        ]],
    ]);

    $event = UpsellEvent::where('transaction_id', $transaction->id)->first();

    expect($event)->not->toBeNull()
        ->and($event->surface)->toBe(UpsellEvent::SURFACE_SELF_ORDER);
});

test('endpoint saran self order mengembalikan kandidat untuk keranjang', function () {
    $pressed = ProductVariant::factory()->create([
        'product_id' => Product::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'stock' => 4,
        'expiry_date' => now()->addDay()->toDateString(),
    ]);

    actingAs($this->cashier, 'sanctum');

    $response = $this->postJson('/api/v1/upsell/suggestions', [
        'variant_ids' => [$this->variant->id],
    ])->assertOk();

    $ids = array_column($response->json('suggestions'), 'suggested_variant_id');

    expect($ids)->toContain($pressed->id);
});

// ── Laporan owner ───────────────────────────────────────────────────────────

test('laporan upsell menghitung tingkat terima dan tambahan omzet', function () {
    $owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    UpsellEvent::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
    UpsellEvent::factory()->accepted(7500)->create(['tenant_id' => $this->tenant->id]);

    // Milik tenant lain — tidak boleh ikut terhitung.
    UpsellEvent::factory()->accepted(999000)->create([
        'tenant_id' => Tenant::factory()->create()->id,
    ]);

    actingAs($owner);

    get('/owner/reports/upsell')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Owner/Reports/Upsell')
            ->where('summary.shown', 4)
            ->where('summary.accepted', 1)
            ->where('summary.conversion_rate', 25)
            ->where('summary.extra_revenue', 7500)
        );
});

test('modifier yang disarankan divalidasi kepemilikannya lewat grupnya', function () {
    $otherTenant = Tenant::factory()->create();
    $foreignGroup = ModifierGroup::factory()->create(['tenant_id' => $otherTenant->id]);
    $foreignModifier = Modifier::factory()->create(['modifier_group_id' => $foreignGroup->id]);

    actingAs($this->cashier);

    post('/cashier/transactions', checkoutWith(
        ['variant' => $this->variant, 'cash' => $this->cash],
        [[
            'type' => 'attach',
            'status' => 'accepted',
            'reason' => 'cooccurrence',
            'label' => 'Topping Tenant Lain',
            'extra_amount' => 5000,
            'suggested_modifier_id' => $foreignModifier->id,
        ]],
    ))->assertSessionHas('success');

    expect(UpsellEvent::first()->suggested_modifier_id)->toBeNull();
});

// ── Penawaran wajib & status ditolak ([BL-025]) ─────────────────────────────

test('status ditolak tersimpan terpisah dari diabaikan', function () {
    actingAs($this->cashier);

    post('/cashier/transactions', checkoutWith(
        ['variant' => $this->variant, 'cash' => $this->cash],
        [
            [
                'type' => 'upsize',
                'status' => 'rejected',
                'reason' => 'price_step',
                'label' => 'Kopi Susu - Large',
                'extra_amount' => 5000,
                'trigger_variant_id' => $this->variant->id,
                'suggested_variant_id' => $this->upsizeVariant->id,
            ],
            [
                'type' => 'pressed_stock',
                'status' => 'ignored',
                'reason' => 'near_expiry',
                'label' => 'Roti Sisa',
            ],
        ],
    ))->assertSessionHas('success');

    expect(UpsellEvent::where('status', UpsellEvent::STATUS_REJECTED)->count())->toBe(1)
        ->and(UpsellEvent::where('status', UpsellEvent::STATUS_IGNORED)->count())->toBe(1)
        // Ditolak berarti tidak ada omzet tambahan, sama seperti diabaikan.
        ->and((float) UpsellEvent::where('status', UpsellEvent::STATUS_REJECTED)->value('extra_amount'))
        ->toBe(0.0);
});

test('status di luar daftar tetap ditolak validasi', function () {
    actingAs($this->cashier);

    post('/cashier/transactions', checkoutWith(
        ['variant' => $this->variant, 'cash' => $this->cash],
        [[
            'type' => 'upsize',
            'status' => 'maybe',
            'label' => 'Kopi Susu - Large',
        ]],
    ))->assertSessionHasErrors('upsell_events.0.status');
});

test('indeks upsell membawa saklar wajib milik tenant', function () {
    actingAs($this->cashier);

    // Indeks upsell ikut ditunda bersama katalognya ([BL-037]), jadi ia tidak
    // ada di respons pertama — yang diperiksa adalah permintaan lanjutannya.
    get('/cashier/pos')->assertInertia(fn ($page) => $page
        ->missing('upsell')
        ->loadDeferredProps(fn ($reload) => $reload->where('upsell.mandatory', false))
    );

    $this->tenant->update(['upsell_mandatory' => true]);

    // actingAs memegang instance User yang SAMA lintas request dalam satu test,
    // jadi relasi `tenant` yang sudah ter-load ikut terbawa basi. Login ulang
    // dengan instance segar — ini artefak test, bukan perilaku request nyata.
    actingAs($this->cashier->fresh());

    get('/cashier/pos')->assertInertia(fn ($page) => $page
        ->loadDeferredProps(fn ($reload) => $reload->where('upsell.mandatory', true))
    );
});

test('owner bisa menyalakan penawaran wajib dari pengaturan', function () {
    $owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);

    actingAs($owner)
        ->patch('/owner/settings', ['upsell_mandatory' => true])
        ->assertSessionHas('success');

    expect($this->tenant->fresh()->upsell_mandatory)->toBeTrue();
});

test('laporan memisahkan tingkat terima dari tingkat sukses tawar', function () {
    $owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);
    $transaction = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
    ]);

    $make = fn (string $status) => UpsellEvent::create([
        'tenant_id' => $this->tenant->id,
        'transaction_id' => $transaction->id,
        'type' => UpsellEvent::TYPE_UPSIZE,
        'surface' => UpsellEvent::SURFACE_POS,
        'status' => $status,
        'label' => 'Kopi Susu - Large',
        'extra_amount' => $status === UpsellEvent::STATUS_ACCEPTED ? 5000 : 0,
    ]);

    $make(UpsellEvent::STATUS_ACCEPTED);
    $make(UpsellEvent::STATUS_REJECTED);
    $make(UpsellEvent::STATUS_IGNORED);
    $make(UpsellEvent::STATUS_IGNORED);

    actingAs($owner)
        ->get('/owner/reports/upsell')
        ->assertInertia(fn ($page) => $page
            ->where('summary.shown', 4)
            ->where('summary.accepted', 1)
            ->where('summary.rejected', 1)
            ->where('summary.offered', 2)
            // 1 dari 4 yang tampil …
            ->where('summary.conversion_rate', 25)
            // … tapi 1 dari 2 yang benar-benar ditawarkan.
            ->where('summary.offer_rate', 50)
        );
});
