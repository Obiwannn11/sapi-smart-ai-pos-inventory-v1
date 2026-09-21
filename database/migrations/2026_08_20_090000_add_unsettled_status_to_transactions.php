<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Umur tagihan terbuka: 24 jam, lalu jadi kas negatif ([BL-031]).
 *
 * `unsettled` adalah keadaan KEEMPAT, bukan salah satu dari tiga yang sudah
 * ada, dan itu disengaja: ia bukan `voided` (barangnya sudah keluar, stok
 * TIDAK dipulihkan) dan bukan `completed` (uangnya tidak pernah masuk).
 *
 * Menjadikannya status — bukan sekadar penanda di samping `pending` — membuat
 * setiap penjaga `!== STATUS_PENDING` yang sudah tersebar di aplikasi ini
 * (`payOpenBill`, API mobile, webhook Xendit) otomatis MENOLAK tagihan yang
 * sudah lewat. Penanda terpisah akan membuat semuanya diam-diam tetap
 * menerima, dan tiap tempat harus diingat satu per satu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('unsettled_at')->nullable()->after('edited_by');

            // Penyaring sapuan terjadwal maupun rekapnya selalu lewat pasangan
            // ini; tanpa indeks, perintah per jam memindai seluruh tabel.
            $table->index(['tenant_id', 'status', 'unsettled_at'], 'transactions_unsettled_idx');
        });

        // SQLite memperlakukan ENUM sebagai TEXT, jadi tidak ada yang perlu
        // diubah di sana — pola yang sama dipakai saat `edit` masuk ke
        // stock_movements.type.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'completed', 'voided', 'unsettled') NOT NULL");
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_unsettled_idx');
            $table->dropColumn('unsettled_at');
        });

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'completed', 'voided') NOT NULL");
    }
};
