<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Izin modul akun platform — satu baris = "akun ini boleh membuka modul ini".
     *
     * Sengaja TIDAK memakai spatie/laravel-permission seperti sisi tenant.
     * Pivot spatie (`model_has_roles`) menuntut `tenant_id` non-null karena kolom
     * itu bagian dari primary key-nya, sedangkan akun platform tak punya tenant.
     * Menyiasatinya dengan nilai sentinel berarti menaruh angka ajaib bermakna
     * "bukan tenant" di kolom bernama `tenant_id` — jebakan bagi pembaca skema
     * berikutnya. Kebutuhan di sini pun hanya "boleh buka modul ini" untuk
     * segelintir akun: tak perlu role hierarkis, guard, maupun cache.
     */
    public function up(): void
    {
        Schema::create('platform_user_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_user_id')->constrained('platform_users')->cascadeOnDelete();
            $table->string('module', 50);
            $table->timestamps();

            $table->unique(['platform_user_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_user_modules');
    }
};
