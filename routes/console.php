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
//
// **Wajib berjalan SESUDAH `subscriptions:compute-revenue`,** dan itu bukan
// kebetulan urutan baris di berkas ini: penerbit tagihan tinggal di dalam
// `advanceLifecycle()`, dan tarif jalur Adaptif dibaca dari ringkasan omzet
// bulan sebelumnya. Berjalan lebih dulu berarti ringkasan bulan yang baru tutup
// belum ada, dan `MonthlyRevenueResolver` diam-diam jatuh ke bulan sebelumnya
// lagi — tagihan terbit dengan angka dua bulan lalu, tanpa satu pun tanda
// ([BL-080] butir (a)). Jangan geser jam ini tanpa menggeser yang itu.
Schedule::command('subscriptions:advance-lifecycle')->dailyAt('03:30');

// Peringatan percobaan masuk yang menumpuk. Tiap jam, bukan harian: serangan
// yang baru diberitahukan besok pagi sudah kehilangan gunanya sebagai
// peringatan. Jeda antar surat sejenis diatur di config/platform-alerts.php.
Schedule::command('platform:alert-failed-logins')->hourly();

// Omset tenant jalur subsidi, dihitung atas bulan yang baru saja tutup.
// Tanggal 1 pukul 02:40 — cukup lewat dari tengah malam agar transaksi terakhir
// bulan lalu sudah pasti tersimpan, dan lima puluh menit sebelum
// `subscriptions:advance-lifecycle` supaya penerbit tagihan di dalamnya membaca
// ringkasan yang baru ditulis, bukan ringkasan bulan sebelumnya ([BL-080]
// butir (a)). Yang digeser sengaja yang ini, bukan `advance-lifecycle` —
// alasannya berjalan sebelum jam buka warung masih berlaku.
//
// Ongkos pergeseran ini: jarak dari tengah malam menyusut dari empat jam ke
// 2 jam 40 menit, jadi sinkronisasi offline yang tiba lebih larut dari itu
// terlewat. `--period` ada persis untuk itu — hitung ulang bulannya, lalu
// tagihan yang sudah terbit ditinjau manual.
Schedule::command('subscriptions:compute-revenue')->monthlyOn(1, '02:40');

// Pemangkasan ringkasan omset yang lewat retensi. Bulanan sudah cukup — datanya
// pun hanya bertambah sebulan sekali.
Schedule::command('subscriptions:prune-metrics')->monthlyOn(1, '04:30');

// Foto bukti bayar yang diunggah lalu modalnya dibatalkan ([BL-075]). Harian
// dan di jam sepi. Hanya menyentuh berkas TERTUNDA — foto yang sudah melekat
// pada sebuah pembayaran tidak punya kebijakan retensi, dan itu keputusan yang
// sengaja belum diambil.
Schedule::command('payment-proofs:prune-unclaimed')->dailyAt('03:50');

// Tagihan terbuka yang lewat 24 jam jadi kas negatif ([BL-031]).
//
// TIAP JAM, bukan harian, dan itu yang menentukan seberapa benar batas 24 jam
// itu. Sapuan harian berarti sebuah tagihan bisa hidup sampai 48 jam hanya
// karena ia lahir tepat sesudah sapuan lewat — batas yang diputuskan pemilik
// akan jadi "antara satu dan dua hari", dan kalimat di layar kasir yang
// menyebut 24 jam jadi bohong. Ongkosnya satu query berindeks per jam.
Schedule::command('open-bills:expire')->hourly();
