<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak pengungkapan angka "seharusnya di laci" ([BL-090]).
 *
 * Keputusan pemilik 2026-08-21 memilih **mencatat, bukan mencegah**: angkanya
 * tetap bisa dibuka kasir, tapi setiap pembukaannya meninggalkan baris di
 * sini. Itu cara masalah ini diselesaikan di kasir sungguhan — penyimpangan
 * tidak diblokir, ia jadi terlihat.
 *
 * Tabel tersendiri, bukan kolom penghitung di `cash_drawers`, karena yang
 * ditanyakan pemilik saat curiga bukan "berapa kali" melainkan "kapan, dan
 * oleh siapa". Penghitung menjawab yang pertama saja, dan tidak bisa dibuat
 * menjawab yang kedua tanpa migrasi kedua.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_drawer_reveals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cash_drawer_id')->constrained('cash_drawers')->cascadeOnDelete();
            // Disimpan terpisah dari `cash_drawers.user_id` yang sudah ada:
            // pemilik yang membuka laci kasirnya sendiri kelak akan tercatat
            // sebagai dirinya, bukan sebagai kasir pemegang sesi itu.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('revealed_at');
            $table->timestamps();

            // Satu-satunya pola bacanya: seluruh pengungkapan pada satu sesi,
            // urut waktu — dipakai daftar sesi kas milik pemilik.
            $table->index(['cash_drawer_id', 'revealed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_reveals');
    }
};
