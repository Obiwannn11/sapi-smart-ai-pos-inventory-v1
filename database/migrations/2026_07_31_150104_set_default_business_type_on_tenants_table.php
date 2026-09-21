<?php

use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Isi tipe usaha yang masih kosong dengan nilai bawaan.
     *
     * Ketika kolom ini lahir, `null` dipilih dengan sengaja: menebak tipe usaha
     * tenant lama akan menghasilkan dasar harga yang salah tanpa ada yang
     * menyadari. Alasan itu bertahan selama yang MENGISINYA adalah pemilik SaaS.
     *
     * Sejak pengisiannya berpindah ke tangan pemilik toko sendiri, `null` justru
     * berubah jadi masalah: ia bukan lagi "belum ada yang menjawab" melainkan
     * "pemiliknya belum sempat membuka Pengaturan", dan selama itu tiap aturan
     * harga yang menyebut tipe usaha diam-diam melewatkan mereka.
     *
     * `lainnya` adalah pilihan yang paling jujur untuk keadaan itu — ia sudah
     * ada di katalog, tidak mengaku tahu apa pun tentang usahanya, dan bisa
     * diperbaiki pemiliknya kapan saja dari Pengaturan.
     *
     * Kolomnya tetap `nullable` di tingkat basis data. Yang menjamin nilainya
     * terisi adalah pendaftaran dan form Pengaturan, dan di sanalah tempatnya:
     * satu-satunya yang tahu jawabannya adalah orang yang mengisi form itu.
     */
    public function up(): void
    {
        DB::table('tenants')
            ->whereNull('business_type')
            ->update(['business_type' => Tenant::BUSINESS_TYPE_DEFAULT]);
    }

    /**
     * Tidak dibalik: nilai yang sudah dikoreksi pemiliknya sendiri tidak bisa
     * dibedakan lagi dari yang diisikan migrasi ini, dan mengosongkan keduanya
     * akan membuang jawaban yang benar bersama tebakan.
     */
    public function down(): void
    {
        //
    }
};
