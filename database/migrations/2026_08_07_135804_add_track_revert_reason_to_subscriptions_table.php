<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kenapa tenant dijadwalkan kembali ke jalur harga tetap.
 *
 * `track_reverts_at` sudah menyimpan KAPAN, dan sampai sekarang hanya ada satu
 * sebab — tenant mencabut persetujuannya. Sejak `[BL-055]`(e) ada sebab kedua:
 * omzetnya melewati ujung tangga Adaptif. Keduanya berakhir di jalur normal,
 * tapi yang kedua HARUS ikut memindahkan paket ke penampung Adaptif, sementara
 * yang pertama tidak.
 *
 * Bisa saja dibedakan dengan menebak dari keadaan lain — "consent-nya masih
 * aktif, berarti ini pemindahan ambang". Tebakan itu benar hari ini dan diam-
 * diam salah pada sebab ketiga yang ditulis siapa pun kelak, dan yang keliru
 * bukan sebuah label di layar melainkan paket yang ditagihkan. Karena itu
 * sebabnya disimpan, bukan disimpulkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('track_revert_reason')->nullable()->after('track_reverts_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('track_revert_reason');
        });
    }
};
