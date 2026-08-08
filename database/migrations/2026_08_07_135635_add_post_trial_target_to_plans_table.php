<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paket tujuan tenant setelah masa gratisnya habis — `[BL-052]`.
     *
     * Sampai sekarang `trial_ends_at` ditulis di `startTrial()` dan tidak pernah
     * dibaca lagi oleh siapa pun. Akibatnya paket `free` TIDAK BISA HIDUP:
     * tarifnya Rp 0, penerbit tagihan melewatinya tanpa memperpanjang periode,
     * lalu periodenya lewat dan tenant turun ke masa tenggang — tiap periode,
     * selamanya. Keputusan pemilik 2026-08-07 menutupnya dengan memindahkan
     * tenant ke paket berbayar begitu masa gratisnya habis; penanda ini yang
     * menunjukkan ke MANA.
     *
     * Sebagai penanda di `plans`, bukan slug di config (`[BL-052]`(b)): pola
     * `is_adaptive_fallback` sudah membuktikan bentuknya. Slug `paid-1` yang
     * tertanam di kode atau config berarti mengganti paket masuk menuntut
     * deploy, dan berarti nama paket hidup di dua tempat yang bisa berselisih.
     *
     * Perannya tunggal, dan ketunggalan itu ditegakkan di
     * `Plan::setPostTrialTarget()` — bukan oleh indeks unik, karena `false`
     * boleh berulang dan indeks parsial tidak portabel ke SQLite yang dipakai
     * pengujian.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('is_post_trial_target')->default(false)->after('is_adaptive_fallback');
        });

        // Ditunjuk di migrasi, bukan dibiarkan kosong menunggu pemilik SaaS
        // membukanya di panel. Tanpa penunjukan, perpindahannya tidak berjalan
        // sama sekali — dan yang terlihat hanyalah keadaan lama, yaitu tenant
        // gratis yang diam-diam jatuh ke tenggang. Kegagalan yang bentuknya
        // persis sama dengan bug yang baru saja ditutup tidak boleh jadi
        // keadaan awal.
        //
        // `paid-1` karena itulah paket masuk berbayar yang disebut keputusan
        // pemilik 2026-08-07. Bila slug itu tidak ada di pemasangan ini, jatuh
        // ke paket aktif termurah yang tarifnya di atas nol — menunjuk paket
        // Rp 0 hanya akan mengulang lingkaran yang sama.
        $target = DB::table('plans')->where('slug', 'paid-1')->value('id')
            ?? DB::table('plans')
                ->where('is_active', true)
                ->where('base_price', '>', 0)
                ->orderBy('base_price')
                ->value('id');

        if ($target !== null) {
            DB::table('plans')->where('id', $target)->update(['is_post_trial_target' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('is_post_trial_target');
        });
    }
};
