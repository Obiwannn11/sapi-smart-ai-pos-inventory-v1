<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nama varian yang BENAR-BENAR ada di konteks satu analisis ([BL-100] tahap 2).
 *
 * Bukan hasil pemetaannya, melainkan bahan bakunya — dan pemisahan itu yang
 * membuat entri ini bekerja:
 *
 *   Yang disimpan harus keadaan saat analisis DIBUAT, karena hanya di situlah
 *   diketahui nama mana yang sungguh disodorkan ke model. Tanpa daftar ini,
 *   nama karangan model tidak bisa dibedakan dari nama yang barangnya sudah
 *   dihapus — keduanya sama-sama tidak punya varian aktif, dan menandai yang
 *   pertama "sudah dihapus" mengubah halusinasi jadi pernyataan berwenang.
 *
 *   Yang TIDAK disimpan adalah id-nya. Pemetaan nama ke varian dilakukan saat
 *   dibaca, terhadap katalog hari ini. Menyimpan id di sini akan membekukan
 *   tautan pada katalog bulan lalu, dan tautan ke varian yang sudah dihapus
 *   mendarat di halaman kosong tanpa satu pun tanda bahwa ia sudah basi.
 *
 * Nullable dengan sengaja: analisis lama tidak punya daftar ini dan tidak bisa
 * direkonstruksi — konteksnya tidak pernah disimpan. Nama di hasil analisis
 * lama karena itu tetap tampil sebagai teks biasa, bukan salah tertaut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->json('context_variants')->nullable()->after('result');
        });
    }

    public function down(): void
    {
        Schema::table('ai_analyses', function (Blueprint $table) {
            $table->dropColumn('context_variants');
        });
    }
};
