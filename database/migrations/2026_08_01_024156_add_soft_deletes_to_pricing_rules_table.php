<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aturan yang dihapus berhenti berlaku, tapi tidak lenyap.
     *
     * Sebelum ini aturan yang SUDAH berlaku tidak bisa dihapus sama sekali, dan
     * alasannya benar: ia dasar harga periode yang sudah ditagihkan, dan
     * `invoices.pricing_rule_id` yang `nullOnDelete` akan kehilangan tautannya —
     * tagihan lamanya tetap ada, tapi pertanyaan "aturan mana yang menghasilkan
     * angka ini" jadi tak terjawab.
     *
     * Yang keliru bukan alasannya, melainkan kesimpulannya: dari larangan itu
     * pemilik SaaS tidak punya cara APA PUN menghentikan aturan yang telanjur
     * salah terbit. Satu-satunya jalan adalah menerbitkan aturan pengganti
     * berlabel sama — yang tidak menolong bila yang diinginkan justru
     * meniadakan kelompoknya.
     *
     * `deleted_at` menyelesaikan keduanya sekaligus: barisnya tetap ada bagi
     * tagihan yang menautnya, sementara `SoftDeletes` mengeluarkannya dari
     * setiap query penetapan harga sejak detik itu.
     */
    public function up(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
