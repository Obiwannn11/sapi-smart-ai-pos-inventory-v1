<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tarif publik jalur normal. Semua angka di sini adalah DATA yang di-CRUD
     * dari platform console — bukan konstanta di kode — supaya pemilik SaaS
     * bisa mengubah harga tanpa deploy.
     *
     * `slug` dipakai untuk merujuk paket dasar dari kode (mis. saat registrasi
     * memberi trial) tanpa mengunci diri ke id auto-increment yang bisa berbeda
     * antar pemasangan.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('base_price', 12, 2)->default(0);
            $table->unsignedSmallInteger('included_seats')->default(1);
            $table->decimal('extra_seat_price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Paket dasar ditulis di migrasi, bukan seeder: registrasi tenant baru
        // langsung membutuhkannya, jadi pemasangan yang lupa menjalankan seeder
        // akan berakhir dengan tenant tanpa langganan sama sekali. Mengikuti
        // pola 2026_07_21_221120_add_is_owner_to_platform_users_table yang juga
        // mengisi data agar tak ada keadaan setengah jadi.
        DB::table('plans')->insert([
            'name' => 'Dasar',
            'slug' => 'dasar',
            'base_price' => 0,
            'included_seats' => 1,
            'extra_seat_price' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
