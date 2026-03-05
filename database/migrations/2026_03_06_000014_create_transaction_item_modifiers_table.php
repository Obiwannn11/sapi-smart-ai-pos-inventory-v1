<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_item_id')->constrained('transaction_items')->cascadeOnDelete();
            $table->foreignId('modifier_id')->constrained('modifiers');
            $table->string('modifier_name');
            $table->decimal('extra_price', 12, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_item_modifiers');
    }
};
