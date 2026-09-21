<?php

use App\Services\CashDrawerAttributionBackfill;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill `transactions.cash_drawer_id` ([BL-028] Tahap B langkah 2).
 *
 * Migrasi, bukan perintah yang dijalankan tangan: rekonsiliasi membaca kolom
 * ini di commit yang sama, dan syarat sakelar bacanya tidak boleh bergantung
 * pada seseorang yang ingat menjalankan satu perintah lagi sesudah deploy.
 * Aturan pencocokannya ada di CashDrawerAttributionBackfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(CashDrawerAttributionBackfill::class)->run();
    }

    /**
     * Sengaja kosong. Sesudah backfill, baris yang diisi di sini tidak bisa
     * dibedakan dari baris yang diisi saat penjualannya terjadi — mengosongkan
     * kolomnya akan ikut menghapus atribusi yang benar.
     */
    public function down(): void
    {
        //
    }
};
