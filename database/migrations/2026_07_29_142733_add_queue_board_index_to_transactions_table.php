<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migrasi terpisah dari penambahan kolom supaya niatnya jelas.
 *
 * Query papan menyaring `tenant_id` + `fulfillment_status` lalu mengurutkan
 * `sort_index` — persis bentuk indeks ini. Tanpa indeks, papan yang di-poll
 * tiap 5 detik oleh setiap kasir aktif menjadi pemindaian tabel berulang pada
 * tabel yang justru paling cepat tumbuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['tenant_id', 'fulfillment_status', 'sort_index'], 'transactions_queue_board_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_queue_board_index');
        });
    }
};
