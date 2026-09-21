<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jendela buka kunci pajak ([BL-065] butir 4).
     *
     * `tax_enabled` dan `tax_mode` terkunci begitu ada satu penjualan berpajak,
     * dan layar setelan pemilik sudah menyuruhnya "hubungi operator" sejak
     * 2026-08-28 — tanpa operator punya tombolnya. Kolom ini tombolnya.
     *
     * Yang dibuka operator adalah KUNCINYA, bukan setelannya. Pembedaan itu
     * bukan kerapian: panel platform pernah punya kuasa mengubah `business_type`
     * tenant dan kuasa itu DICABUT ([BL-015] → `TenantController`), dengan
     * alasan yang berlaku persis sama di sini — cara kerja usaha orang bukan
     * milik penyedia layanan. Operator mengembalikan kemampuan pemilik toko
     * memilih; yang memilih tetap pemilik toko, di layarnya sendiri.
     *
     * Berupa BATAS WAKTU, bukan boolean. Kunci yang dibuka tanpa batas adalah
     * kunci yang mati: kalau pemiliknya lupa memakainya hari itu, tidak ada
     * yang menutupnya kembali dan lubang yang penguncian ini hindari terbuka
     * diam-diam berbulan-bulan. Jendelanya juga HABIS BEGITU DIPAKAI —
     * satu pembukaan untuk satu perubahan, ditegakkan di `TaxSettingsController`.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('tax_lock_opened_until')->nullable()->after('tax_label');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('tax_lock_opened_until');
        });
    }
};
