<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bagaimana tagihan ini dilunasi.
 *
 * Selama hanya ada satu cara — pemilik SaaS memeriksa bukti transfer —
 * pertanyaannya tidak pernah muncul, dan `verified_by` sudah menjawabnya
 * dengan sendirinya. Begitu ada cara kedua (`[BL-045]`: tombol peragaan) dan
 * kelak cara ketiga (payment gateway), `verified_by` yang kosong jadi ambigu:
 * ia bisa berarti simulasi, bisa berarti webhook, bisa berarti baris lama.
 *
 * Kolomnya `nullable` dan TIDAK di-backfill: tagihan yang lunas sebelum ini
 * memang tidak punya jawabannya, dan menebaknya menjadi `platform_verify`
 * akan membuat jejak yang terlihat lebih pasti daripada kenyataannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('settled_via', 32)->nullable()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('settled_via');
        });
    }
};
