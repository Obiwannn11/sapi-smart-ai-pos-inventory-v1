<?php

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FulfillmentService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['kitchen_queue_enabled' => true]);
    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);
    $this->service = app(FulfillmentService::class);
});

/**
 * Kartu papan dengan posisi yang ditentukan, supaya urutannya bisa diuji.
 */
function boardCard(string $status = Transaction::FULFILLMENT_WAITING, ?int $sortIndex = null): Transaction
{
    return Transaction::factory()->selfOrder()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->cashier->id,
        'status' => Transaction::STATUS_COMPLETED,
        'fulfillment_status' => $status,
        'sort_index' => $sortIndex ?? now()->getTimestampMs(),
    ]);
}

/** @return list<int> urutan id kartu di papan */
function boardOrder(): array
{
    return Transaction::withoutGlobalScopes()
        ->where('tenant_id', test()->tenant->id)
        ->whereNotNull('fulfillment_status')
        ->orderBy('sort_index')
        ->orderBy('id')
        ->pluck('id')
        ->all();
}

// ── Transisi ───────────────────────────────────────────────────────────────

test('advance menaikkan status satu langkah dan menstempel waktunya', function () {
    $card = boardCard(Transaction::FULFILLMENT_WAITING);

    $this->service->advance($card, Transaction::FULFILLMENT_WAITING);

    expect($card->fresh()->fulfillment_status)->toBe(Transaction::FULFILLMENT_PREPARING)
        ->and($card->fresh()->preparing_at)->not->toBeNull()
        ->and($card->fresh()->ready_at)->toBeNull();

    $this->service->advance($card->fresh(), Transaction::FULFILLMENT_PREPARING);

    expect($card->fresh()->fulfillment_status)->toBe(Transaction::FULFILLMENT_READY)
        ->and($card->fresh()->ready_at)->not->toBeNull();
});

test('advance dengan status yang tidak cocok ditolak tanpa mengubah apa pun', function () {
    $card = boardCard(Transaction::FULFILLMENT_PREPARING);

    // Inti proteksi balapan: kartu di layar masih menampilkan "Mulai masak"
    // padahal orang lain sudah memajukannya.
    expect(fn () => $this->service->advance($card, Transaction::FULFILLMENT_WAITING))
        ->toThrow(Exception::class, 'Status pesanan sudah berubah');

    expect($card->fresh()->fulfillment_status)->toBe(Transaction::FULFILLMENT_PREPARING);
});

test('advance dari done ditolak', function () {
    $card = boardCard(Transaction::FULFILLMENT_DONE);

    expect(fn () => $this->service->advance($card, Transaction::FULFILLMENT_DONE))
        ->toThrow(Exception::class, 'Pesanan sudah selesai.');
});

test('advance menolak transaksi tanpa fulfillment tracking', function () {
    $card = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'fulfillment_status' => null,
    ]);

    expect(fn () => $this->service->advance($card, Transaction::FULFILLMENT_WAITING))
        ->toThrow(Exception::class, 'tidak punya fulfillment tracking');
});

test('stempel waktu tidak ditimpa saat status dilewati ulang', function () {
    $card = boardCard(Transaction::FULFILLMENT_WAITING);

    $this->service->advance($card, Transaction::FULFILLMENT_WAITING);
    $firstStamp = $card->fresh()->preparing_at;

    // Mundurkan manual lalu majukan lagi — jejak kapan masakan benar-benar
    // dimulai tidak boleh hilang.
    $card->fresh()->update(['fulfillment_status' => Transaction::FULFILLMENT_WAITING]);
    $this->service->advance($card->fresh(), Transaction::FULFILLMENT_WAITING);

    expect($card->fresh()->preparing_at->toIso8601String())->toBe($firstStamp->toIso8601String());
});

// ── Urutan ─────────────────────────────────────────────────────────────────

test('moveToTop menaruh kartu di puncak dalam satu panggilan', function () {
    $first = boardCard(sortIndex: 1000);
    $second = boardCard(sortIndex: 2000);
    $last = boardCard(sortIndex: 3000);

    $this->service->moveToTop($last);

    expect(boardOrder())->toBe([$last->id, $first->id, $second->id]);
});

test('moveUp menukar posisi dengan kartu di atasnya', function () {
    $first = boardCard(sortIndex: 1000);
    $second = boardCard(sortIndex: 2000);

    $this->service->moveUp($second);

    expect(boardOrder())->toBe([$second->id, $first->id]);
});

test('moveDown menukar posisi dengan kartu di bawahnya', function () {
    $first = boardCard(sortIndex: 1000);
    $second = boardCard(sortIndex: 2000);

    $this->service->moveDown($first);

    expect(boardOrder())->toBe([$second->id, $first->id]);
});

test('kartu teratas yang digeser naik adalah no-op, bukan error', function () {
    $first = boardCard(sortIndex: 1000);
    $second = boardCard(sortIndex: 2000);

    $this->service->moveUp($first);

    expect(boardOrder())->toBe([$first->id, $second->id]);
});

test('kartu terbawah yang digeser turun adalah no-op, bukan error', function () {
    $first = boardCard(sortIndex: 1000);
    $second = boardCard(sortIndex: 2000);

    $this->service->moveDown($second);

    expect(boardOrder())->toBe([$first->id, $second->id]);
});

test('dua kartu dengan sort_index identik tetap bisa saling melewati', function () {
    // Mengunci pemecah seri `id`. Dua self-order bisa jatuh di milidetik yang
    // sama; tanpa pemecah seri, perbandingan strict melewati kartu kembar dan
    // tombolnya terasa rusak bagi pemakainya.
    $a = boardCard(sortIndex: 5000);
    $b = boardCard(sortIndex: 5000);

    expect(boardOrder())->toBe([$a->id, $b->id]);

    $this->service->moveUp($b);

    expect(boardOrder())->toBe([$b->id, $a->id]);
});

// ── Batas papan ────────────────────────────────────────────────────────────

test('pengurutan tidak menyentuh kartu tenant lain', function () {
    $mine = boardCard(sortIndex: 2000);

    $otherTenant = Tenant::factory()->create(['kitchen_queue_enabled' => true]);
    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'cashier']);
    $theirs = Transaction::factory()->selfOrder()->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherUser->id,
        'sort_index' => 1000,
    ]);

    $this->service->moveToTop($mine);

    // Kartu tenant lain tidak boleh ikut jadi pembanding puncak.
    expect($mine->fresh()->sort_index)->toBe(1999)
        ->and($theirs->fresh()->sort_index)->toBe(1000);
});

test('kartu kemarin tidak ikut diperhitungkan saat mendahulukan', function () {
    $yesterday = Transaction::factory()->selfOrder()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'sort_index' => 10,
        'occurred_at' => now()->subDay(),
    ]);

    $today = boardCard(sortIndex: 9000);

    $this->service->moveToTop($today);

    // Kalau kartu kemarin ikut terhitung, sort_index barunya akan 9 dan papan
    // hari ini mewarisi urutan hari sebelumnya.
    expect($today->fresh()->sort_index)->toBe(8999)
        ->and($yesterday->fresh()->sort_index)->toBe(10);
});
