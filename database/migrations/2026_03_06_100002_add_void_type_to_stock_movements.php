<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite treats ENUM as TEXT, so no ALTER needed
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('sale', 'restock', 'adjustment', 'void') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('sale', 'restock', 'adjustment') NOT NULL");
    }
};
