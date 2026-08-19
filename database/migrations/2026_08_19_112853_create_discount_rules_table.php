<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Diskon sebagai ENTITAS TERSENDIRI, bukan sebagai pengubah
     * `product_variants.price` ([BL-018]).
     *
     * Ini inti seluruh entri backlognya, dan alasannya bukan kerapian model:
     *
     *   Menurunkan `price` varian sebagai "cara mendiskon" membuat potongan itu
     *   TAK BISA DIBEDAKAN dari perubahan harga permanen. Laporan tidak akan
     *   pernah bisa menjawab "berapa yang kita korbankan untuk menghabiskan
     *   stok bulan ini", dan harga katalog kehilangan artinya sebagai acuan.
     *
     * Dengan tabel ini harga katalog tetap utuh, potongannya punya alasan dan
     * masa berlaku, dan tiap baris penjualan bisa menunjuk aturan mana yang
     * dipakai.
     *
     * **Jangan tertukar dengan `pricing_rules`.** Tabel itu harga LANGGANAN
     * SaaS yang dibayar tenant ke pemilik platform. Ini harga jual produk ke
     * pelanggan tenant. Penamaannya sengaja tidak menyerempet.
     */
    public function up(): void
    {
        Schema::create('discount_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // cascadeOnDelete: aturan tanpa barang bukan aturan. Berbeda dari
            // `transaction_items.discount_rule_id`, yang justru harus bertahan
            // setelah aturannya dihapus — ia catatan sejarah.
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();

            // Apa yang membuat barang ini perlu didorong. Bukan hiasan:
            // laporan memisahkan angkanya, dan `near_expiry` adalah satu-satunya
            // yang potongannya mendalam seiring waktu.
            //
            // `expired` TIDAK ADA di daftar ini, dan itu bukan kelalaian.
            // Barang yang sudah kedaluwarsa tidak boleh dijual sama sekali —
            // batas keamanan pangan, bukan pilihan bisnis. Ia dijaga di
            // DiscountService, bukan diserahkan pada kedisiplinan kasir.
            $table->enum('trigger', ['near_expiry', 'dead_stock', 'manual']);

            // Potongan awal, dalam persen harga katalog.
            $table->decimal('percent', 5, 2);

            // Potongan TERDALAM untuk `near_expiry`, dicapai pada hari
            // kedaluwarsa. Null = tidak mendalam, `percent` berlaku rata.
            $table->decimal('max_percent', 5, 2)->nullable();

            // Alasan yang ditulis owner dan dibaca kasir apa adanya. Wajib:
            // potongan tanpa alasan adalah potongan yang tidak bisa
            // dipertanggungjawabkan saat laporannya dibuka berbulan kemudian.
            $table->string('reason', 120);

            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_rules');
    }
};
