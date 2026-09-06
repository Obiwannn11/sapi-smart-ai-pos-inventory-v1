<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto struk untuk satu mutasi kas ([BL-093]).
 *
 * `[BL-087]` sudah mewajibkan ALASAN tertulis, dan itu yang membedakan
 * pencatatan ini dari uang yang hilang begitu saja. Yang belum ada adalah bukti
 * yang bisa diperiksa: untuk pengeluaran yang punya struk — galon, belanja
 * bahan, parkir — foto mengubah "katanya beli galon" jadi sesuatu yang bisa
 * dicocokkan.
 *
 * **Nullable, dan akan tetap nullable.** Fotonya OPSIONAL dengan sengaja.
 * Mewajibkannya akan mengulang kesalahan yang `[BL-087]` hindari dari sisi
 * lain: kasir yang tidak bisa mencatat karena struknya hilang tetap
 * mengeluarkan uangnya, dan selisihnya muncul di akhir shift tanpa keterangan
 * apa pun. Sebagian pengeluaran memang tidak berstruk.
 *
 * Retensinya mengikuti keputusan pemilik 2026-08-19 di `[BL-075]`: tanpa batas
 * untuk sekarang, tanpa pembersihan otomatis. Foto yang sudah melekat pada
 * barisnya tidak pernah disentuh perintah pembersih mana pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_drawer_movements', function (Blueprint $table) {
            $table->string('proof_path')->nullable()->after('reason');
        });
    }

    public function down(): void
    {
        Schema::table('cash_drawer_movements', function (Blueprint $table) {
            $table->dropColumn('proof_path');
        });
    }
};
