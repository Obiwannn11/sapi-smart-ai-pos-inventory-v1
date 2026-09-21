<?php

use Illuminate\Console\Scheduling\Schedule;

/**
 * Penjaga urutan jadwal `[BL-080]` butir (a).
 *
 * Penerbit tagihan tinggal di dalam `subscriptions:advance-lifecycle`, dan tarif
 * jalur Adaptif dibaca dari ringkasan omzet yang ditulis
 * `subscriptions:compute-revenue`. Bila yang kedua berjalan belakangan, tagihan
 * tanggal 1 terbit memakai ringkasan bulan sebelumnya lagi — bukan error,
 * melainkan angka salah yang diam. Berkas ini yang membuat pergeseran jam
 * berikutnya berbunyi di CI, bukan di tagihan tenant.
 */

/**
 * Menit ke berapa dalam sehari sebuah perintah terjadwal berjalan.
 */
function scheduledMinuteOfDay(string $command): int
{
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains($event->command ?? '', $command));

    expect($events)->toHaveCount(1, "Perintah {$command} harus terdaftar tepat sekali di routes/console.php.");

    [$minute, $hour] = explode(' ', $events->first()->expression);

    return ((int) $hour * 60) + (int) $minute;
}

test('penghitung omzet berjalan sebelum penerbit tagihan pada hari yang sama', function () {
    $revenue = scheduledMinuteOfDay('subscriptions:compute-revenue');
    $lifecycle = scheduledMinuteOfDay('subscriptions:advance-lifecycle');

    expect($revenue)->toBeLessThan(
        $lifecycle,
        'subscriptions:compute-revenue harus berjalan sebelum subscriptions:advance-lifecycle ([BL-080]).'
    );
});

test('penghitung omzet tetap cukup lewat dari tengah malam', function () {
    // Sisi lain dari tukar-urutan: ia tidak boleh digeser begitu dekat ke
    // tengah malam sampai transaksi terakhir bulan lalu belum tersimpan.
    expect(scheduledMinuteOfDay('subscriptions:compute-revenue'))->toBeGreaterThanOrEqual(2 * 60);
});

test('pemangkas ringkasan omzet berjalan sesudah penghitungnya', function () {
    // Memangkas sebelum menghitung akan membuang bulan yang baru saja ditulis.
    expect(scheduledMinuteOfDay('subscriptions:prune-metrics'))
        ->toBeGreaterThan(scheduledMinuteOfDay('subscriptions:compute-revenue'));
});

test('penghitung omzet dan penerbit tagihan sama-sama menyala di tanggal 1', function () {
    $events = collect(app(Schedule::class)->events());

    $revenue = $events->first(fn ($event) => str_contains($event->command ?? '', 'subscriptions:compute-revenue'));
    $lifecycle = $events->first(fn ($event) => str_contains($event->command ?? '', 'subscriptions:advance-lifecycle'));

    // Urutan jam saja tidak cukup bila keduanya tak pernah bertemu di hari yang
    // sama: penghitungnya bulanan tanggal 1, penerbitnya harian.
    [, , $revenueDayOfMonth] = explode(' ', $revenue->expression);
    [, , $lifecycleDayOfMonth] = explode(' ', $lifecycle->expression);

    expect($revenueDayOfMonth)->toBe('1')
        ->and($lifecycleDayOfMonth)->toBe('*');
});
