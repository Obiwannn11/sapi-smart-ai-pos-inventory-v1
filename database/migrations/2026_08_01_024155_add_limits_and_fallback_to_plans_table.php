<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dua hal yang sama-sama membuat paket berakibat sesuatu.
     *
     * `limits` — batas per paket, sebagai SATU kolom JSON dan bukan satu kolom
     * per batas (`[BL-046]`(b)). Batas berikutnya pasti menyusul; kalau tiap
     * batas baru berarti satu migrasi, yang terjadi bukan orang menulis migrasi
     * melainkan orang menulis `if` di kode sebagai gantinya. Kunci pertamanya
     * `ai_daily`. Kunci yang TIDAK ada berarti "ikut bawaan platform" — bukan
     * nol, dan bedanya penting: paket yang lupa disetel tidak boleh diam-diam
     * mematikan AI bagi tenant yang sudah memakainya.
     *
     * `is_adaptive_fallback` — paket yang menampung tenant jalur Harga Adaptif
     * ketika tidak ada satu pun aturan tarif yang cocok untuknya (mis. aturannya
     * dihapus, atau omsetnya di atas bracket teratas). Sebelum ini keadaan itu
     * berakhir sebagai `price = null`: tenant tanpa harga sama sekali, dan
     * tagihan yang harus diketik manual tanpa dasar apa pun.
     *
     * Penandanya tinggal di `plans`, bukan di config, karena paketnya sendiri
     * adalah data yang di-CRUD dari panel — menaruh rujukannya di config akan
     * membuat slug paket hidup di dua tempat yang bisa berselisih.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('limits')->nullable()->after('extra_seat_price');
            $table->boolean('is_adaptive_fallback')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['limits', 'is_adaptive_fallback']);
        });
    }
};
