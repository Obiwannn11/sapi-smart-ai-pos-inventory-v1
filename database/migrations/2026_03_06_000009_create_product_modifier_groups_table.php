<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained('modifier_groups')->cascadeOnDelete();

            $table->unique(['product_id', 'modifier_group_id'], 'product_modifier_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_modifier_groups');
    }
};
