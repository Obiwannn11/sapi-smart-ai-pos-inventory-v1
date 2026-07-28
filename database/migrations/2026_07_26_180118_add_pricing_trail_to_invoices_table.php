<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak aturan yang MENANG pada tiap tagihan.
     *
     * Selama aturan harga hanya punya satu sumbu, "aturan mana yang berlaku
     * waktu itu" masih bisa direka ulang dari `effective_from` dan satu angka
     * omzet. Begitu satu aturan punya banyak syarat, rekonstruksi itu menuntut
     * mengetahui SELURUH nilai dimensi tenant pada saat penagihan — dan nilai
     * itu berubah tiap bulan. Menyimpan `price_locked` saja hanya menjawab
     * "berapa", tidak pernah "kenapa".
     *
     * Karena itu keduanya disimpan berdampingan:
     *   - `pricing_rule_id` — aturan yang cocok, `nullOnDelete` supaya tagihan
     *     lama tidak ikut hilang bila aturan yang belum berlaku dibatalkan.
     *   - `pricing_context`  — cuplikan nilai dimensi saat itu, dibekukan. Ia
     *     TIDAK boleh dihitung ulang belakangan; menghitung ulang justru
     *     mengembalikan angka hari ini, bukan angka yang jadi dasar tagihannya.
     *
     * Keduanya `nullable`: tagihan bisa diterbitkan tanpa aturan yang cocok
     * (nominalnya diketik manual, dan itu tetap sah), dan seluruh tagihan yang
     * terbit sebelum migrasi ini memang tidak punya jejaknya.
     *
     * Isi `pricing_context` untuk tenant jalur subsidi memuat omzet — data
     * bisnis. Ia tunduk pada batas yang sama seperti `tenant_monthly_metrics`:
     * tidak boleh masuk `InvoiceResource` maupun payload halaman mana pun.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('pricing_rule_id')->nullable()->after('amount')->constrained()->nullOnDelete();
            $table->json('pricing_context')->nullable()->after('pricing_rule_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pricing_rule_id');
            $table->dropColumn('pricing_context');
        });
    }
};
