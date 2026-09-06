<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saklar per-jenis saran jual, milik tiap toko ([BL-099]).
 *
 * Sebelum ini satu-satunya saklar ada di `config/upsell.php` — berkas PHP yang
 * hanya bisa disentuh orang dengan akses server. Laporan Saran Jual sudah
 * memisahkan angkanya per jenis SUPAYA jenis yang tak pernah diterima bisa
 * dimatikan, lalu tidak menyediakan tempat mematikannya.
 *
 * Empat kolom, bukan satu kolom JSON: setelan per-tenant di aplikasi ini
 * selalu berbentuk kolom nyata (`kitchen_queue_enabled`, `upsell_mandatory`,
 * `min_margin_percent`), dan satu kolom serba guna akan jadi satu-satunya
 * tempat yang polanya berbeda.
 *
 * Bawaannya `true` — kebalikan dari `upsell_mandatory` yang bawaannya mati.
 * Alasannya bukan selera: keempat jenis ini SUDAH berjalan hari ini untuk
 * setiap tenant, jadi bawaan `false` akan mematikan fitur yang sedang dipakai
 * pada saat migrasinya jalan.
 *
 * Config tetap ada dan tetap menang — lihat `UpsellIndexBuilder::enabled()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('upsell_attach_enabled')->default(true)->after('upsell_mandatory');
            $table->boolean('upsell_pressed_stock_enabled')->default(true)->after('upsell_attach_enabled');
            $table->boolean('upsell_upsize_enabled')->default(true)->after('upsell_pressed_stock_enabled');
            $table->boolean('upsell_manual_enabled')->default(true)->after('upsell_upsize_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'upsell_attach_enabled',
                'upsell_pressed_stock_enabled',
                'upsell_upsize_enabled',
                'upsell_manual_enabled',
            ]);
        });
    }
};
