<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak penjualan barang yang SUDAH kedaluwarsa, pada baris penjualannya
     * ([BL-108]).
     *
     * Keputusan pemilik 2026-09-08: penjualannya tidak dilarang, tapi berhenti
     * bisa terjadi tanpa disadari. Keputusan 2026-09-15: kasir boleh
     * mengonfirmasi, dengan alasan tertulis, dan pemilik meninjaunya sesudahnya
     * (preseden `[BL-087]`, bukan `[BL-018]`).
     *
     * Polanya sama dengan kolom potongan di sebelahnya: snapshot yang bertahan
     * sendiri. `product_variants.expiry_date` bisa berubah besok, dan batchnya
     * bisa habis dan hilang dari layar — jejak ini tidak boleh ikut hilang.
     */
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            // Berapa unit di baris ini yang diambil dari batch yang sudah lewat
            // tanggalnya. Bisa lebih kecil dari `qty`: stok segar habis lebih
            // dulu, sisanya baru diambil dari yang basi.
            $table->unsignedInteger('expired_qty')->default(0)->after('below_floor_approved_by');

            // Tanggal kedaluwarsa PALING LAMA di antara unit basi yang terjual —
            // yang paling parah, bukan rata-ratanya.
            $table->date('expiry_date_at_sale')->nullable()->after('expired_qty');

            // Siapa yang menyatakan "tetap jual". NULL dengan `expired_qty > 0`
            // berarti penjualannya terjadi tanpa konfirmasi — hanya mungkin
            // lewat jalur yang tidak punya manusia untuk ditanya (sinkronisasi
            // offline tanpa alasan, pesanan mandiri yang sudah dibayar), dan
            // justru itu yang harus paling mudah ditemukan pemilik.
            $table->foreignId('expired_sale_confirmed_by')->nullable()->after('expiry_date_at_sale')
                ->constrained('users')->nullOnDelete();

            $table->string('expired_sale_reason', 200)->nullable()->after('expired_sale_confirmed_by');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropConstrainedForeignKey('expired_sale_confirmed_by');
            $table->dropColumn(['expired_qty', 'expiry_date_at_sale', 'expired_sale_reason']);
        });
    }
};
