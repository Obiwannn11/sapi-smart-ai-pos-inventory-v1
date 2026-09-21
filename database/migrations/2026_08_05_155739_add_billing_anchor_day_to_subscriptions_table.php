<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tanggal tagih sebagai JANGKAR, bukan sebagai turunan periode sebelumnya.
 *
 * Tanpa kolom ini, satu-satunya cara menghitung periode berikutnya adalah dari
 * akhir periode sebelumnya — dan begitu sebuah periode berakhir di bulan pendek,
 * tanggalnya sudah terjepit dan angka aslinya hilang. Langganan yang tanggal
 * tagihnya 31 turun ke 28 di Februari lalu menetap di 28 selamanya.
 *
 * Menyimpan hari aslinya membuat penjepitan bersifat sementara: 31 Jan → 28 Feb
 * → 31 Mar. Lihat `[BL-030]`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('billing_anchor_day')->nullable()->after('current_period_end');
        });

        // Backfill dari tanggal akhir periode yang berlaku sekarang. Aman
        // dilakukan hari ini justru karena belum ada satu pun tanggal tagih yang
        // pernah terjepit — tidak ada langganan yang jangkarnya perlu ditebak.
        // Yang `current_period_end`-nya kosong dibiarkan null: jangkarnya lahir
        // sendiri saat periode berbayar pertamanya dibuka.
        foreach (DB::table('subscriptions')->select('id', 'current_period_end')->cursor() as $subscription) {
            if ($subscription->current_period_end === null) {
                continue;
            }

            DB::table('subscriptions')
                ->where('id', $subscription->id)
                ->update([
                    'billing_anchor_day' => (int) date('j', strtotime((string) $subscription->current_period_end)),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_anchor_day');
        });
    }
};
