<?php

use App\Models\Plan;
use App\Models\PlatformUser;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

/**
 * `[BL-071]` — masa gratis berakhir dengan perpindahan ke paket berbayar sejak
 * `[BL-052]`, dan sampai entri ini halaman daftar tidak menyebutnya sama sekali.
 *
 * Yang dijaga di sini bukan kalimatnya, melainkan **asalnya**: panjang masa
 * gratis tinggal di config dan paket tujuannya penanda di `plans` yang bisa
 * dipindah pemilik SaaS dari panel. Keduanya sengaja dibuat begitu supaya tidak
 * menuntut deploy — halaman daftar yang menyalin angkanya akan berbohong pada
 * hari salah satunya digeser.
 */
beforeEach(function () {
    Plan::query()->delete();
    Plan::factory()->create(['slug' => Plan::SLUG_DEFAULT, 'name' => 'Free', 'base_price' => 0]);
});

test('halaman daftar menyebut lama masa gratis dan paket tujuannya', function () {
    config(['subscription.trial_months' => 2, 'subscription.invoice_lead_days' => 7]);
    Plan::factory()->create([
        'slug' => 'paid-1',
        'name' => 'Mandiri',
        'base_price' => 100_000,
        'is_post_trial_target' => true,
    ]);

    get('/register')->assertStatus(200)->assertInertia(fn (Assert $page) => $page
        ->component('Auth/Register')
        ->where('trial.months', 2)
        ->where('trial.invoice_lead_days', 7)
        ->where('trial.post_trial_plan.name', 'Mandiri')
        ->where('trial.post_trial_plan.price', 100_000));
});

test('mengubah penanda paket tujuan dari panel ikut mengubah yang disebut halaman daftar', function () {
    // Inti butir (c): tanpa test ini, penanda yang bisa dipindah dari panel akan
    // kembali jadi teks mati pada penulisan ulang berikutnya.
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Mandiri', 'base_price' => 100_000, 'is_post_trial_target' => true]);
    $lain = Plan::factory()->create(['slug' => 'paid-2', 'name' => 'Berkembang', 'base_price' => 150_000]);

    get('/register')->assertInertia(fn (Assert $page) => $page->where('trial.post_trial_plan.name', 'Mandiri'));

    actingAs(PlatformUser::factory()->withAllModules()->create(), 'platform');

    put("/platform/plans/{$lain->id}", [
        'name' => $lain->name,
        'base_price' => 150_000,
        'included_seats' => 1,
        'extra_seat_price' => 5_000,
        'ai_daily_limit' => null,
        'is_adaptive_fallback' => false,
        'is_post_trial_target' => true,
    ])->assertSessionHasNoErrors();

    // `actingAs(..., 'platform')` memindahkan guard BAWAAN ke `platform`, dan
    // middleware `guest` di `/register` membaca guard bawaan — tanpa
    // mengembalikannya, permintaan berikutnya dialihkan alih-alih dijawab.
    auth()->shouldUse('web');

    get('/register')->assertInertia(fn (Assert $page) => $page
        ->where('trial.post_trial_plan.name', 'Berkembang')
        ->where('trial.post_trial_plan.price', 150_000));
});

test('tanpa paket tujuan halaman daftar tetap terbuka dan tidak menjanjikan paket apa pun', function () {
    // Panel bisa saja belum menandai paket mana pun. Yang tidak boleh terjadi:
    // kalimat setengah jadi, atau janji gratis selamanya.
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Mandiri', 'base_price' => 100_000]);

    get('/register')->assertStatus(200)->assertInertia(fn (Assert $page) => $page
        ->where('trial.months', config('subscription.trial_months'))
        ->where('trial.post_trial_plan', null));
});

test('paket tujuan yang menunjuk paket gratis diperlakukan sebagai ketiadaan penunjukan', function () {
    // Persis yang ditolak `SubscriptionService::graduateExpiredTrials()`:
    // perpindahannya tidak akan terjadi, jadi tidak ada yang boleh diumumkan.
    Plan::where('slug', Plan::SLUG_DEFAULT)->update(['is_post_trial_target' => true]);

    get('/register')->assertInertia(fn (Assert $page) => $page->where('trial.post_trial_plan', null));
});

test('lama masa gratis di halaman daftar mengikuti config, bukan angka yang diketik', function () {
    config(['subscription.trial_months' => 3]);

    get('/register')->assertInertia(fn (Assert $page) => $page->where('trial.months', 3));
});
