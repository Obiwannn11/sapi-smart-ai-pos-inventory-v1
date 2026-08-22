<?php

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BusinessClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Hari toko, bukan hari UTC ([BL-082]).
 *
 * Semua pengujian di berkas ini berdiri di satu momen yang sama: 00.30 pagi
 * menurut jam toko. Momen itu dipilih karena di situlah kesalahannya terlihat
 * — pada saat yang sama, kalender UTC masih menunjuk hari SEBELUMNYA. Sebelum
 * perbaikan ini, penjualan jam pertama tiap hari (kafe yang buka pukul 07.00
 * termasuk di dalamnya) masuk ke laporan hari kemarin.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

/** Pukul 00.30 waktu toko pada 22 Agustus — 21 Agustus 16.30 menurut UTC. */
function bekukanDiniHariToko(): Carbon
{
    $moment = Carbon::parse('2026-08-22 00:30:00', BusinessClock::timezone());
    Carbon::setTestNow($moment);

    return $moment;
}

test('aplikasi berjalan di zona bisnis, bukan UTC', function () {
    expect(config('app.timezone'))->toBe('Asia/Makassar')
        ->and(now()->getTimezone()->getName())->toBe(config('app.timezone'));
});

test('hari toko berbeda dari hari UTC pada dini hari', function () {
    $moment = bekukanDiniHariToko();

    expect(BusinessClock::today())->toBe('2026-08-22')
        ->and($moment->copy()->utc()->toDateString())->toBe('2026-08-21');
});

test('periode bulan mengikuti bulan toko', function () {
    // 1 September 00.30 WITA masih 31 Agustus menurut UTC — dan periode inilah
    // yang jadi dasar bracket Harga Adaptif.
    Carbon::setTestNow(Carbon::parse('2026-09-01 00:30:00', BusinessClock::timezone()));

    expect(BusinessClock::period())->toBe('2026-09')
        ->and(BusinessClock::startOfWeek())->toBe('2026-08-31');
});

test('cap waktu dari perangkat dibawa ke zona bisnis sebelum disimpan', function () {
    // Yang dikirim peramban: instan UTC (`toISOString()` selalu berakhiran Z).
    $occurredAt = BusinessClock::fromClient('2026-08-21T16:30:00.000Z');

    expect($occurredAt->toDateString())->toBe('2026-08-22')
        ->and($occurredAt->format('H:i'))->toBe('00:30');

    $transaction = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'occurred_at' => $occurredAt,
    ]);

    // Yang benar-benar tersimpan di kolomnya, bukan hasil cast pembacaan:
    // tanpa perpindahan zona, barisnya tertulis 16.30 dan jatuh ke 21 Agustus.
    $stored = DB::table('transactions')->where('id', $transaction->id)->value('occurred_at');

    expect(substr($stored, 0, 16))->toBe('2026-08-22 00:30');
});

test('kartu hari ini di dashboard menghitung penjualan dini hari', function () {
    bekukanDiniHariToko();

    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => 75000,
        'occurred_at' => BusinessClock::now(),
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.today_revenue', 75000)
            ->where('metrics.today_count', 1)
        );
});

test('laporan harian default ke tanggal toko', function () {
    bekukanDiniHariToko();

    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => 50000,
        'occurred_at' => BusinessClock::now(),
    ]);

    $this->actingAs($this->owner)
        ->get(route('owner.reports.daily'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('date', '2026-08-22')
            ->where('summary.total_revenue', 50000)
        );
});

afterEach(function () {
    Carbon::setTestNow();
});
