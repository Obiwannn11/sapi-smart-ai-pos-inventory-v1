<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Setelan "izinkan tagihan terbuka" milik owner ([BL-104]).
 *
 * Bawaannya MENYALA, kebalikan dari `upsell_mandatory`: tagihan terbuka sudah
 * berjalan untuk setiap tenant sebelum saklarnya ada, jadi bawaan mati akan
 * mencabut tombol "Tunda Bayar" dari kasir yang sedang memakainya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('open_bill_enabled')->default(true)->after('upsell_mandatory');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('open_bill_enabled');
        });
    }
};
