<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Source: asal pesanan
            $table->enum('source', ['pos', 'self_order'])
                  ->default('pos')
                  ->after('notes');

            // Order type: tipe pesanan
            $table->enum('order_type', ['dine_in', 'pickup'])
                  ->default('dine_in')
                  ->after('source');

            // Fulfillment status: tracking penyajian
            // null = tidak perlu tracking (POS bayar langsung)
            // waiting/preparing/ready/done = aktif tracking
            $table->enum('fulfillment_status', ['waiting', 'preparing', 'ready', 'done'])
                  ->nullable()
                  ->after('order_type');

            // Customer name: nama customer (structured, bukan di notes)
            $table->string('customer_name', 100)
                  ->nullable()
                  ->after('fulfillment_status');

            // Table number: nomor meja untuk dine-in (free text, max 10 char)
            $table->string('table_number', 10)
                  ->nullable()
                  ->after('customer_name');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'source',
                'order_type',
                'fulfillment_status',
                'customer_name',
                'table_number',
            ]);
        });
    }
};
