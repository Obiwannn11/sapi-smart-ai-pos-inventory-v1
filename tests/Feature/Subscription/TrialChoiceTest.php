<?php

use App\Models\Plan;
use App\Models\PricingRule;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Momen pilihan jalur di akhir masa coba — `[BL-044]`(c).
 *
 * Butir (b) membuat tagihan terbit sendiri; yang belum ada adalah pertanyaan
 * yang mendahuluinya. Catatan pemilik meminta "wajib ajukan subsidi ATAU kena
 * tagihan normal", dan kata "atau" itu mengandaikan tenant pernah DITANYA.
 * Sampai sekarang tidak pernah: jalur harga dipilih diam-diam saat pendaftaran
 * dan hanya berubah bila tenant sendiri menemukan halaman Langganan.
 *
 * Yang diuji di sini tiga hal yang kalau salah merugikan orang: kapan
 * pertanyaannya muncul, jalur mana yang boleh dijawab, dan apakah jawabannya
 * masih sempat mengubah tagihan pertama.
 */

/** Tangga bracket berujung, sama bentuknya dengan yang terpasang di produksi. */
function seedTrialChoiceLadder(): void
{
    PricingRule::query()->forceDelete();

    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);
    PricingRule::factory()->revenueBetween(2_000_000, 15_000_000)->create(['label' => 'B', 'price' => 25_000]);
    PricingRule::factory()->revenueBetween(15_000_000, 50_000_000)->create(['label' => 'C', 'price' => 75_000]);
}

/** Paket berbayar yang menunggu tenant setelah masa coba. */
function trialChoiceTarget(float $basePrice = 100_000): Plan
{
    $plan = Plan::factory()->create([
        'name' => 'Premium 1',
        'slug' => 'paid-1',
        'base_price' => $basePrice,
        'included_seats' => 3,
    ]);

    $plan->setPostTrialTarget(true);

    return $plan;
}

/**
 * Tenant masa coba yang masih duduk di paket gratis, berakhir
 * `$daysUntilTrialEnds` hari lagi.
 *
 * @return array{tenant: Tenant, owner: User, subscription: Subscription}
 */
function trialChoiceContext(int $daysUntilTrialEnds, string $status = Tenant::STATUS_TRIAL): array
{
    $free = Plan::default();
    $tenant = Tenant::factory()->create(['status' => $status]);
    $trialEnds = now()->addDays($daysUntilTrialEnds)->startOfDay();

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $free->id,
        'seats' => $free->included_seats,
        'trial_ends_at' => $trialEnds,
        'current_period_start' => $trialEnds->copy()->subMonthsNoOverflow(2)->toDateString(),
        'current_period_end' => $trialEnds->toDateString(),
        'billing_anchor_day' => $trialEnds->day,
    ]);

    return [
        'tenant' => $tenant,
        'owner' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']),
        'subscription' => $subscription,
    ];
}

/** Penjualan di bulan yang sudah TUTUP — periode yang dipakai penilaian omzet. */
function recordTrialChoiceSale(Tenant $tenant, User $owner, float $amount): void
{
    Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => $amount,
        'occurred_at' => now()->startOfMonth()->subMonth()->addDays(5),
    ]);
}

// --- Kapan pertanyaannya muncul ---

test('masa coba yang masih jauh tidak disodori pilihan apa pun', function () {
    seedTrialChoiceLadder();
    trialChoiceTarget();
    ['owner' => $owner] = trialChoiceContext(daysUntilTrialEnds: 20);

    actingAs($owner);

    // Sesuatu yang selalu ada di layar berhenti dibaca jauh sebelum harinya
    // tiba. Jendelanya sempit dengan sengaja.
    get('/owner/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('subscription.trial_choice', null)
    );
});

test('jendelanya terbuka lebih awal daripada penerbitan tagihan, bukan bersamaan', function () {
    seedTrialChoiceLadder();
    trialChoiceTarget();
    ['owner' => $owner] = trialChoiceContext(daysUntilTrialEnds: 14);

    actingAs($owner);

    // Inti seluruh butir (c). Tagihan pertama terbit H-7 dan nominalnya beku di
    // sana; pilihan yang baru tiba di hari yang sama bukan pilihan — tenant
    // memilih Harga Adaptif lalu tetap menerima tagihan harga penuh.
    get('/owner/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('subscription.trial_choice.first_invoice_issued', false)
        ->where('subscription.trial_choice.trial_ends_at', now()->addDays(14)->toDateString())
        ->where('subscription.trial_choice.first_invoice_at', now()->addDays(7)->toDateString())
    );
});

test('sesudah tagihan pertama terbit, tawarannya tetap tapi keadaannya berubah', function () {
    seedTrialChoiceLadder();
    trialChoiceTarget();
    ['owner' => $owner] = trialChoiceContext(daysUntilTrialEnds: 7);

    actingAs($owner);

    // Tidak disembunyikan: pindah jalur tetap boleh, hanya berlakunya pada
    // tagihan BERIKUTNYA. Tenant yang mengira keringanannya berlaku bulan ini
    // akan membaca tagihannya sebagai kesalahan sistem.
    get('/owner/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('subscription.trial_choice.first_invoice_issued', true)
        ->where('subscription.trial_choice.adaptive.eligible', true)
    );
});

