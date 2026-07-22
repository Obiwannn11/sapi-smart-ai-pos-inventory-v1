<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Derajat kejadian: `routine` (akses baca berulang) vs `sensitive`
     * (perubahan keadaan, data bisnis klien, kejadian keamanan).
     *
     * Dipakai untuk dua hal: menyaring kebisingan di halaman log, dan
     * menentukan berapa lama barisnya disimpan. Garisnya dijelaskan di
     * config/platform-audit.php.
     *
     * Kolom string, bukan enum: SQLite yang dipakai test suite menyulitkan
     * perubahan enum di kemudian hari, dan derajat baru bisa saja muncul.
     */
    public function up(): void
    {
        Schema::table('platform_audit_logs', function (Blueprint $table) {
            $table->string('severity', 20)->default('sensitive')->after('action');
            $table->index(['severity', 'created_at']);
        });

        // Isi mundur baris yang sudah ada. Default kolom sengaja `sensitive`
        // supaya kejadian yang belum terklasifikasi tidak diam-diam terbuang
        // lebih cepat oleh pemangkasan; hanya akses baca yang diturunkan.
        DB::table('platform_audit_logs')
            ->whereIn('action', ['tenants.index', 'login.success', 'logout'])
            ->update(['severity' => 'routine']);
    }

    public function down(): void
    {
        Schema::table('platform_audit_logs', function (Blueprint $table) {
            $table->dropIndex(['severity', 'created_at']);
            $table->dropColumn('severity');
        });
    }
};
