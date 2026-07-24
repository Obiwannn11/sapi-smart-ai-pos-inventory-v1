<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bukti persetujuan tenant atas dokumen jalur harganya.
     *
     * `version` WAJIB disimpan, bukan sekadar tanggal. Teks persetujuan pasti
     * berubah, dan "dia dulu setuju" tidak berarti apa-apa kalau tidak bisa
     * ditunjukkan setuju pada teks yang MANA. Teksnya sendiri disimpan sebagai
     * berkas berversi di `resources/consents/`, tidak pernah disunting di
     * tempat — versi baru berarti berkas baru.
     *
     * Barisnya tidak pernah diperbarui saat dicabut; `revoked_at` diisi dan
     * barisnya tetap tinggal. Riwayat persetujuan yang bisa hilang bukan bukti.
     */
    public function up(): void
    {
        Schema::create('tenant_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // Siapa yang menyetujui — wajib owner, ditegakkan di controller.
            // `nullOnDelete` agar menghapus akun tidak menghapus buktinya.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('version', 20);
            $table->timestamp('agreed_at');
            $table->string('ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_consents');
    }
};
