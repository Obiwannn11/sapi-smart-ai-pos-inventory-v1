<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Nomor pendek untuk dipanggil ("nomor 12!"), reset harian per
            // tenant. `code` (TRX-YYYYMMDD-XXX) terlalu panjang untuk
            // diteriakkan. BUKAN identitas — hanya label tampilan; identitas
            // tetap `id`/`code`. Fase offline tidak akan bisa menjamin nomor
            // unik lintas perangkat, jadi apa pun yang menjadikan nomor ini
            // kunci akan menghalangi fase itu.
            $table->unsignedInteger('queue_number')->nullable()->after('fulfillment_status');

            // Kunci urutan papan. Diisi dari effectiveDate()->getTimestampMs(),
            // bukan now(): penjualan offline disinkronkan belakangan, sehingga
            // now() saat sync akan melemparkannya ke dasar papan padahal
            // pesanannya datang paling awal.
            $table->unsignedBigInteger('sort_index')->nullable()->after('queue_number');

            // Dasar timer "sudah menunggu berapa lama", sekaligus bahan
            // analitik rata-rata waktu masak. Murah, jadi diambil sekarang.
            $table->timestamp('preparing_at')->nullable()->after('sort_index');
            $table->timestamp('ready_at')->nullable()->after('preparing_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['queue_number', 'sort_index', 'preparing_at', 'ready_at']);
        });
    }
};
