<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda tenant peragaan.
 *
 * Satu-satunya gunanya hari ini: membuka tombol "simulasikan pembayaran" di
 * halaman Langganan (`[BL-045]` butir 2), yang melunasi tagihan tanpa bukti
 * transfer apa pun. Penanda ini TIDAK berdiri sendiri sebagai gerbang — ia
 * dipasangkan dengan syarat lingkungan non-produksi, sehingga jalur pintasnya
 * tidak pernah ada di produksi walau penandanya ikut terbawa ke sana.
 *
 * Sengaja `default(false)`: tenant yang lahir dari jalur mana pun — pendaftaran,
 * seeder, impor — bukan tenant peragaan sampai seseorang menyatakannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
    }
};
