<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Perpindahan jalur harga: kapan terakhir pindah, dan kapan akan kembali.
     *
     * `track_changed_at` menegakkan jarak minimum antar perpindahan. Tanpa
     * jarak itu, tenant bisa pindah ke subsidi tiap bulan sepi lalu balik ke
     * normal — dan bracket yang dihitung dari omset bulanan kehilangan artinya.
     *
     * `track_reverts_at` adalah janji yang tertulis di dokumen persetujuan:
     * mencabut consent TIDAK langsung menaikkan tagihan. Harga subsidi berlaku
     * sampai periode berjalan habis, baru setelah itu kembali normal.
     * Pencabutan yang seketika menaikkan tagihan membuat orang takut mencabut —
     * dan consent yang tidak bisa dicabut dengan tenang bukan consent.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('track_changed_at')->nullable()->after('pricing_track');
            $table->date('track_reverts_at')->nullable()->after('track_changed_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['track_changed_at', 'track_reverts_at']);
        });
    }
};
