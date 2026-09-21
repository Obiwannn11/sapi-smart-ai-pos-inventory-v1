<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tagihan per periode. Di v1 pembayaran dicatat MANUAL: tenant mengunggah
     * bukti transfer, pemilik SaaS memverifikasi belakangan. Belum ada payment
     * gateway.
     *
     * `status` berupa string, bukan enum, mengikuti alasan yang sama seperti
     * `platform_audit_logs.severity`: SQLite yang dipakai test suite menyulitkan
     * perubahan enum, dan status baru bisa saja muncul.
     *
     * Nilai yang dikenal ada di App\Models\Invoice.
     *
     * `verified_by` menunjuk ke `platform_users` — yang memverifikasi adalah
     * pemilik SaaS, bukan pengguna tenant. `nullOnDelete` supaya menghapus akun
     * platform tidak ikut menghapus riwayat tagihan; jejak siapa yang
     * memverifikasi tetap ada di `platform_audit_logs`.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('period', 7);
            $table->decimal('amount', 12, 2);
            $table->string('status', 30)->default('unpaid');
            $table->date('due_date');
            $table->string('proof_path')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'period']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
