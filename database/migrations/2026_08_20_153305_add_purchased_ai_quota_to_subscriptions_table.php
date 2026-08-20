<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kuota AI tambahan jadi hak yang dibeli per langganan — `[BL-069]`, keputusan
 * pemilik 2026-08-19.
 *
 * Sampai sekarang jatah analisis harian sepenuhnya ditentukan dari luar
 * langganan: batas paket, lalu kebijakan bawaan platform, lalu `config/ai.php`.
 * Tenant yang jatahnya kurang hanya punya satu jalan keluar — naik paket — dan
 * itu langkah Rp 50.000 untuk kebutuhan yang kadang cuma sebesar lima analisis.
 *
 * Ketiga kolomnya sengaja mencerminkan seat satu lawan satu
 * (`purchased_extra_seats` / `scheduled_extra_seats` / `seat_release_at`), dan
 * kemiripan itu bukan kemalasan: pola seat sudah dipakai, sudah diuji, dan
 * sudah punya perintah terjadwal yang memberlakukan pelepasannya. Menemukan
 * pola kedua untuk masalah yang sama berarti dua alur beli, dua alur lepas, dan
 * dua tempat yang bisa berselisih tentang apa yang berhak ditagih.
 *
 *   - `purchased_ai_blocks` — hak yang berlaku SEKARANG, dasar tagihan, dipakai
 *     atau tidak. Disimpan sebagai jumlah BLOK, bukan jumlah analisis: yang
 *     dibeli tenant adalah satuan berharga tetap (`subscription.ai_quota`), dan
 *     menyimpan hasil kalinya berarti mengubah `block_size` diam-diam mengubah
 *     arti angka yang sudah tersimpan.
 *   - `scheduled_ai_blocks` + `ai_quota_release_at` — pelepasan yang sudah
 *     diminta tapi belum berlaku. Disimpan sebagai TARGET, bukan selisih,
 *     dengan alasan yang sama seperti seat: permintaan kedua sebelum yang
 *     pertama berlaku cukup menimpa targetnya.
 *
 * Tidak ada backfill, dan itu bedanya dari migrasi seat. Seat sudah pernah
 * dijual sebelum kolomnya ada, sehingga haknya harus disimpulkan mundur dari
 * `seats − included_seats`. Kuota AI belum pernah dijual kepada siapa pun:
 * tabel yang lahir nol berarti tidak ada satu pun tenant yang jatahnya berubah
 * oleh pemasangan ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedSmallInteger('purchased_ai_blocks')->default(0)->after('seat_release_at');
            $table->unsignedSmallInteger('scheduled_ai_blocks')->nullable()->after('purchased_ai_blocks');
            $table->date('ai_quota_release_at')->nullable()->after('scheduled_ai_blocks');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['purchased_ai_blocks', 'scheduled_ai_blocks', 'ai_quota_release_at']);
        });
    }
};
