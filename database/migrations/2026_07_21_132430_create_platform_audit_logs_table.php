<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak audit tindakan pemilik SaaS.
     *
     * Inilah yang membuat janji "saya tidak melihat data Anda" bisa dibuktikan,
     * bukan sekadar diklaim. Bersifat append-only: tidak ada `updated_at` dan
     * tidak disediakan UI ubah/hapus — log yang bisa disunting tak membuktikan
     * apa pun.
     *
     * `platform_user_id` nullable agar percobaan login yang gagal (belum ada
     * akun yang teridentifikasi) tetap bisa dicatat.
     */
    public function up(): void
    {
        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_user_id')->nullable()->constrained('platform_users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
    }
};
