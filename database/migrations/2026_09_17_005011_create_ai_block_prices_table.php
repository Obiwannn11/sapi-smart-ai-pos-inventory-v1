<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Harga satu blok kuota AI, sebagai data yang bisa disunting pemilik SaaS.
     *
     * Sebelum ini angkanya tinggal di `config/subscription.php` — satu-satunya
     * tarif yang dibayar tenant yang masih menuntut deploy untuk berubah,
     * sementara paket dan seluruh bracket Adaptif sudah lama jadi baris tabel.
     *
     * SATU BARIS, dan itu disengaja. Tabel ini tidak punya `effective_from`
     * seperti `pricing_rules`, karena keputusan pemilik 2026-09-16 menetapkan
     * kebalikannya: blok yang SUDAH dibeli ikut harga baru pada tagihan
     * berikutnya. Menyimpan riwayat tanggal berlaku hanya akan memberi kesan
     * ada grandfathering yang sebenarnya tidak berlaku di sini — dan kesan itu
     * justru yang paling mahal saat tenant menanyakan kenapa tagihannya naik.
     *
     * Yang tetap menjaga tagihan LAMA bisa dijelaskan adalah
     * `invoices.pricing_context.billing_breakdown.ai_block_price`, yang sudah
     * membekukan tarif tiap periode sejak `[BL-069]`.
     *
     * Tabel ini LAHIR KOSONG, mengikuti preseden `ai_quota_policies`: selama
     * belum ada yang menyuntingnya, `config/subscription.php` tetap lapis
     * terakhir yang berlaku. Dengan begitu pemasangan migrasi ini sendiri tidak
     * mengubah tagihan satu tenant pun.
     */
    public function up(): void
    {
        Schema::create('ai_block_prices', function (Blueprint $table) {
            $table->id();
            $table->decimal('block_price', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_block_prices');
    }
};
