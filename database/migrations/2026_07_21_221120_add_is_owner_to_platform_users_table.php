<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda pemilik SaaS di antara akun platform.
     *
     * Sengaja kolom penanda, BUKAN modul yang bisa dicentang. Kalau "kelola akun
     * platform" jadi modul biasa, staf platform yang diberi modul itu bisa
     * mencentang `revenue_data` untuk dirinya sendiri — dan pemisahan modul
     * sensitif jadi tak ada artinya. Ini mengikuti pola yang sudah dipakai di
     * sisi tenant: manajemen staf/role dijaga `role:owner`, bukan permission
     * grantable (lihat catatan di config/rbac.php).
     */
    public function up(): void
    {
        Schema::table('platform_users', function (Blueprint $table) {
            $table->boolean('is_owner')->default(false)->after('password');
        });

        // Isi mundur untuk pemasangan yang sudah menjalankan seeder Tahap A.
        // Tanpa ini kolomnya default false untuk semua, sehingga instalasi lama
        // berakhir tanpa satu pun pemilik — dan tak seorang pun bisa membuka
        // manajemen akun, termasuk untuk mengangkat pemilik baru.
        $firstAccountId = DB::table('platform_users')->orderBy('id')->value('id');

        if ($firstAccountId !== null) {
            DB::table('platform_users')->where('id', $firstAccountId)->update(['is_owner' => true]);

            // Seeder Tahap A sempat menuliskan seluruh modul untuk akun ini.
            // Sejak ada penanda is_owner, baris itu tak lagi menentukan apa pun
            // — dibiarkan justru menyesatkan: tampak bisa dicabut padahal
            // aksesnya tetap penuh.
            DB::table('platform_user_modules')->where('platform_user_id', $firstAccountId)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('platform_users', function (Blueprint $table) {
            $table->dropColumn('is_owner');
        });
    }
};
