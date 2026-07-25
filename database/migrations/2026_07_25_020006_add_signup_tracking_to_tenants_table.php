<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak pendaftaran, untuk mengenali trial yang diambil berulang kali.
     *
     * `signup_ip` adalah satu-satunya sinyal yang benar-benar tersedia saat
     * pendaftaran: formulirnya hanya meminta nama usaha, nama, surel, dan kata
     * sandi. Nomor telepon akan jadi sinyal yang jauh lebih kuat — untuk UMKM
     * Indonesia nomor jauh lebih mahal dibuat berulang daripada alamat surel —
     * tapi ia belum diminta di mana pun, dan memintanya adalah perubahan alur
     * pendaftaran tersendiri.
     *
     * `flagged_at` menandai untuk DITINJAU, bukan memblokir. Satu IP publik bisa
     * dipakai bersama satu kompleks pertokoan; memblokir otomatis akan menjegal
     * warung sebelah yang tidak melakukan kesalahan apa pun.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('signup_ip', 45)->nullable()->after('pricing_track');
            $table->timestamp('flagged_at')->nullable()->after('signup_ip');
            $table->string('flag_reason', 255)->nullable()->after('flagged_at');

            $table->index('signup_ip');
            $table->index('flagged_at');
        });

        // Pengguna yang sudah ada dianggap terverifikasi. Mereka sudah dipakai
        // bekerja, dan menyalakan gerbang verifikasi tanpa ini akan mengunci
        // seluruh tenant yang berjalan pada hari migrasi dijalankan.
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['signup_ip']);
            $table->dropIndex(['flagged_at']);
            $table->dropColumn(['signup_ip', 'flagged_at', 'flag_reason']);
        });
    }
};
