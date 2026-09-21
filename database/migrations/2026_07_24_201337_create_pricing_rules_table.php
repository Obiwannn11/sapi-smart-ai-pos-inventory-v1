<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bracket omzet → tarif untuk jalur subsidi.
     *
     * Aturan harga adalah DATA, bukan kode. Pemilik SaaS mengubahnya sendiri
     * dari platform console; tidak ada satu pun angka tarif yang perlu di-deploy.
     *
     * `effective_from` adalah kunci grandfathering. Aturan yang baru dibuat
     * hanya berlaku untuk periode yang dimulai setelah tanggal itu — mengedit
     * angka bracket hari ini tidak boleh mengubah tagihan orang yang sudah
     * berjalan. Perlindungan lapis keduanya ada di `subscriptions.price_locked`,
     * yang menyimpan harga yang benar-benar disepakati.
     *
     * `max_revenue` null berarti tanpa batas atas.
     *
     * Baris awalnya diisi dari `config/subscription.php` supaya bracket yang
     * sudah dipakai Tahap C tidak hilang saat sumbernya berpindah ke tabel ini.
     */
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('label', 20);
            $table->decimal('min_revenue', 14, 2);
            $table->decimal('max_revenue', 14, 2)->nullable();
            $table->decimal('price', 12, 2);
            $table->date('effective_from');
            $table->timestamps();

            $table->index(['effective_from', 'min_revenue']);
        });

        $now = now();

        foreach (config('subscription.revenue_brackets', []) as $bracket) {
            DB::table('pricing_rules')->insert([
                'label' => $bracket['label'],
                'min_revenue' => $bracket['min'],
                'max_revenue' => $bracket['max'],
                'price' => $bracket['price'],
                // Berlaku sejak jauh di belakang: ini bukan aturan baru, hanya
                // aturan lama yang berpindah tempat tinggal. Menyetelnya ke hari
                // ini akan membuat tenant yang sudah berjalan mendadak tidak
                // punya bracket sama sekali.
                'effective_from' => '2000-01-01',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
