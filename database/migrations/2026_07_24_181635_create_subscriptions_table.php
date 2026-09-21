<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu langganan per tenant (karena itu `tenant_id` unik). Menyimpan sisi
     * KOMERSIAL: paket apa, jalur harga mana, berapa seat, harga berapa, dan
     * periode kapan.
     *
     * Sengaja TIDAK ada kolom `status` di sini. Keadaan operasional tenant
     * (trial / active / grace / suspended) hanya hidup di `tenants.status`:
     * middleware membacanya di setiap request, dan dua kolom status yang
     * berdampingan pasti melenceng satu sama lain cepat atau lambat. Rencana
     * awal di PHASE-SAAS Bagian 4 memuat keduanya — ini penyimpangan yang
     * disengaja.
     *
     * `price_locked` adalah harga yang BENAR-BENAR ditagihkan ke tenant ini.
     * Ditetapkan saat periode dimulai dan tidak ikut berubah ketika tarif di
     * `plans`/`pricing_rules` diedit — inilah mekanisme grandfathering.
     *
     * `seat_high_water` mencatat jumlah pengguna aktif tertinggi selama periode
     * berjalan. Penambahan staf ditegakkan terhadap jumlah aktif SEKARANG (agar
     * mengganti staf terasa wajar), tetapi tagihan mengikuti puncak ini — jadi
     * pola menonaktifkan–mengaktifkan bergantian tidak menghemat sepeser pun.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('pricing_track', 20)->default('normal');
            $table->unsignedSmallInteger('seats')->default(1);
            $table->unsignedSmallInteger('seat_high_water')->default(1);
            $table->decimal('price_locked', 12, 2)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->date('current_period_start')->nullable();
            $table->date('current_period_end')->nullable();
            $table->timestamps();

            $table->index('pricing_track');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
