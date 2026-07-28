<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipe usaha — dimensi harga bertipe ATRIBUT, bukan metrik.
     *
     * Berbeda dari omzet, seat, dan cacah transaksi, nilai ini tidak bisa
     * dihitung aplikasi dari data mana pun: ia harus ditanyakan. Karena itu
     * kolomnya tinggal di `tenants` dan diisi saat pendaftaran, bukan
     * diturunkan dari `transactions` seperti dimensi metrik.
     *
     * `nullable`, dan sengaja demikian: tenant yang mendaftar sebelum
     * pertanyaan ini ada tidak punya jawabannya, dan menebaknya dari nama usaha
     * akan menghasilkan dasar harga yang salah tanpa ada yang menyadari. Aturan
     * harga yang menyebut dimensi ini tidak akan cocok untuk mereka — gagal
     * menutup, bukan gagal membuka.
     *
     * Kolom ini BUKAN data operasional: ia tidak mengungkap penjualan, produk,
     * maupun laba. Karena itu ia boleh dilihat pemilik SaaS di kedua jalur harga
     * tanpa menuntut persetujuan baru — setara dengan nama usaha, yang sudah
     * terlihat sejak Tahap A.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('business_type', 30)->nullable()->after('slug');

            // Dipakai penetapan harga untuk menyaring tenant per tipe.
            $table->index('business_type');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['business_type']);
            $table->dropColumn('business_type');
        });
    }
};
