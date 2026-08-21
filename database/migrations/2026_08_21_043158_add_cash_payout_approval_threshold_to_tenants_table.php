<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ambang persetujuan uang keluar laci ([BL-087]).
 *
 * Kolom tenant, bukan konstanta seperti `CashDrawer::MAX_SESSION_HOURS`, atas
 * permintaan pemilik: angkanya harus bisa diubah dari dashboard. Dan memang di
 * sinilah tempatnya — Rp 50.000 adalah uang galon di satu toko dan setoran
 * setengah hari di toko lain, jadi tidak ada satu angka yang benar untuk
 * semuanya. Bandingkan `min_margin_percent`, yang jadi kolom tenant dengan
 * alasan yang sama persis.
 *
 * Nilainya juga menentukan BENTUK fiturnya, bukan cuma besarannya: 0 berarti
 * setiap pencatatan menunggu persetujuan pemilik, dan angka sangat besar
 * berarti tidak ada yang pernah menunggu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('cash_payout_approval_threshold', 12, 2)->default(50000)->after('min_margin_percent');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('cash_payout_approval_threshold');
        });
    }
};
