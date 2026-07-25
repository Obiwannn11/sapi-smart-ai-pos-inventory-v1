<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pemangkasan jejak audit platform. Harian dan di jam sepi — tabelnya tumbuh
// terus dan tanpa ini tak ada yang pernah membersihkannya.
Schedule::command('platform:prune-audit-logs')->dailyAt('03:10');

// Perpindahan keadaan langganan. Dijalankan sebelum jam buka warung supaya
// tenant yang jatuh ke masa tenggang mengetahuinya di awal hari, bukan di
// tengah antrean pembeli.
Schedule::command('subscriptions:advance-lifecycle')->dailyAt('03:30');

// Peringatan percobaan masuk yang menumpuk. Tiap jam, bukan harian: serangan
// yang baru diberitahukan besok pagi sudah kehilangan gunanya sebagai
// peringatan. Jeda antar surat sejenis diatur di config/platform-alerts.php.
Schedule::command('platform:alert-failed-logins')->hourly();

// Omset tenant jalur subsidi, dihitung atas bulan yang baru saja tutup.
// Tanggal 1 pukul 04:00 — cukup lewat dari tengah malam agar transaksi terakhir
// bulan lalu sudah pasti tersimpan, termasuk yang masuk dari sinkronisasi
// offline larut malam.
Schedule::command('subscriptions:compute-revenue')->monthlyOn(1, '04:00');

// Pemangkasan ringkasan omset yang lewat retensi. Bulanan sudah cukup — datanya
// pun hanya bertambah sebulan sekali.
Schedule::command('subscriptions:prune-metrics')->monthlyOn(1, '04:30');
