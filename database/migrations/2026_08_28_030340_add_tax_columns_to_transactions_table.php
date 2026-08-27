<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uang transaksi berhenti jadi satu angka ([BL-065]).
     *
     * `total_amount` TIDAK berubah artinya — ia tetap "yang dibayar
     * pelanggan", dan dua belas tempat sudah menjumlahkannya dengan arti itu.
     * Yang ditambahkan adalah dua angka di sebelahnya sehingga pertanyaan
     * "berapa yang benar-benar pendapatan toko" punya jawaban.
     *
     * Tiga kolom konteks di bawah MEMBEKUKAN setelan pajak pada saat
     * penjualan, sama seperti `invoices.pricing_context` membekukan konteks
     * tagihan. Tanpa itu, struk yang dicetak ulang enam bulan kemudian akan
     * dihitung dengan tarif hari ini — dan berbeda dari kertas yang dipegang
     * pelanggan. Dibuat tiga kolom terpisah alih-alih satu JSON karena
     * laporan pajak terpungut perlu mengelompokkannya per tarif.
     *
     * Semua `nullable`: `null` berarti transaksi ini lahir sebelum pajak ada,
     * yang berbeda artinya dari "pajaknya nol persen".
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // default(0) bukan kelalaian: menambah kolom NOT NULL tanpa
            // default ke tabel berisi data gagal di SQLite, dan test suite
            // berjalan di sana. Nilai sebenarnya diisi backfill di bawah.
            $table->decimal('subtotal_amount', 12, 2)->default(0)->after('status');
            $table->decimal('tax_amount', 12, 2)->default(0)->after('subtotal_amount');

            $table->decimal('tax_rate', 5, 2)->nullable()->after('change_amount');
            $table->string('tax_mode', 10)->nullable()->after('tax_rate');
            $table->string('tax_label', 20)->nullable()->after('tax_mode');
        });

        // Untuk seluruh masa sebelum pajak ada, subtotal MEMANG sama dengan
        // total dan pajaknya MEMANG nol. Ini bukan tebakan yang menyenangkan
        // pembaca laporan, melainkan pernyataan yang benar.
        DB::table('transactions')->update([
            'subtotal_amount' => DB::raw('total_amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal_amount', 'tax_amount', 'tax_rate', 'tax_mode', 'tax_label',
            ]);
        });
    }
};
