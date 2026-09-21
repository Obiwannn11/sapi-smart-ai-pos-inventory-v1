<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Paket bawaan berganti nama: `dasar` → `free`.
 *
 * Bukan kosmetik. Sejak keputusan pemilik 2026-08-07 paket ini bukan lagi tier
 * termurah yang bisa dihuni selamanya, melainkan **masa gratis berbatas waktu**
 * yang berakhir dengan perpindahan ke `paid-1`. Namanya harus mengatakan itu,
 * karena "Dasar" mengundang tenant menetap di tempat yang memang tidak
 * dirancang untuk ditinggali.
 *
 * Slug-nya terikat kode lewat `Plan::SLUG_DEFAULT`, jadi rename ini WAJIB
 * berjalan bersama perubahan konstanta itu — bukan disetel tangan dari panel.
 * `Plan::default()` memakai `firstOrFail()`, sehingga keduanya yang berselisih
 * akan menggagalkan pendaftaran tenant pertama, bukan diam-diam salah.
 *
 * Jatah seat ikut naik 1 → 2, dan itu bukan angka komersial melainkan bentuk
 * produk: satu seat berarti pemilik toko satu-satunya yang bisa masuk, tanpa
 * kasir. Untuk aplikasi POS itu bukan paket terbatas, itu paket yang tidak bisa
 * dipakai — dan selama dua bulan pertama paket inilah wajah produknya.
 *
 * Harga dan kuota AI TIDAK disentuh di sini. Keduanya milik pemilik SaaS lewat
 * `/platform/pricing-rules`, dan migrasi yang menimpanya akan menghapus
 * keputusan komersial yang mungkin sudah berbeda di produksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempoten, dan sengaja tidak menyentuh baris yang sudah bernama
        // `free`: pemasangan yang sudah pernah dirapikan tangan tidak boleh
        // ditimpa balik.
        DB::table('plans')
            ->where('slug', 'dasar')
            ->update([
                'name' => 'Free',
                'slug' => 'free',
                'included_seats' => 2,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('plans')
            ->where('slug', 'free')
            ->update([
                'name' => 'Dasar',
                'slug' => 'dasar',
                'included_seats' => 1,
                'updated_at' => now(),
            ]);
    }
};
