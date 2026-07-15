<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');   // yang mengedit
            $table->string('reason')->nullable();                 // alasan edit (opsional tapi didorong)
            $table->json('before');                               // snapshot items+payments+total sebelum
            $table->json('after');                                // snapshot sesudah
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_edits');
    }
};
