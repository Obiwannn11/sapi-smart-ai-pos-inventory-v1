<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Default `false`, dan itu BUKAN sekadar kerapian.
            //
            // Menyalakannya untuk semua orang berarti menambah satu langkah ke
            // setiap penjualan non-tunai di setiap toko — termasuk toko yang
            // tidak pernah memintanya — dan langkah itu langsung terasa di
            // antrean kasir. Toko yang perlu bukti bayar akan mencarinya;
            // toko yang tidak perlu tidak akan pernah tahu ia ada.
            //
            // Tidak ada backfill, dengan alasan yang berkebalikan dari
            // `self_order_enabled` (lihat 2026_07_29_141632): di sana fitur
            // sudah hidup dan `false` akan mematikannya. Di sini fiturnya baru
            // lahir hari ini — tidak ada satu pun tenant yang sedang memakainya.
            $table->boolean('payment_proof_enabled')->default(false)->after('ai_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('payment_proof_enabled');
        });
    }
};
