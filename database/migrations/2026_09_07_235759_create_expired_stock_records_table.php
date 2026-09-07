<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Satu baris = satu varian yang melewati tanggal kedaluwarsanya dengan stok
     * tersisa, beserta nilai modal yang mati bersamanya ([BL-105] butir 2).
     *
     * Tanpa tabel ini pertanyaan "berapa yang basi bulan lalu" TIDAK PERNAH bisa
     * dijawab, dan bukan karena kueri yang belum ditulis. `product_variants.stock`
     * adalah nilai SEKARANG, bukan sejarah: begitu pemilik membuang barangnya dan
     * menyesuaikan stok jadi nol, kerugiannya lenyap tanpa jejak.
     * `stock_movements` tidak menolong — tidak satu pun dari lima jenisnya
     * (`sale`, `restock`, `adjustment`, `void`, `edit`) berarti "dibuang", jadi
     * pembuangan tersamar sebagai `adjustment` bersama koreksi hitung dan barang
     * pecah.
     *
     * Karena itu ia lahir SEBELUM permukaan yang membacanya, persis seperti
     * `upsell_events` dulu ([BL-017] usulan 5): pencatatan yang ditambal
     * belakangan berarti kehilangan periode awal justru saat datanya paling
     * dibutuhkan.
     */
    public function up(): void
    {
        Schema::create('expired_stock_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // nullOnDelete, BUKAN cascade — alasan yang sama dengan
            // `upsell_events.transaction_id`: menghapus varian tidak boleh
            // diam-diam memperbaiki angka kerugian.
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            // Snapshot nama yang dibaca pemilik, bertahan meski variannya sudah
            // hilang. Sama alasannya dengan `transaction_items.variant_name`.
            $table->string('label');

            // Tanggal yang dilewati, dan hari toko saat pencatat mengamatinya.
            // Keduanya ada karena keduanya berbeda pertanyaan: yang pertama
            // "kapan ia basi", yang kedua "kapan kita tahu". Untuk baris
            // `pre_existing` jaraknya bisa berbulan-bulan.
            $table->date('expiry_date');
            $table->date('recorded_on');

            // NULL berarti TIDAK DIKETAHUI, bukan nol. Hanya baris
            // `pre_existing` yang boleh begitu — lihat catatan sumber di bawah.
            $table->integer('qty')->nullable();
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('value', 12, 2)->nullable();

            /**
             * Dari mana baris ini datang, dan ini bukan hiasan administratif.
             *
             * `recorder`     — diamati pencatat harian pada pagi sesudah barang
             *                  itu basi. Jumlahnya benar, dan hanya baris inilah
             *                  yang boleh masuk angka periode.
             * `pre_existing` — barang yang SUDAH basi sebelum pencatat ini ada.
             *                  `qty` sengaja NULL: yang tersisa hari ini bukan
             *                  yang tersisa saat ia basi, dan menuliskan angka
             *                  hari ini di bawah tanggal bulan lalu adalah
             *                  mengarang pengukuran yang tidak pernah terjadi.
             *                  Barisnya tetap ditulis supaya pencatat tidak
             *                  memungutnya besok dan melakukan tepat itu.
             */
            $table->string('source')->default('recorder');

            $table->timestamps();

            // Satu varian, satu tanggal kedaluwarsa, satu baris. Ini penjaga
            // terakhir kalau pencatatnya berjalan dua kali dalam sehari.
            $table->unique(['product_variant_id', 'expiry_date']);
            $table->index(['tenant_id', 'recorded_on']);
        });

        $this->markGoodsThatExpiredBeforeThisRecorderExisted();
    }

    /**
     * Tandai barang yang sudah telanjur basi sebelum pencatat ini lahir.
     *
     * Tanpa langkah ini, sapuan pertama besok pagi akan memungut setiap varian
     * yang kedaluwarsanya sudah lewat — termasuk yang basi berbulan-bulan lalu
     * — lalu menstempelnya dengan stok HARI INI. Akibatnya dua-duanya salah:
     * jumlahnya karangan (stok sudah berubah sejak hari ia basi), dan
     * periodenya salah (kedaluwarsa Juli mendarat di ember September).
     *
     * Ditulis sebagai SQL di dalam migrasi, bukan memanggil `ExpiredStockRecorder`:
     * migrasi harus tetap berarti sama bertahun-tahun kemudian, dan service
     * boleh berubah kapan saja.
     */
    private function markGoodsThatExpiredBeforeThisRecorderExisted(): void
    {
        $today = now()->toDateString();

        DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNull('product_variants.deleted_at')
            ->whereNull('products.deleted_at')
            ->whereNotNull('product_variants.expiry_date')
            ->whereDate('product_variants.expiry_date', '<', $today)
            ->where('product_variants.stock', '>', 0)
            ->select([
                'products.tenant_id',
                'product_variants.id as variant_id',
                'products.name as product_name',
                'product_variants.name as variant_name',
                'product_variants.expiry_date',
            ])
            ->orderBy('product_variants.id')
            ->chunk(200, function ($variants) use ($today) {
                $rows = [];

                foreach ($variants as $variant) {
                    $rows[] = [
                        'tenant_id' => $variant->tenant_id,
                        'product_variant_id' => $variant->variant_id,
                        'label' => $variant->product_name.' - '.$variant->variant_name,
                        'expiry_date' => $variant->expiry_date,
                        'recorded_on' => $today,
                        'qty' => null,
                        'cost_price' => null,
                        'value' => null,
                        'source' => 'pre_existing',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($rows !== []) {
                    DB::table('expired_stock_records')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('expired_stock_records');
    }
};
