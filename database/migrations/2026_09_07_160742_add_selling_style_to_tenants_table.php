<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cara berjualan tenant — kunci paket setelan awal ([BL-035]).
 *
 * Sengaja BUKAN `business_type`, dan bukan pula kolom yang menggantikannya.
 * `business_type` milik penetapan harga: ia jadi dimensi di
 * `config/pricing-dimensions.php` dan dibekukan per tagihan di
 * `invoices.pricing_context`. Cara berjualan tidak menyentuh harga sama sekali
 * — ia cuma menjawab "setelan awal mana yang paling masuk akal untuk orang
 * ini". Menumpangkannya pada `business_type` akan menyeret "gerai acara" ke
 * dalam `PricingRule` dan `BusinessTypeResolver`, dan penjual di CFD tetap
 * `kuliner` di mata tarif.
 *
 * `nullable` tanpa bawaan, dan itu disengaja: tenant yang mendaftar sebelum
 * pertanyaannya ada memang tidak pernah menjawabnya. Menebak jawabannya
 * sekarang berarti mengaku tahu cara mereka bekerja — dan kolom ini justru
 * lahir untuk berhenti menebak. `null` terbaca sebagai "belum ditanya", dan
 * satu-satunya akibatnya adalah halaman Pengaturan tidak menyorot paket mana
 * pun sebagai milik mereka.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('selling_style')->nullable()->after('business_type');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('selling_style');
        });
    }
};
