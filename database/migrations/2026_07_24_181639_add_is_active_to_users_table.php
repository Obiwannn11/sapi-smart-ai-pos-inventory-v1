<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda aktif/nonaktif pengguna tenant. Dua kegunaan yang berbeda:
     *
     * 1. Batas seat dihitung dari pengguna AKTIF, bukan semua baris — supaya
     *    mengganti staf yang keluar tidak menuntut upgrade paket.
     * 2. Pengguna nonaktif tidak bisa masuk. Ini menggantikan kebiasaan
     *    menghapus akun staf, yang selama ini satu-satunya cara — dan yang ikut
     *    menghapus jejak siapa yang melayani transaksi lama.
     *
     * Default `true` sekaligus mengisi mundur semua pengguna yang sudah ada.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_active']);
            $table->dropColumn('is_active');
        });
    }
};
