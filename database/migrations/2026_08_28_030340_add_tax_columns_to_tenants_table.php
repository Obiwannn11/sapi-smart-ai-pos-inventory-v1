<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setelan pajak milik tenant ([BL-065]).
     *
     * Bawaannya MATI, dan itu bukan sikap hati-hati melainkan jawaban yang
     * benar untuk hampir semua pengguna: kewajiban memungut PPN baru lahir
     * setelah omzet melewati Rp 4,8 miliar setahun, dan di bawah itu tenant
     * berstatus pengusaha kecil yang memang tidak wajib memungut apa pun.
     * Tenant yang menyalakannya adalah pengecualian, bukan mayoritas.
     *
     * `tax_mode` disimpan sebagai `string`, BUKAN `enum`. Bukan selera:
     * mengubah enum di SQLite — yang dipakai test suite — menuntut membangun
     * ulang tabelnya, dan tabel `tenants` bukan tempat untuk itu.
     *
     * `tax_label` sengaja NULLABLE tanpa default. Kolom ini memilih kata yang
     * TERCETAK di struk pelanggan, dan katanya menyebut dasar hukum yang
     * berbeda: PPN dipungut untuk negara, PB1/PBJT untuk daerah. Kafe yang
     * mencetak "PPN 10%" atas pungutan yang sebenarnya PBJT sedang salah
     * menyebut dasar hukum. Memberinya default berarti menebak — persis yang
     * `[BL-079]` baru saja hentikan untuk `business_type`. `null` berarti
     * "belum dijawab", dan validasi menolak menyalakan pajak selama masih null.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('tax_enabled')->default(false)->after('order_identity_mode');

            // 'exclusive' (pajak ditambahkan di atas harga) atau 'inclusive'
            // (harga katalog sudah mengandungnya). Nilainya tidak berarti apa
            // pun selama `tax_enabled` mati; ia baru mengunci saat transaksi
            // berpajak pertama lahir.
            $table->string('tax_mode', 10)->default('exclusive')->after('tax_enabled');

            $table->decimal('tax_rate', 5, 2)->default(0)->after('tax_mode');

            $table->string('tax_label', 20)->nullable()->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['tax_enabled', 'tax_mode', 'tax_rate', 'tax_label']);
        });
    }
};
