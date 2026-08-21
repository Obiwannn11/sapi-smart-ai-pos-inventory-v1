<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda sesi kas yang ditutup paksa oleh sistem ([BL-088]).
 *
 * Perlu kolom sendiri, bukan diturunkan dari `closing_amount === null`:
 * keduanya kebetulan sama hari ini, tapi menyandarkan artinya pada kebetulan
 * itu berarti sesi mana pun yang kelak boleh ditutup tanpa hitungan akan
 * terbaca sebagai "ditutup sistem" tanpa satu baris kode pun berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_drawers', function (Blueprint $table) {
            $table->boolean('closed_by_system')->default(false)->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('cash_drawers', function (Blueprint $table) {
            $table->dropColumn('closed_by_system');
        });
    }
};
