<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak potongan pada BARIS PENJUALANNYA ([BL-018]).
     *
     * `unit_price` tetap berarti "yang benar-benar dibayar" — tidak ada satu
     * pun pembaca lama yang perlu berubah. Yang ditambahkan adalah konteks
     * yang membuat angka itu bisa dipertanggungjawabkan berbulan-bulan
     * kemudian.
     */
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            // Harga katalog saat penjualan. Tanpa ini, "berapa yang kita
            // korbankan" harus dihitung dari harga varian HARI INI — yang
            // sudah berubah, mungkin beberapa kali.
            $table->decimal('original_unit_price', 12, 2)->nullable()->after('unit_price');

            // Potongan PER UNIT, bukan per baris. Per unit membuatnya tetap
            // benar saat qty diedit belakangan.
            $table->decimal('discount_amount', 12, 2)->default(0)->after('original_unit_price');

            // nullOnDelete, BUKAN cascade: menghapus aturan diskon tidak boleh
            // menghapus riwayat penjualan yang memakainya — kebalikan dari
            // `discount_rules.product_variant_id`. Yang satu konfigurasi, yang
            // satu sejarah.
            $table->foreignId('discount_rule_id')->nullable()->after('discount_amount')
                ->constrained('discount_rules')->nullOnDelete();

            // Alasan, MENEMPEL pada barisnya — bukan di log terpisah yang bisa
            // dipangkas retensi (keputusan pemilik 2026-07-29). Untuk potongan
            // beraturan ia salinan alasan aturannya; untuk penembusan lantai ia
            // yang diketik owner sendiri.
            $table->string('discount_reason', 200)->nullable()->after('discount_rule_id');

            // Harga modal YANG BERLAKU SAAT ITU.
            //
            // Ini menutup keterbatasan yang sudah lama tercatat pada
            // ProfitService dan yang jadi jauh lebih tajam di sini: begitu
            // `cost_price` jadi dasar klaim "diskon ini tetap untung",
            // perubahan harga modal di kemudian hari akan MENGUBAH KLAIM ATAS
            // PENJUALAN YANG SUDAH LEWAT.
            $table->decimal('cost_price_at_sale', 12, 2)->nullable()->after('discount_reason');

            // Lantai margin yang berlaku saat itu. Tanpa nilainya ikut dibekukan,
            // "seberapa dalam tembusnya" tak bisa dihitung ulang di kemudian
            // hari — persoalan yang sama dengan cost_price di atas.
            $table->decimal('margin_floor_at_sale', 12, 2)->nullable()->after('cost_price_at_sale');

            // Siapa yang menyetujui penjualan di BAWAH lantai. NULL untuk
            // penjualan biasa, dan NULL inilah yang memisahkan "diskon biasa"
            // dari "yang benar-benar dikorbankan" di laporan.
            $table->foreignId('below_floor_approved_by')->nullable()->after('margin_floor_at_sale')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropConstrainedForeignKey('discount_rule_id');
            $table->dropConstrainedForeignKey('below_floor_approved_by');
            $table->dropColumn([
                'original_unit_price',
                'discount_amount',
                'discount_reason',
                'cost_price_at_sale',
                'margin_floor_at_sale',
            ]);
        });
    }
};
