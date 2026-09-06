<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setelan biaya layanan milik tenant ([BL-097] Tahap 1).
     *
     * Bentuknya sengaja meniru kolom pajak di sebelahnya, TAPI dengan satu
     * perbedaan yang disengaja: **tidak ada mekanisme penguncian sama sekali**
     * — tidak ada pasangan `service_charge_lock_opened_until`, dan tidak ada
     * pemeriksaan "sudah pernah dipungut". Penguncian `tax_mode` dibeli oleh
     * kewajiban hukum: tenant yang tembus Rp 4,8 M WAJIB memungut, dan riwayat
     * pungutan yang berlubang melanggar sesuatu. Biaya layanan tidak punya
     * keduanya — ia pilihan komersial pemilik toko, yang boleh dinyalakan dan
     * dimatikan kapan pun tanpa melanggar apa pun. Cukup berlaku MAJU dan
     * dibekukan per transaksi.
     *
     * `service_charge_label` sengaja NULLABLE tanpa default, alasan yang sama
     * persis dengan `tax_label`: kata ini TERCETAK di struk pelanggan, dan
     * pilihannya bukan kosmetik. "Biaya Layanan", "Service Charge", dan
     * "Biaya Pelayanan" menyebut hal yang sama dengan nada berbeda, dan yang
     * memilih harus pemilik tokonya. Memberi default berarti menebak — persis
     * yang `[BL-079]` hentikan untuk `business_type`. `null` berarti "belum
     * dijawab", dan validasi menolak menyalakannya selama masih null.
     *
     * Tidak ada `service_charge_mode`. Pajak butuh mode karena harga katalog
     * BISA sudah mengandung pajak; biaya layanan tidak pernah "sudah termasuk"
     * — ia selalu dihitung dari harga katalog dan ditambahkan. Yang berbeda
     * antar mode pajak hanyalah bagaimana pajak diurai SESUDAHNYA, dan itu
     * urusan `TaxCalculator`, bukan kolom di sini.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('service_charge_enabled')->default(false)->after('tax_lock_opened_until');

            $table->decimal('service_charge_rate', 5, 2)->default(0)->after('service_charge_enabled');

            $table->string('service_charge_label', 30)->nullable()->after('service_charge_rate');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'service_charge_enabled', 'service_charge_rate', 'service_charge_label',
            ]);
        });
    }
};
