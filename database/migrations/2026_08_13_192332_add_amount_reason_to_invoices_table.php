<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alasan di balik nominal yang menyimpang dari aturan harga (`[BL-057]`(a)).
     *
     * Tidak menumpang `rejection_reason`. Keduanya sama-sama kalimat bebas yang
     * dibaca tenant, tapi menjawab pertanyaan yang berbeda pada saat yang
     * berbeda: `rejection_reason` menjelaskan kenapa BUKTI BAYAR ditolak dan
     * lahir setelah tagihan berjalan, sedangkan kolom ini menjelaskan kenapa
     * NOMINALNYA begini dan lahir bersama tagihannya. Satu tagihan bisa punya
     * keduanya sekaligus — harga khusus yang buktinya kurang — dan satu kolom
     * berarti yang kedua menimpa yang pertama.
     *
     * Nullable, dan memang akan sering null: tagihan yang nominalnya persis
     * sama dengan tarif aturan tidak dimintai alasan. Memaksa alasan untuk
     * tagihan yang mengikuti aturan hanya melatih orang mengetik "sesuai
     * aturan" tanpa membacanya.
     *
     * Terlihat tenant — itu keputusan sadar, bukan kelalaian daftar putih.
     * Nominal yang berbeda dari daftar harga tanpa penjelasan adalah pertanyaan
     * yang pasti datang, dan menjawabnya di layar lebih murah daripada
     * menjawabnya lewat percakapan. Konsekuensinya ditanggung penulisnya:
     * kalimat yang ditulis di sini akan dibaca orang yang ditagih.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('amount_reason', 500)->nullable()->after('pricing_context');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('amount_reason');
        });
    }
};
