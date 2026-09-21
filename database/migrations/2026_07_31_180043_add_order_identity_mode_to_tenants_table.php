<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cara outlet ini mengenali pesanannya: nama, nomor meja, atau kode panggil
 * ([BL-026]).
 *
 * SATU mode aktif, bukan tiga saklar. Warung yang disodori ketiganya sekaligus
 * akan mengisi nol dari tiga — dan identitas yang kadang diisi kadang tidak
 * lebih buruk daripada tidak punya identitas sama sekali, karena tidak ada yang
 * bisa bersandar padanya.
 *
 * Bawaannya `none`: outlet yang sudah berjalan tidak tiba-tiba memperoleh satu
 * langkah tambahan di tiap penjualan tanpa ada yang memilihnya.
 *
 * `string`, bukan `enum`: mengubah daftar nilai enum memaksa SQLite (yang
 * dipakai suite tes) membangun ulang tabelnya — ongkos yang sudah dibayar
 * sekali di fase EDIT-TX dan tidak perlu dibayar lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('order_identity_mode', 10)
                ->default('none')
                ->after('upsell_mandatory');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('order_identity_mode');
        });
    }
};
