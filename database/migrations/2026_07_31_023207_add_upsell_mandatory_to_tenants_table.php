<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saklar "penawaran wajib diselesaikan" milik owner ([BL-025]).
 *
 * Bawaannya MATI. Menyalakannya bisa menahan tombol bayar, dan fitur yang
 * mampu menahan penjualan tidak boleh menyala tanpa ada yang memilihnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('upsell_mandatory')->default(false)->after('ai_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('upsell_mandatory');
        });
    }
};
