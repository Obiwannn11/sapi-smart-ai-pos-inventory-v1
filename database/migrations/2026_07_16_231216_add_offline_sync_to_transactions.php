<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Channel: bagaimana transaksi masuk ke sistem.
            // Sengaja kolom string BARU, bukan value tambahan pada enum `source`
            // — `source` (pos|self_order) menjawab "dari mana pesanan datang",
            // channel menjawab "online atau tercatat offline". Keduanya ortogonal:
            // penjualan POS offline = source `pos` + channel `offline`.
            // Mengubah enum juga bermasalah di SQLite yang dipakai test suite.
            $table->string('channel', 20)
                ->default('online')
                ->after('table_number');

            // Waktu transaksi SEBENARNYA terjadi (jam perangkat kasir).
            // Beda dari created_at (kapan baris dibuat di server saat sync).
            // Laporan memakai ini agar penjualan offline masuk ke hari yang benar.
            $table->timestamp('occurred_at')
                ->nullable()
                ->after('channel');

            // Kapan transaksi offline berhasil tersinkron. null untuk transaksi online.
            $table->timestamp('synced_at')
                ->nullable()
                ->after('occurred_at');

            // null = sehat. 'needs_review' = ada anomali (stok minus, harga beda,
            // variant hilang) yang perlu dikoreksi owner.
            $table->string('sync_status', 20)
                ->nullable()
                ->after('synced_at');

            // Perangkat asal transaksi offline — untuk menelusuri sumber anomali
            // saat beberapa till menjual offline bersamaan.
            $table->string('device_id', 64)
                ->nullable()
                ->after('sync_status');

            // Owner memfilter "yang perlu dikoreksi" per tenant; tanpa index ini
            // jadi full scan pada tabel yang tumbuh paling cepat di aplikasi.
            $table->index(['tenant_id', 'sync_status']);

            // Laporan mengelompokkan per occurred_at (fallback created_at).
            $table->index(['tenant_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'sync_status']);
            $table->dropIndex(['tenant_id', 'occurred_at']);

            $table->dropColumn([
                'channel',
                'occurred_at',
                'synced_at',
                'sync_status',
                'device_id',
            ]);
        });
    }
};
