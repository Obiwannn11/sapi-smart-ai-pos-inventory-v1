<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_payments', function (Blueprint $table) {
            // Kolomnya di `transaction_payments`, BUKAN di `transactions`.
            //
            // Satu transaksi bisa dibayar beberapa metode sekaligus (split
            // bill): setengah tunai, setengah QRIS. Bukti bayar melekat pada
            // PEMBAYARANNYA — pada bagian yang lewat QRIS — bukan pada
            // penjualannya. Menaruhnya di `transactions` akan memaksa satu foto
            // mewakili dua pembayaran yang berbeda sifatnya.
            //
            // Nullable dan tetap nullable, bahkan saat toko mewajibkan foto:
            // baris tunai tidak pernah punya bukti, dan itu bukan kekurangan
            // data.
            $table->string('proof_path')->nullable()->after('reference_code');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_payments', function (Blueprint $table) {
            $table->dropColumn('proof_path');
        });
    }
};
