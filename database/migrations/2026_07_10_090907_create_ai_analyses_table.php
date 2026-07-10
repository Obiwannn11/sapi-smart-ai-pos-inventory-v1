<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');       // general|discount|profit_projection|custom
            $table->string('status');     // pending|processing|completed|failed
            $table->json('params')->nullable();     // rentang tanggal, dll
            $table->text('prompt')->nullable();
            $table->longText('result')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_analyses');
    }
};
