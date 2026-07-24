<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Upgrade provisional: tenant menambah seat, mengunggah bukti transfer, dan
     * paketnya langsung berlaku sebelum diperiksa.
     *
     * `kind` memisahkan tagihan langganan bulanan dari tagihan upgrade. Tanpa
     * itu indeks unik (tenant_id, period) akan menolak upgrade di bulan yang
     * sudah punya tagihan langganan — dan tenant yang butuh kasir tambahan di
     * pertengahan bulan tidak akan bisa membayar sama sekali.
     *
     * `previous_seats` adalah jalan pulang bila buktinya ditolak: seat kembali
     * ke angka semula. Akun staf yang terlanjur dibuat TIDAK ikut dinonaktifkan
     * — jumlah aktif jadi melampaui seat, dan itu menutup penambahan berikutnya
     * dengan sendirinya tanpa mengusir siapa pun dari pekerjaannya.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('kind', 20)->default('subscription')->after('period');
            $table->unsignedSmallInteger('grants_seats')->nullable()->after('kind');
            $table->unsignedSmallInteger('previous_seats')->nullable()->after('grants_seats');

            // Indeks BARU dibuat lebih dulu, baru yang lama dibuang. Urutan
            // sebaliknya gagal di MySQL: foreign key `tenant_id` bersandar pada
            // indeks unik lama sebagai satu-satunya indeks yang diawali kolom
            // itu, jadi membuangnya duluan membuat MySQL menolak (errno 150).
            $table->unique(['tenant_id', 'period', 'kind']);
            $table->dropUnique(['tenant_id', 'period']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            // Sekali bukti bayar ditolak, fasilitas "berlaku dulu, diperiksa
            // belakangan" dicabut untuk tenant itu. Ia tetap boleh membayar dan
            // tetap boleh naik paket — hanya saja seat-nya baru berlaku setelah
            // buktinya benar-benar diperiksa.
            $table->boolean('provisional_blocked')->default(false)->after('seat_high_water');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('provisional_blocked');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->unique(['tenant_id', 'period']);
            $table->dropUnique(['tenant_id', 'period', 'kind']);
            $table->dropColumn(['kind', 'grants_seats', 'previous_seats']);
        });
    }
};