test('tenant di luar masa coba tidak ditanyai apa-apa', function () {
    seedTrialChoiceLadder();
    trialChoiceTarget();
    ['owner' => $owner] = trialChoiceContext(daysUntilTrialEnds: 3, status: Tenant::STATUS_ACTIVE);

    actingAs($owner);

    get('/owner/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('subscription.trial_choice', null)
    );
});

// --- Jalur mana yang boleh dijawab ---

test('kedua jalur disodorkan berdampingan, masing-masing berikut angkanya', function () {
    seedTrialChoiceLadder();
    trialChoiceTarget();
    ['tenant' => $tenant, 'owner' => $owner] = trialChoiceContext(daysUntilTrialEnds: 10);
    recordTrialChoiceSale($tenant, $owner, 1_500_000);

    actingAs($owner);

    get('/owner/dashboard')->assertInertia(fn (Assert $page) => $page
        // Jalur yang berlaku sendiri bila tenant diam. Diam tetap sebuah
        // pilihan, dan tenant berhak tahu isinya sebelum mengambilnya.
        ->where('subscription.trial_choice.fixed.name', 'Premium 1')
        ->where('subscription.trial_choice.fixed.base_price', 100_000)
        ->where('subscription.trial_choice.adaptive.eligible', true)
        ->where('subscription.trial_choice.adaptive.estimate.price', 10_000)
    );
});

test('tenant beromzet di atas ambang melihat satu jalur, dan sebabnya', function () {
    seedTrialChoiceLadder();
    trialChoiceTarget();
    ['tenant' => $tenant, 'owner' => $owner] = trialChoiceContext(daysUntilTrialEnds: 10);
    recordTrialChoiceSale($tenant, $owner, 60_000_000);

    actingAs($owner);

    // Kartunya TIDAK hilang. Tenant yang tidak layak justru paling perlu tahu
    // bahwa masa coba gratisnya berujung tagihan Rp 100.000 — menyembunyikan
    // kartunya berarti kabar itu tiba lewat tagihan.
    get('/owner/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('subscription.trial_choice.fixed.base_price', 100_000)
        ->where('subscription.trial_choice.adaptive.eligible', false)
        ->where('subscription.trial_choice.adaptive.reason', 'above_ceiling')
        ->where('subscription.trial_choice.adaptive.ceiling', 50_000_000)
        // Perkiraan tarif untuk jalur yang tertutup bukan informasi, melainkan
        // tawaran yang akan ditolak — dan ia menyeret satu agregat penjualan ke
        // tiap pemuatan dashboard tanpa ada yang membacanya.
        ->where('subscription.trial_choice.adaptive.estimate', null)
    );
});

test('tanpa paket tujuan, tidak ada yang disodorkan sama sekali', function () {
    seedTrialChoiceLadder();
    ['owner' => $owner] = trialChoiceContext(daysUntilTrialEnds: 10);

    actingAs($owner);

    // Menyebut "masa coba Anda akan habis" tanpa bisa menyebutkan menjadi apa
    // hanya menakuti tanpa memberi jalan. Salah setelnya ditagih ke pemilik
    // SaaS lewat `graduateExpiredTrials()`, bukan ke tenant.
    get('/owner/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('subscription.trial_choice', null)
    );
});

// --- Apa yang dibandingkan ---

test('perkiraan adaptif dibandingkan dengan tarif setelah masa coba, bukan dengan Rp 0', function () {
    seedTrialChoiceLadder();
    trialChoiceTarget();
    ['tenant' => $tenant, 'owner' => $owner] = trialChoiceContext(daysUntilTrialEnds: 10);
    recordTrialChoiceSale($tenant, $owner, 1_500_000);

    actingAs($owner);

    // Tenant masa coba membayar Rp 0. Dibandingkan terhadap gratis, SETIAP
    // tawaran keringanan terbaca "tidak lebih murah" — tepat pada satu-satunya
    // kelompok yang sedang diminta memilih jalurnya.
    get('/owner/dashboard')->assertInertia(fn (Assert $page) => $page
        ->where('subscription.trial_choice.adaptive.estimate.current_price', 100_000)
        ->where('subscription.trial_choice.adaptive.estimate.is_cheaper', true)
    );
});

test('halaman langganan memakai pembanding yang sama dengan kartu dashboard', function () {
    seedTrialChoiceLadder();
    trialChoiceTarget();
    ['tenant' => $tenant, 'owner' => $owner] = trialChoiceContext(daysUntilTrialEnds: 10);
    recordTrialChoiceSale($tenant, $owner, 1_500_000);

    actingAs($owner);

    // Dua layar yang menjawab pertanyaan yang sama dengan angka yang berbeda
    // membuat tenant bertanya mana yang benar — dan yang satu ini adalah
    // halaman yang dituju tautan di kartu dashboard.
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('subsidy.estimate.current_price', 100_000)
        ->where('subsidy.estimate.is_cheaper', true)
    );
});
