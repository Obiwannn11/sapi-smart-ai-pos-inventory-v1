<?php

use App\Models\CashDrawer;
use App\Models\CashDrawerMovement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Foto struk untuk mutasi kas (`[BL-093]`).
 *
 * `[BL-087]` sudah mewajibkan ALASAN tertulis; itu yang membedakan pencatatan
 * ini dari uang yang hilang begitu saja. Foto menambahkan lapis berikutnya:
 * bukti yang bisa DIPERIKSA, bukan sekadar dibaca.
 *
 * Dua hal yang dijaga paling keras di sini, karena keduanya gagal dalam diam:
 * fotonya tetap OPSIONAL — mewajibkannya akan menghentikan pencatatan yang
 * struknya memang tidak ada, dan yang hilang bukan fotonya melainkan seluruh
 * keterangan uangnya — dan foto satu toko tidak pernah bisa dibuka toko lain.
 */
beforeEach(function () {
    Storage::fake('local');

    $this->tenant = Tenant::factory()->create(['cash_payout_approval_threshold' => 50000]);
    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->drawer = CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'opening_amount' => 500_000,
        'opened_at' => now()->subHours(3),
        'closed_at' => null,
    ]);
});

/**
 * @return array<string, mixed>
 */
function payoutWith(array $overrides = []): array
{
    return array_merge([
        'type' => CashDrawerMovement::TYPE_PAYOUT,
        'amount' => 30_000,
        'reason' => 'Beli galon air',
    ], $overrides);
}

// --- Melampirkan foto ---

test('kasir bisa melampirkan foto struk saat mencatat uang keluar', function () {
    actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', payoutWith([
            'proof' => UploadedFile::fake()->image('struk.jpg', 1200, 1600),
        ]))
        ->assertRedirect();

    $movement = CashDrawerMovement::first();

    expect($movement->proof_path)->not->toBeNull()
        // Direktori sendiri, terpisah per tenant — sama seperti bukti bayar.
        ->and($movement->proof_path)->toStartWith("cash-movement-proofs/{$this->tenant->id}/");

    Storage::disk('local')->assertExists($movement->proof_path);
    // Pratinjaunya ikut lahir; daftar persetujuan pemilik memuat thumbnail,
    // dan tanpa rendition kedua tiap baris mengunduh foto penuh.
    Storage::disk('local')->assertExists(str_replace('.webp', '_thumb.webp', $movement->proof_path));
});

test('mencatat tanpa foto tetap berhasil, dan itu intinya', function () {
    // Sebagian pengeluaran memang tidak berstruk — parkir, tukar receh.
    // Mewajibkan foto akan mengulang kesalahan yang alasan-wajib hindari dari
    // sisi lain: kasir yang tidak bisa mencatat tetap mengeluarkan uangnya.
    actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', payoutWith())
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(CashDrawerMovement::first()->proof_path)->toBeNull();
});

test('setoran masuk juga boleh berfoto', function () {
    // Bukan cuma pengeluaran: setoran uang kecil dari pemilik pun punya bukti
    // yang layak dilampirkan.
    actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', payoutWith([
            'type' => CashDrawerMovement::TYPE_DEPOSIT,
            'proof' => UploadedFile::fake()->image('setoran.png'),
        ]))
        ->assertRedirect();

    expect(CashDrawerMovement::first()->proof_path)->not->toBeNull();
});

test('berkas yang bukan gambar ditolak', function () {
    // PDF sengaja tidak diterima, sama seperti bukti bayar kasir: ini foto yang
    // diambil di tempat, dan berkas yang tidak bisa dipratinjau berarti bukti
    // yang tidak pernah bisa diperiksa siapa pun.
    actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', payoutWith([
            'proof' => UploadedFile::fake()->create('struk.pdf', 100, 'application/pdf'),
        ]))
        ->assertSessionHasErrors('proof');

    expect(CashDrawerMovement::count())->toBe(0);
});

