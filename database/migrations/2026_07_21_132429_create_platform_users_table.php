<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akun pemilik SaaS — berdiri DI ATAS semua tenant.
     *
     * Sengaja tabel terpisah dari `users`, bukan kolom penanda di sana: `users`
     * selalu terikat pada satu tenant (TenantScope dan spatie teams sama-sama
     * memakai `tenant_id`), sementara akun platform memang tidak punya tenant.
     * Menumpangkannya berarti menaruh baris ber-`tenant_id` null di tabel yang
     * seluruh mekanismenya mengandaikan kolom itu terisi.
     */
    public function up(): void
    {
        Schema::create('platform_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_users');
    }
};
