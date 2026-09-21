<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uang transaksi mendapat angka keempatnya ([BL-097] Tahap 1).
     *
     * Invarian `[BL-065]` butir 8 ditulis ulang jadi:
     *
     *     subtotal + service_charge + pajak = total, TEPAT.
     *
     * Empat angka, dua yang dibulatkan (biaya layanan dan pajak), dan tetap
     * TEPAT SATU yang diturunkan dengan pengurangan — jangkarnya tetap
     * berbeda per mode, persis seperti sebelum kolom ini ada.
     *
     * **`service_charge_amount` TIDAK pernah dilebur ke `subtotal_amount`**,
     * dan itu keputusan yang menanggung seluruh taruhan entri ini. Selama ia
     * kolomnya sendiri, pertanyaan "apakah biaya layanan itu pendapatan toko"
     * cuma soal penurunan angka di `ProfitService` dan `ReportController` —
     * bisa dibalik kapan saja tanpa migrasi dan tanpa menyentuh satu baris
     * transaksi lama. Meleburnya membuat jawaban itu permanen.
     *
     * Bawaannya `[BL-097]` jawaban 3: biaya layanan BUKAN pendapatan toko.
     * Karena kolomnya terpisah, `net_revenue = SUM(subtotal_amount)` yang
     * sudah berjalan sejak `[BL-065]` mengecualikannya dengan sendirinya —
     * tidak ada satu pun query yang perlu diubah untuk mendapatkannya.
     *
     * Dua kolom konteks di bawah MEMBEKUKAN setelan pada saat penjualan,
     * mengikuti pola `tax_rate`/`tax_label`. Tanpa itu, mengedit penjualan
     * bulan lalu akan menghitung ulang dengan tarif hari ini, dan struk cetak
     * ulang berbeda dari kertas yang dipegang pelanggan.
     *
     * Semua `nullable`/`default 0`: `null` berarti transaksi ini lahir sebelum
     * biaya layanan ada, yang berbeda artinya dari "biaya layanannya nol
     * persen". Tidak ada backfill — 0 memang jawaban yang benar untuk seluruh
     * masa sebelum kolom ini lahir, bukan tebakan yang menyenangkan pembaca.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // default(0) bukan kelalaian: menambah kolom NOT NULL tanpa
            // default ke tabel berisi data gagal di SQLite, dan test suite
            // berjalan di sana.
            $table->decimal('service_charge_amount', 12, 2)->default(0)->after('tax_amount');

            $table->decimal('service_charge_rate', 5, 2)->nullable()->after('tax_label');
            $table->string('service_charge_label', 30)->nullable()->after('service_charge_rate');
        });
    }

    public function down(): void
    {
        // SQLite di bawah 3.35 menjatuhkan kolom satu per satu dengan
        // membangun ulang tabel; menyerahkan ketiganya sekaligus ke Laravel
        // sudah benar di MySQL dan di SQLite modern.
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'service_charge_amount', 'service_charge_rate', 'service_charge_label',
            ]);
        });
    }
};
