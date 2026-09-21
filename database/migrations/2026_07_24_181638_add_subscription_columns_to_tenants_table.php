<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dua kolom yang membuat tenant punya siklus hidup komersial.
     *
     * `status` — keadaan OPERASIONAL, satu-satunya yang dibaca middleware di
     * tiap request: `trial` | `active` | `grace` | `suspended`. Sengaja tidak
     * dikembarkan di `subscriptions` (lihat catatan di migrasi tabel itu).
     *
     * `pricing_track` — `normal` | `subsidized`. Letaknya di `tenants`, bukan
     * hanya di `subscriptions`, karena inilah GERBANG PRIVASI: job penghitung
     * omset memfilter dengan kolom ini, dan filter itu harus semurah dan
     * sesederhana mungkin — tanpa join.
     *
     * Keduanya string, bukan enum, mengikuti alasan yang sama seperti
     * `platform_audit_logs.severity`: SQLite yang dipakai test suite
     * menyulitkan perubahan enum di kemudian hari.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('status', 20)->default('trial')->after('phone');
            $table->string('pricing_track', 20)->default('normal')->after('status');

            $table->index('status');
            $table->index('pricing_track');
        });

        // Tenant yang sudah ada TIDAK boleh mendarat di `trial` — mereka sudah
        // dipakai bekerja, dan default kolom itu akan membuat mereka kedaluwarsa
        // sebulan lagi tanpa pernah ditawari apa pun.
        DB::table('tenants')->update(['status' => 'active']);

        $planId = DB::table('plans')->where('slug', 'dasar')->value('id');

        if ($planId === null) {
            return;
        }

        $now = now();

        foreach (DB::table('tenants')->select('id')->get() as $tenant) {
            // Seat dibuka selebar pemakaian yang sudah berjalan. Menyetelnya ke
            // `included_seats` akan membuat tenant yang hari ini punya tiga staf
            // langsung melanggar batas begitu migrasi jalan — batas baru tidak
            // boleh berlaku surut terhadap staf yang sudah bekerja.
            $userCount = DB::table('users')->where('tenant_id', $tenant->id)->count();
            $seats = max(1, $userCount);

            DB::table('subscriptions')->insert([
                'tenant_id' => $tenant->id,
                'plan_id' => $planId,
                'pricing_track' => 'normal',
                'seats' => $seats,
                'seat_high_water' => $seats,
                'price_locked' => 0,
                'trial_ends_at' => null,
                'current_period_start' => $now->toDateString(),
                // `addMonthNoOverflow`, sejalan dengan `[BL-030]`: instalasi baru
                // yang kebetulan dijalankan tanggal 31 tidak boleh melahirkan
                // langganan yang periodenya sudah meleset sejak baris pertamanya.
                // Jangkar tanggal tagihnya ditulis migrasi berikutnya, yang
                // membacanya dari tanggal ini.
                'current_period_end' => $now->copy()->addMonthNoOverflow()->toDateString(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['pricing_track']);
            $table->dropColumn(['status', 'pricing_track']);
        });
    }
};
