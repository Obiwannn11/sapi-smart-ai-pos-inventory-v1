<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aturan saran jual yang ditulis OWNER, bukan diturunkan mesin ([BL-074]).
     *
     * Tiga strategi yang ada semuanya menemukan sarannya sendiri dari data —
     * ko-okurensi modifier, barang tertekan stok, selisih harga naik ukuran.
     * Tidak ada satu pun tempat bagi orang yang paling tahu barangnya sendiri
     * untuk mengatakan "bulan ini dorong kopi susu botol". Tabel ini tempatnya.
     */
    public function up(): void
    {
        Schema::create('upsell_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // NULL = aturan tanpa pemicu: ditawarkan pada setiap keranjang yang
            // tidak kosong, apa pun isinya. Itulah yang membedakan "kalau beli
            // nasi goreng, tawarkan teh manis" dari "bulan ini dorong kopi susu
            // botol" — dan keduanya diminta pemilik dengan kalimat yang sama.
            //
            // Pemicunya VARIAN, bukan produk atau kategori (keputusan pemilik
            // 2026-08-19). Yang paling longgar paling mahal di sisi penyaringan
            // client, dan penyaringan itu berjalan tiap klik di perangkat kasir
            // yang paling lemah.
            $table->foreignId('trigger_variant_id')->nullable()
                ->constrained('product_variants')->cascadeOnDelete();

            // cascadeOnDelete, bukan nullOnDelete: aturan tanpa barang yang
            // disarankan bukan aturan. Berbeda dari `upsell_events`, yang
            // memang harus bertahan setelah produknya dihapus karena ia catatan
            // sejarah, bukan konfigurasi.
            $table->foreignId('suggested_variant_id')
                ->constrained('product_variants')->cascadeOnDelete();

            // Alasan yang ditulis owner dan dibaca kasir apa adanya. Mesin
            // menuliskan alasannya sendiri ("Kedaluwarsa 3 hari lagi"); di sini
            // manusia yang menulisnya, dan itu justru gunanya.
            $table->string('note', 120)->nullable();

            // Jendela berlaku. Keduanya nullable — aturan tanpa tanggal berlaku
            // selamanya sampai dimatikan. Pemilik meminta bisa disetel dari
            // jauh hari ("promo setiap tanggal kembar, disetting hari-hari
            // sebelumnya"), jadi `starts_on` di masa depan harus sah.
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            // Urutan antar aturan manual saat slotnya lebih sedikit dari
            // aturannya. TIDAK dipakai untuk beradu dengan skor mesin — soal
            // itu diselesaikan lantai skor di ManualRuleStrategy.
            $table->unsignedSmallInteger('priority')->default(0);

            // Saklar per aturan. Pemilik memintanya eksplisit: "bisa di-select
            // mau aktifkan saran yang mana untuk dimunculkan". Mematikan lebih
            // sering yang diinginkan daripada menghapus — promo yang sama
            // kembali tahun depan.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Jalur baca satu-satunya: seluruh aturan aktif milik satu tenant,
            // dirakit sekali per pembangunan indeks.
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upsell_rules');
    }
};
