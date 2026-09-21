<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TOTP untuk akun platform ([BL-013]).
     *
     * TOTP, bukan OTP surel — dan itu keputusan keamanan, bukan selera.
     * Surel adalah jalur pemulihan kata sandi akun ini; kalau kotak masuknya
     * jebol, faktor kedua yang dikirim ke sana jebol bersamaan dan dua faktor
     * itu sebenarnya satu.
     */
    public function up(): void
    {
        Schema::table('platform_users', function (Blueprint $table) {
            // Rahasia base32. Terenkripsi di tingkat model (`encrypted` cast):
            // siapa pun yang bisa membaca tabel ini — dump basis data, cadangan
            // yang bocor — bisa membangkitkan kode yang sah selamanya, dan
            // itu justru meniadakan gunanya faktor kedua.
            $table->text('two_factor_secret')->nullable()->after('password');

            // Kode pemulihan sekali pakai, terenkripsi sebagai JSON.
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');

            // NULL = pendaftarannya belum diselesaikan. Kolom terpisah dari
            // `two_factor_secret` karena rahasianya lahir lebih dulu, saat
            // layar pendaftaran dibuka, dan baru sah setelah pengguna
            // membuktikan aplikasinya benar-benar membaca kode yang sama.
            // Tanpa pemisahan ini, membuka layar pendaftaran lalu menutupnya
            // akan mengunci akun dengan rahasia yang tidak ada di ponsel mana
            // pun.
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('platform_users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
