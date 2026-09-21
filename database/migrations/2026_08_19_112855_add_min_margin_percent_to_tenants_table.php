<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ambang untung minimum — MILIK OWNER, bukan konstanta ([BL-018] poin 2).
     *
     * Lantai harga tiap varian dihitung `cost_price × (1 + margin/100)`, dan
     * angka margin itu keputusan bisnis yang berbeda antar warung: pedagang
     * sayur dan kedai kopi tidak hidup dari persentase yang sama. Menguncinya
     * di kode berarti memilihkan margin untuk orang yang lebih tahu.
     *
     * Default 10% dipilih rendah dengan sengaja: ia lantai, bukan target.
     * Lantai yang ketinggalan tinggi membuat fitur diskonnya nyaris tidak
     * pernah bisa menawarkan apa pun, dan owner akan menyimpulkan ia rusak.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->decimal('min_margin_percent', 5, 2)->default(10.00)->after('payment_proof_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('min_margin_percent');
        });
    }
};
