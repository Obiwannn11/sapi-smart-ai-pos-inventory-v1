<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('kitchen_queue_enabled')->default(false)->after('business_type');
            $table->boolean('self_order_enabled')->default(false)->after('kitchen_queue_enabled');
            $table->boolean('ai_enabled')->default(true)->after('self_order_enabled');
        });

        // Backfill WAJIB — bukan kerapian, tapi penjaga status quo.
        //
        // Self-order BUKAN fitur baru: ia sudah terpasang dan mungkin sedang
        // dipakai lewat n8n/Telegram. Membiarkannya `false` untuk baris yang
        // sudah ada berarti pada hari rilis setiap integrasi yang hidup
        // menerima 403 dan n8n mulai memberi tahu pelanggan bahwa pemesanan
        // ditutup.
        //
        // Risikonya tidak setara: keliru `false` mematikan yang sedang bekerja;
        // keliru `true` tidak mengubah apa pun bagi tenant yang memang tak
        // pernah memakainya. Default `false` tetap berlaku untuk tenant yang
        // mendaftar SETELAH migrasi ini.
        DB::table('tenants')->update(['self_order_enabled' => true]);

        // `ai_enabled` tidak perlu di-backfill: default kolomnya sudah `true`,
        // dengan alasan yang sama — AI sudah berjalan hari ini.
        // `kitchen_queue_enabled` default `false` tanpa backfill — belum ada
        // apa pun yang memakainya.
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['kitchen_queue_enabled', 'self_order_enabled', 'ai_enabled']);
        });
    }
};
