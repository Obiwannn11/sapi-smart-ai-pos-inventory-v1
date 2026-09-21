<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laci mana yang menerima uang penjualan ini ([BL-028] Tahap B, langkah 1).
 *
 * Sampai sekarang kepemilikan laci DITURUNKAN: rekonsiliasi mencocokkan
 * `transactions.user_id` dengan rentang `opened_at`–`closed_at` sesi. Itu benar
 * selama satu kasir per outlet dan satu sesi per hari, dan hari ini data masih
 * begitu — tapi ia sudah salah di satu tempat yang tidak terlihat: aturan
 * `[BL-028]` menyebut uang milik laci yang MELUNASI, sedangkan tagihan terbuka
 * membawa `user_id` PEMBUATNYA dan tanggal efektif saat ia DIBUKA. Tagihan yang
 * dibuka shift pagi lalu dilunasi shift malam karena itu menaruh uangnya di
 * laci pagi. Kolom ini adalah satu-satunya cara menyatakannya, bukan
 * menyimpulkannya.
 *
 * **Tidak ada backfill, dan itu disengaja.** Baris lama dibiarkan `null`.
 * Konsekuensinya harus dibaca sebelum seseorang memakai kolom ini:
 *
 *   `null` di sini berarti DUA hal yang berbeda — "lahir sebelum kolom ini
 *   ada" dan "memang tidak jatuh ke laci mana pun" (pelunasan terlambat oleh
 *   pemilik, self-order lewat webhook, penjualan saat tak ada sesi terbuka).
 *   Keduanya tidak bisa dibedakan dari nilainya sendiri, hanya dari umur
 *   barisnya.
 *
 * Karena itu jalur BACA rekonsiliasi sengaja TIDAK dipindahkan ke kolom ini
 * sekarang — 248 sesi lama akan menghitung nol. Sakelar bacanya adalah langkah
 * 2, dan syaratnya salah satu dari: seluruh sesi yang masih hidup lahir sesudah
 * migrasi ini, atau backfill benar-benar dijalankan.
 *
 * `nullOnDelete` dan bukan `cascadeOnDelete`: tidak ada satu pun jalur yang
 * menghapus laci hari ini, tapi kalau suatu hari ada, cascade akan ikut
 * menghapus penjualannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('cash_drawer_id')
                ->nullable()
                ->after('user_id')
                ->constrained('cash_drawers')
                ->nullOnDelete();

            // Pola bacanya nanti sama dengan rekonsiliasi hari ini: seluruh
            // penjualan SELESAI milik satu sesi.
            $table->index(['cash_drawer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['cash_drawer_id']);
            $table->dropIndex(['cash_drawer_id', 'status']);
            $table->dropColumn('cash_drawer_id');
        });
    }
};
