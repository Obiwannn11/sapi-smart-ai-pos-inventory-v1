<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu percobaan pembayaran lewat payment gateway — `[BL-059]`.
     *
     * Tabel sendiri, bukan kolom tambahan di `invoices`, karena satu tagihan
     * wajar punya BEBERAPA percobaan: yang pertama kedaluwarsa, yang kedua
     * berganti kanal, yang ketiga akhirnya lunas. `invoices` sudah unik per
     * `(tenant_id, period, kind)` sehingga tidak mungkin menampung riwayat itu,
     * dan menimpanya berarti membuang jejak percobaan yang gagal — justru yang
     * paling perlu dibaca ketika tenant mengaku sudah membayar.
     *
     * `external_id` adalah nomor transaksi di sisi penyedia. Uniknya berpasangan
     * dengan `gateway`, bukan berdiri sendiri: dua penyedia boleh saja memakai
     * penomoran yang bertabrakan, dan indeks inilah yang membuat notifikasi
     * berulang tidak pernah melunasi tagihan dua kali.
     *
     * `payload` menyimpan jawaban mentah penyedia (nomor VA, isi QR, notifikasi
     * terakhir). Ia sengaja apa adanya: ketika sebuah pembayaran disengketakan,
     * yang menjawab adalah apa yang benar-benar dikirim penyedia, bukan
     * ringkasan yang sudah kita terjemahkan.
     *
     * `tenant_id` ditulis ulang di sini meski bisa ditelusuri lewat `invoice_id`
     * — webhook berjalan TANPA konteks tenant (tak ada pengguna yang masuk, dan
     * `TenantScope` tidak aktif di sana), jadi tenant harus bisa ditentukan dari
     * baris ini sendiri, bukan dari sesi yang kebetulan sedang berjalan.
     */
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 30);
            $table->string('channel', 40);
            $table->string('external_id', 100);
            $table->decimal('amount', 12, 2);
            // String, bukan enum: alasan yang sama seperti `invoices.status` —
            // SQLite yang dipakai test suite menyulitkan perubahan enum, dan
            // keadaan baru pasti muncul begitu gateway sungguhan dipasang.
            // Nilai yang dikenal ada di App\Models\PaymentAttempt.
            $table->string('status', 30)->default('pending');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'external_id']);
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
