<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Gerbang langganan pada permukaan self-order — `[BL-020]`.
 *
 * Entri backlog itu semula dibaca dari susunan middleware, bukan dari
 * percobaan: `routes/api.php` menaruh self-order di balik `auth:sanctum` saja,
 * sementara grup mobile memakai `auth:sanctum` + `tenant.api` — dan
 * `tenant.api` inilah yang membawa `EnsureSubscriptionActive`. Berkas ini
 * membuktikan dugaan itu benar, lalu menjaga perbaikannya.
 *
 * Payload `POST /api/v1/orders` sengaja dikosongkan. Gerbang langganan berjalan
 * SEBELUM controller, jadi kalau ia terpasang jawabannya 403 dan Xendit tidak
 * pernah disentuh; kalau ia absen, permintaan lolos sampai ke validasi dan
 * jawabannya 422. Dua angka itu yang membedakan "tertutup" dari "bocor", tanpa
 * satu pun panggilan keluar dari suite.
 */

/**
 * @return array{tenant: Tenant, machine: User}
 */
function makeSelfOrderContext(string $status): array
{
    $tenant = Tenant::factory()->create(['status' => $status]);

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'current_period_end' => now()->subDay()->toDateString(),
        'trial_ends_at' => now()->subDay(),
    ]);

    // Pemilik token self-order: akun tenant yang tokennya dipegang n8n.
    $machine = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);

    return ['tenant' => $tenant, 'machine' => $machine];
}

// ── Yang seharusnya terjadi ────────────────────────────────────────────────

test('pesanan self-order ditolak saat tenant ditangguhkan', function () {
    ['machine' => $machine] = makeSelfOrderContext(Tenant::STATUS_SUSPENDED);

    Sanctum::actingAs($machine);

    // Tenant yang ditangguhkan tidak boleh menerima pesanan lewat pintu mana pun.
    $this->postJson('/api/v1/orders', [])->assertStatus(403);
});

test('pesanan self-order ditolak saat masa tenggang', function () {
    ['machine' => $machine] = makeSelfOrderContext(Tenant::STATUS_GRACE);

    Sanctum::actingAs($machine);

    // `grace` berarti hanya-baca: data lama tetap terbuka, transaksi baru tidak.
    $this->postJson('/api/v1/orders', [])->assertStatus(403);
});

test('katalog self-order tertutup saat tenant ditangguhkan', function () {
    ['machine' => $machine] = makeSelfOrderContext(Tenant::STATUS_SUSPENDED);

    Sanctum::actingAs($machine);

    $this->getJson('/api/v1/products')->assertStatus(403);
});

// ── Pembanding: gerbangnya tidak boleh menutup yang sah ────────────────────

test('katalog self-order tetap terbaca saat masa tenggang', function () {
    ['tenant' => $tenant, 'machine' => $machine] = makeSelfOrderContext(Tenant::STATUS_GRACE);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    ProductVariant::factory()->create(['product_id' => $product->id]);

    Sanctum::actingAs($machine);

    // Membaca katalog adalah method aman — `grace` tidak menutupnya.
    $this->getJson('/api/v1/products')->assertStatus(200);
});

test('tenant aktif tetap bisa mengirim pesanan self-order', function () {
    ['machine' => $machine] = makeSelfOrderContext(Tenant::STATUS_ACTIVE);

    Sanctum::actingAs($machine);

    // 422 dari validasi, bukan 403: yang diuji di sini justru bahwa gerbangnya
    // TIDAK ikut menutup tenant yang langganannya berlaku.
    $this->postJson('/api/v1/orders', [])->assertStatus(422);
});

test('token mesin tidak dituntut verifikasi surel', function () {
    ['machine' => $machine] = makeSelfOrderContext(Tenant::STATUS_ACTIVE);

    // Inilah alasan self-order memakai alias 'subscription' dan bukan grup
    // 'tenant.api': token n8n tidak punya kotak masuk untuk membuktikan apa
    // pun. Menempelkan grup itu akan mematikan seluruh jalur self-order pada
    // tenant yang surelnya belum terverifikasi.
    $machine->forceFill(['email_verified_at' => null])->save();

    Sanctum::actingAs($machine);

    $this->postJson('/api/v1/orders', [])->assertStatus(422);
});

// ── Batas dengan [BL-019]: memajukan pesanan BUKAN layanan baru ────────────

test('memajukan pesanan tetap bisa saat masa tenggang', function () {
    ['tenant' => $tenant, 'machine' => $machine] = makeSelfOrderContext(Tenant::STATUS_GRACE);

    $transaction = Transaction::factory()->selfOrder()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $machine->id,
    ]);

    Sanctum::actingAs($machine);

    // Pesanan ini uangnya sudah diterima. Menutupnya berarti dapur berhenti di
    // tengah antrean pada hari langganan lewat jatuh tempo — dan middleware-nya
    // sendiri menulis bahwa menyandera data pelanggan bukan alat penagihan yang
    // sah. Jadi rute ini sengaja di luar gerbang.
    $this->patchJson("/api/v1/orders/{$transaction->id}/fulfillment")
        ->assertStatus(200)
        ->assertJsonPath('fulfillment_status', Transaction::FULFILLMENT_PREPARING);
});

test('memajukan pesanan tetap bisa saat tenant ditangguhkan', function () {
    ['tenant' => $tenant, 'machine' => $machine] = makeSelfOrderContext(Tenant::STATUS_SUSPENDED);

    $transaction = Transaction::factory()->selfOrder()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $machine->id,
    ]);

    Sanctum::actingAs($machine);

    // Alasannya sama: kewajiban yang sudah dibayar tetap harus bisa
    // diselesaikan, sekalipun tidak ada pesanan baru yang boleh masuk.
    $this->patchJson("/api/v1/orders/{$transaction->id}/fulfillment")
        ->assertStatus(200);
});