test('nominal yang ditolak validasi tidak meninggalkan berkas di disk', function () {
    // Berkasnya baru disimpan SESUDAH validasi lolos. Kalau urutannya
    // terbalik, tiap salah ketik nominal meninggalkan sampah permanen di disk
    // — dan tidak ada perintah pembersih untuk direktori ini, karena memang
    // tidak seharusnya ada berkas terlantar di sini.
    actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', payoutWith([
            'amount' => 0,
            'proof' => UploadedFile::fake()->image('struk.jpg'),
        ]))
        ->assertSessionHasErrors('amount');

    expect(Storage::disk('local')->allFiles("cash-movement-proofs/{$this->tenant->id}"))->toBeEmpty();
});

// --- Siapa yang boleh membukanya ---

test('pemilik bisa membuka foto sebelum memutuskan', function () {
    actingAs($this->cashier)->post('/cashier/cash-drawer/movements', payoutWith([
        'amount' => 200_000,
        'proof' => UploadedFile::fake()->image('struk.jpg'),
    ]));

    $movement = CashDrawerMovement::first();

    // Barisnya menunggu persetujuan, dan fotonya satu-satunya bagian yang bisa
    // diperiksa — nominal dan alasan sama-sama ucapan orang yang mencatatnya.
    expect($movement->status)->toBe(CashDrawerMovement::STATUS_PENDING);

    actingAs($this->owner)
        ->get("/media/bukti-kas/{$movement->id}/thumb")
        ->assertOk();
});

test('kasir yang mencatatnya bisa membukanya kembali', function () {
    actingAs($this->cashier)->post('/cashier/cash-drawer/movements', payoutWith([
        'proof' => UploadedFile::fake()->image('struk.jpg'),
    ]));

    actingAs($this->cashier)
        ->get('/media/bukti-kas/'.CashDrawerMovement::first()->id.'/full')
        ->assertOk();
});

test('foto toko lain tidak pernah bisa dibuka', function () {
    actingAs($this->cashier)->post('/cashier/cash-drawer/movements', payoutWith([
        'proof' => UploadedFile::fake()->image('struk.jpg'),
    ]));

    $movement = CashDrawerMovement::first();

    $lain = Tenant::factory()->create();
    $orangLain = User::factory()->create(['tenant_id' => $lain->id, 'role' => 'owner']);

    // 404, bukan 403: jawaban yang membedakan "bukan milikmu" dari "tidak ada"
    // mengubah URL ini jadi alat menghitung mutasi kas toko sebelah.
    actingAs($orangLain)
        ->get("/media/bukti-kas/{$movement->id}/thumb")
        ->assertNotFound();
});

test('mutasi tanpa foto menjawab 404, bukan berkas kosong', function () {
    actingAs($this->cashier)->post('/cashier/cash-drawer/movements', payoutWith());

    actingAs($this->owner)
        ->get('/media/bukti-kas/'.CashDrawerMovement::first()->id.'/full')
        ->assertNotFound();
});

test('tamu tidak bisa membuka foto siapa pun', function () {
    actingAs($this->cashier)->post('/cashier/cash-drawer/movements', payoutWith([
        'proof' => UploadedFile::fake()->image('struk.jpg'),
    ]));

    auth()->logout();

    get('/media/bukti-kas/'.CashDrawerMovement::first()->id.'/thumb')
        ->assertRedirect('/login');
});

// --- Sesudah keputusan pemilik ---

test('menolak mutasi tidak menghapus fotonya', function () {
    actingAs($this->cashier)->post('/cashier/cash-drawer/movements', payoutWith([
        'amount' => 200_000,
        'proof' => UploadedFile::fake()->image('struk.jpg'),
    ]));

    $movement = CashDrawerMovement::first();
    $path = $movement->proof_path;

    actingAs($this->owner)->post("/owner/cash-drawer-movements/{$movement->id}/reject");

    // Justru pada baris yang DITOLAK fotonya paling berguna: ia bukti dari
    // klaim yang tidak diterima, dan itu yang akan ditanyakan kembali nanti.
    expect($movement->fresh()->status)->toBe(CashDrawerMovement::STATUS_REJECTED)
        ->and($movement->fresh()->proof_path)->toBe($path);

    Storage::disk('local')->assertExists($path);
});
