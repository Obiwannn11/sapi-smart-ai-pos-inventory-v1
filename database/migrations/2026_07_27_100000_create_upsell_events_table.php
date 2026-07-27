<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris = satu saran upsell yang benar-benar dilihat manusia, beserta
     * nasibnya (diambil atau diabaikan).
     *
     * Tanpa tabel ini pertanyaan "apakah upsell-nya menaikkan penjualan atau
     * hanya memperlambat antrean" tidak akan pernah terjawab — dan menambal
     * pencatatan belakangan berarti kehilangan periode awal justru saat datanya
     * paling dibutuhkan.
     */
    public function up(): void
    {
        Schema::create('upsell_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // nullOnDelete, BUKAN cascade: menghapus transaksi tidak boleh
            // diam-diam memperbaiki angka konversi.
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();

            $table->string('type');       // attach | pressed_stock | upsize
            $table->string('surface');    // pos | self_order
            $table->string('status');     // accepted | ignored
            $table->string('reason')->nullable(); // cooccurrence | catalog | near_expiry | dead_stock | price_step

            $table->foreignId('trigger_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('suggested_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('suggested_modifier_id')->nullable()->constrained('modifiers')->nullOnDelete();

            // Snapshot teks yang dilihat kasir — bertahan meski produk/modifier
            // sudah dihapus, sama alasannya dengan transaction_items.variant_name.
            $table->string('label');

            // Tambahan omzet bila diterima; 0 bila diabaikan.
            $table->decimal('extra_amount', 12, 2)->default(0);

            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upsell_events');
    }
};
