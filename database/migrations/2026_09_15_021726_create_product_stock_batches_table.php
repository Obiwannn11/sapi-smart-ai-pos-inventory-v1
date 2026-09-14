<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris = satu kedatangan barang, dengan tanggal kedaluwarsanya sendiri
     * ([BL-111]).
     *
     * Sebelum tabel ini ada, `product_variants.stock` dan `expiry_date` adalah
     * kolom TUNGGAL: 20 unit yang basi April dan 30 unit yang basi Agustus
     * tersimpan sebagai "50 unit, basi Agustus", dan setiap restock menimpa
     * tanggal batch sebelumnya tanpa jejak. FEFO tidak mungkin ditulis karena
     * tidak ada apa pun untuk dipilih.
     *
     * `product_variants.stock` TETAP ADA dan tetap jadi angka yang dibaca
     * seluruh aplikasi — ia kini jumlah `qty_remaining` di sini, dijaga
     * `StockBatchService`. `expiry_date` di varian ikut jadi turunan: tanggal
     * paling awal di antara batch yang masih bersisa.
     */
    public function up(): void
    {
        Schema::create('product_stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // cascadeOnDelete: batch tanpa varian bukan stok siapa pun. Varian
            // sehari-hari hanya di-soft-delete, jadi jalur ini praktis hanya
            // dilalui saat tenantnya sendiri dihapus.
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();

            // NULL = barang yang memang tidak punya kedaluwarsa (gelas plastik,
            // kopi kiloan), bukan "belum diisi". Batch seperti ini dijual
            // SESUDAH batch bertanggal — ia tidak sedang berpacu dengan waktu.
            $table->date('expiry_date')->nullable();

            // `qty_received` tidak pernah berubah setelah ditulis; `qty_remaining`
            // yang bergerak. Tanpa yang pertama, "dari 30 yang datang, 12 basi di
            // rak" tidak bisa dijawab.
            $table->integer('qty_received');
            $table->integer('qty_remaining');

            $table->timestamp('received_at');

            /**
             * Dari mana batch ini lahir.
             *
             * `opening`    — stok yang sudah ada saat varian dibuat, atau saat
             *                tabel ini lahir (lihat backfill di bawah).
             * `restock`    — penerimaan barang lewat halaman Stok.
             * `adjustment` — koreksi naik yang tidak punya batch untuk ditumpangi.
             * `reconcile`  — selisih yang ditulis langsung ke `stock` tanpa lewat
             *                layanan stok (formulir varian, seeder). Kalau jenis
             *                ini sering muncul, ada penulis yang melewati pintu.
             */
            $table->string('source', 20)->default('restock');

            $table->timestamps();

            // Pembaca terpanas: "batch mana yang masih bersisa untuk varian ini,
            // urut kedaluwarsa". Dipakai setiap penjualan.
            $table->index(['product_variant_id', 'qty_remaining', 'expiry_date'], 'stock_batches_fefo_index');
            $table->index(['tenant_id', 'expiry_date']);
        });

        /**
         * Batch mana yang disentuh sebuah mutasi stok, dan sebanyak apa.
         *
         * Tabel ini yang membuat void jujur. Tanpanya, 3 croissant basi yang
         * terjual lalu dibatalkan akan kembali ke rak sebagai stok tanpa
         * tanggal — dan dijual lagi tanpa konfirmasi, lewat pintu belakang yang
         * persis ingin ditutup `[BL-108]`.
         *
         * `qty` bertanda, searah dengan `stock_movements.qty`: negatif berarti
         * diambil dari batch itu, positif berarti dikembalikan atau diterima.
         */
        Schema::create('stock_movement_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_movement_id')->constrained('stock_movements')->cascadeOnDelete();
            $table->foreignId('product_stock_batch_id')->constrained('product_stock_batches')->cascadeOnDelete();
            $table->integer('qty');

            // Nama eksplisit: nama bawaan Laravel untuk indeks ini 69 karakter,
            // dan MySQL menolak pengenal di atas 64.
            $table->index(['product_stock_batch_id', 'stock_movement_id'], 'stock_movement_batches_batch_index');
        });

        $this->openOneBatchPerVariantThatHasStock();
    }

    /**
     * Setiap varian yang punya stok hari ini jadi satu batch `opening`.
     *
     * Tanggalnya diambil dari `product_variants.expiry_date` apa adanya — satu-
     * satunya tanggal yang pernah tersimpan. Tanggal batch-batch sebelumnya
     * sudah tertimpa sebelum migrasi ini ada dan tidak bisa dikarang kembali.
     *
     * Ditulis sebagai SQL di dalam migrasi, bukan memanggil `StockBatchService`:
     * migrasi harus tetap berarti sama bertahun-tahun kemudian, dan service boleh
     * berubah kapan saja. Varian yang sudah dihapus ikut, karena penjualan
     * offline dan void masih bisa menyentuhnya.
     */
    private function openOneBatchPerVariantThatHasStock(): void
    {
        $now = now();

        DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('product_variants.stock', '>', 0)
            ->select([
                'products.tenant_id',
                'product_variants.id as variant_id',
                'product_variants.stock',
                'product_variants.expiry_date',
            ])
            ->orderBy('product_variants.id')
            ->chunk(500, function ($variants) use ($now) {
                $rows = [];

                foreach ($variants as $variant) {
                    $rows[] = [
                        'tenant_id' => $variant->tenant_id,
                        'product_variant_id' => $variant->variant_id,
                        'expiry_date' => $variant->expiry_date,
                        'qty_received' => $variant->stock,
                        'qty_remaining' => $variant->stock,
                        'received_at' => $now,
                        'source' => 'opening',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('product_stock_batches')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movement_batches');
        Schema::dropIfExists('product_stock_batches');
    }
};
