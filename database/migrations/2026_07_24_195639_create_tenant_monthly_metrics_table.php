<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ringkasan omset bulanan tenant jalur subsidi.
     *
     * **Tidak ada kolom laba, margin, maupun HPP di sini — dan tidak akan
     * pernah ada.** Bukan disimpan lalu disembunyikan: angkanya memang tidak
     * pernah dihitung. Untuk penetapan harga berbasis kemampuan bayar, omset
     * saja sudah cukup; margin hanya menambah data yang harus dijaga tanpa
     * menambah dasar penetapan harga apa pun.
     *
     * Tabel ini adalah SATU-SATUNYA jalan data penjualan tenant sampai ke
     * pemilik SaaS. Halaman platform hanya membaca dari sini, tidak pernah dari
     * `transactions` — itulah yang membuat arch test bisa ditegakkan dan jalur
     * aksesnya bisa diaudit.
     *
     * Barisnya hanya ada untuk tenant berjalur `subsidized`. Tenant jalur
     * normal tidak punya baris di sini sama sekali: datanya bukan disembunyikan
     * di UI, tapi memang tidak pernah ada.
     */
    public function up(): void
    {
        Schema::create('tenant_monthly_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'period']);
            // Dipakai pemangkasan retensi 24 bulan.
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_monthly_metrics');
    }
};
