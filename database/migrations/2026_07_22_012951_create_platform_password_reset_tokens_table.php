<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token pemulihan kata sandi akun platform.
     *
     * Tabel terpisah dari `password_reset_tokens` milik tenant. Keduanya
     * berkunci `email`, dan alamat yang sama bisa saja terdaftar di kedua dunia
     * — kalau ditumpuk di satu tabel, permintaan reset di satu sisi akan
     * menimpa token sisi lain, dan token yang bocor dari satu sisi bisa dipakai
     * di sisi yang lain.
     *
     * Struktur mengikuti bawaan Laravel agar dikenali PasswordBroker.
     */
    public function up(): void
    {
        Schema::create('platform_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_password_reset_tokens');
    }
};
