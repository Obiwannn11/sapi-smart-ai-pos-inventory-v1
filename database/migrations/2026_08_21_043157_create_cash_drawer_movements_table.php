<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uang yang keluar-masuk laci di tengah sesi ([BL-087]).
 *
 * Sebelum ini rumus `expected_amount` hanya mengenal modal, penjualan tunai,
 * dan kembalian — ketiganya diturunkan dari transaksi penjualan. Uang yang
 * diambil pemilik untuk setoran, atau dipakai kasir membeli galon, tidak punya
 * jalan masuk ke rumus itu dan menghilang sebagai selisih kurang yang
 * ditanggung kasir.
 *
 * **Baris di sini adalah satu-satunya angka kas yang sumbernya ucapan
 * manusia.** Setiap angka lain punya pembanding: penjualan tunai dan kembalian
 * datang dari `transactions`, modal awal dihitung ulang saat tutup kas. Karena
 * itu ia punya `status`: keputusan pemilik 2026-08-21 memilih pencatatan bebas
 * (siapa pun kasir boleh) dengan efeknya yang tertahan — di atas ambang
 * `tenants.cash_payout_approval_threshold`, barisnya tercatat dan terlihat
 * tapi TIDAK menggerakkan `expected_amount` sampai pemilik menyetujuinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_drawer_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('cash_drawer_id')->constrained('cash_drawers')->cascadeOnDelete();
            // Pencatatnya, bukan pemilik lacinya: pemilik yang mencatat
            // pengambilan setoran dari laci kasirnya tercatat sebagai dirinya.
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('type', ['payout', 'deposit']);
            $table->decimal('amount', 12, 2);
            // Wajib, dan panjangnya sama dengan `discount_reason` di
            // `transaction_items` — alasan tertulis adalah satu-satunya hal
            // yang membedakan pencatatan ini dari uang yang hilang begitu saja.
            $table->string('reason', 200);

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            // NULL saat disetujui otomatis karena di bawah ambang. Kombinasi
            // "status approved + reviewed_by null" itulah yang membedakan
            // persetujuan otomatis dari persetujuan yang benar-benar dibaca
            // pemilik — dan pemilik perlu bisa membedakannya saat menelusuri.
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            // Pola bacanya dua: seluruh mutasi satu sesi (rekonsiliasi), dan
            // seluruh yang menunggu persetujuan lintas sesi (layar pemilik).
            $table->index(['cash_drawer_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_drawer_movements');
    }
};
