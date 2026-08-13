<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kebijakan kuota AI sebagai data berjangka waktu, bukan satu angka di `.env`.
     *
     * Bentuknya meniru `pricing_rules` — berlaku sejak kapan — dengan satu
     * tambahan yang justru jadi inti `[BL-047]`(a): `effective_until`. Promo
     * yang tidak punya tanggal akhir akan berakhir sebagai angka yang lupa
     * dikembalikan, dan yang membayar kelupaan itu adalah tagihan kunci
     * bersama milik pemilik SaaS sendiri.
     *
     * Dua `mode`, karena dua pertanyaan berbeda yang sama-sama diminta:
     *
     *   - `baseline` menjawab "berapa kuota bawaan platform" — angka yang
     *     berlaku bagi tenant yang paketnya tidak menetapkan batasnya sendiri.
     *     Inilah pengganti `AI_FREE_TIER_DAILY_LIMIT` yang bisa diubah dari
     *     panel.
     *   - `bonus` menjawab "promo dalam waktu tertentu" — tambahan di ATAS
     *     batas yang sudah berlaku, termasuk bagi tenant yang paketnya sudah
     *     menetapkan batas sendiri.
     *
     * Pembedaan itu bukan hiasan. Setiap paket hari ini menetapkan
     * `limits.ai_daily`-nya sendiri (5/15/30/60), jadi kebijakan yang hanya
     * mengisi kekosongan bawaan tidak akan pernah menyentuh satu pun tenant
     * yang berlangganan — ia lahir sebagai kode mati. Promo harus bisa
     * MENAMBAH, bukan sekadar mengisi.
     *
     * Tidak ada baris awal yang ditulis di sini: selama tabelnya kosong, yang
     * berlaku tetap `config/ai.php` persis seperti sebelumnya. Migrasi ini
     * membuka kemungkinan, bukan mengubah kuota siapa pun.
     */
    public function up(): void
    {
        Schema::create('ai_quota_policies', function (Blueprint $table) {
            $table->id();
            $table->string('label', 60);
            // `string`, bukan `enum`: enum tidak bisa diubah lagi di SQLite
            // tanpa membangun ulang tabelnya, dan pengujian berjalan di sana.
            $table->string('mode', 12);
            $table->unsignedInteger('daily_limit');
            $table->date('effective_from');
            // Null berarti tanpa batas akhir — sah untuk `baseline` (kebijakan
            // bawaan memang berlaku sampai diganti), dan sengaja TIDAK dilarang
            // untuk `bonus`, karena promo terbuka kadang memang yang dimaui.
            // Yang menjaga agar itu keputusan sadar adalah panelnya.
            $table->date('effective_until')->nullable();
            $table->timestamps();
            // Kebijakan yang dihentikan tetap bisa dibaca sebagai riwayat:
            // "kenapa bulan lalu kuota kami 20?" adalah pertanyaan yang muncul
            // setelah barisnya dihapus, bukan sebelumnya.
            $table->softDeletes();

            $table->index(['mode', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_quota_policies');
    }
};
