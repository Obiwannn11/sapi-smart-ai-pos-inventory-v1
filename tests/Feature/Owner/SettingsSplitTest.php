<?php

use App\Models\Tenant;
use App\Models\User;

/**
 * Mengunci pemecahan halaman Pengaturan (`[BL-039]`).
 *
 * Yang diuji di sini bukan bahwa tiap endpoint MENYIMPAN field-nya — itu sudah
 * dijaga tes masing-masing fitur. Yang dijaga di sini adalah kebalikannya:
 * bahwa satu endpoint TIDAK BISA menulis field milik endpoint lain. Tanpa tes
 * ini, seseorang bisa mengembalikan satu `update()` gemuk tanpa satu pun tes
 * berubah warna, dan seluruh alasan entri ini dikerjakan hilang diam-diam.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'business_type' => 'kuliner',
        'kitchen_queue_enabled' => false,
        'ai_api_key' => 'sk-kunci-lama',
    ]);
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->actingAs($this->owner);
});

test('halaman Profil tidak bisa menulis kredensial maupun kapabilitas', function () {
    $this->patch(route('owner.settings.update'), [
        'phone' => '021-1234567',
        'ai_api_key' => 'sk-kunci-selundupan',
        'ai_provider' => 'anthropic',
        'kitchen_queue_enabled' => true,
    ])->assertSessionHasNoErrors();

    $this->tenant->refresh();

    expect($this->tenant->phone)->toBe('021-1234567')
        ->and($this->tenant->ai_api_key)->toBe('sk-kunci-lama')
        ->and($this->tenant->ai_provider)->toBeNull()
        ->and($this->tenant->kitchen_queue_enabled)->toBeFalse();
});

test('halaman Cara Kerja tidak bisa menulis kredensial maupun dasar tarif', function () {
    $this->patch(route('owner.settings.operations.update'), [
        'kitchen_queue_enabled' => true,
        'business_type' => 'retail',
        'ai_api_key' => 'sk-kunci-selundupan',
    ])->assertSessionHasNoErrors();

    $this->tenant->refresh();

    expect($this->tenant->kitchen_queue_enabled)->toBeTrue()
        ->and($this->tenant->business_type)->toBe('kuliner')
        ->and($this->tenant->ai_api_key)->toBe('sk-kunci-lama');
});

test('halaman Integrasi tidak bisa menulis kapabilitas maupun dasar tarif', function () {
    $this->patch(route('owner.settings.integrations.update'), [
        'ai_provider' => 'anthropic',
        'kitchen_queue_enabled' => true,
        'business_type' => 'retail',
    ])->assertSessionHasNoErrors();

    $this->tenant->refresh();

    expect($this->tenant->ai_provider)->toBe('anthropic')
        ->and($this->tenant->kitchen_queue_enabled)->toBeFalse()
        ->and($this->tenant->business_type)->toBe('kuliner');
});

test('jenis usaha hanya tampil di halaman yang memuat keterangan dampak tarifnya', function () {
    // Kolom yang menggerakkan uang tidak boleh muncul di dua tempat: yang
    // kedua hampir pasti tidak akan membawa kalimat peringatannya.
    $this->get(route('owner.settings.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Owner/Settings/Index')
            ->where('tenant.business_type', 'kuliner')
            ->has('businessTypes')
        );

    $this->get(route('owner.settings.operations.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Owner/Settings/Operations')
            ->missing('tenant')
            ->missing('businessTypes')
        );
});

test('halaman Profil dan Cara Kerja tidak membawa kredensial apa pun', function () {
    $this->get(route('owner.settings.index'))
        ->assertInertia(fn ($page) => $page
            ->missing('tenant.ai_api_key')
            ->missing('tenant.ai_provider')
            ->missing('mcp')
        );

    $this->get(route('owner.settings.operations.index'))
        ->assertInertia(fn ($page) => $page->missing('mcp'));
});

test('kasir tidak bisa membuka satu pun halaman pengaturan', function () {
    $cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $this->actingAs($cashier);

    $this->get(route('owner.settings.index'))->assertForbidden();
    $this->get(route('owner.settings.operations.index'))->assertForbidden();
    $this->get(route('owner.settings.integrations.index'))->assertForbidden();
});
