<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seat tambahan jadi hak yang DIBELI, bukan angka yang disimpulkan dari
 * pemakaian — `[BL-053]`, keputusan pemilik kedua 2026-08-07.
 *
 * Sampai sekarang "berapa seat tambahan yang dimiliki tenant" hanya bisa
 * dihitung mundur sebagai `seats − plan.included_seats`. Itu cukup selama seat
 * adalah biaya sekali bayar, dan berhenti cukup begitu ia jadi komponen bulanan:
 * angka yang disimpulkan dari selisih akan ikut berubah sendiri setiap kali
 * tenant berpindah paket, dan tagihan yang berubah tanpa ada yang memutuskannya
 * adalah tagihan yang tidak bisa dijelaskan.
 *
 *   - `purchased_extra_seats` — hak yang berlaku SEKARANG. Inilah dasar tagihan,
 *     terpakai atau tidak. `seat_high_water` berhenti berperan di penetapan
 *     harga langganan; ia tetap ada karena `ActiveSeatsResolver` masih
 *     membacanya sebagai dimensi `active_seats` untuk aturan Harga Adaptif —
 *     menghapusnya akan mengubah pencocokan aturan, bukan sekadar membersihkan
 *     kolom mati.
 *   - `scheduled_extra_seats` + `seat_release_at` — pelepasan yang sudah diminta
 *     tapi belum berlaku. Disimpan sebagai TARGET, bukan selisih: permintaan
 *     kedua sebelum yang pertama berlaku cukup menimpa targetnya, sementara
 *     menyimpan selisih menuntut penjumlahan yang benar di dua tempat.
 *
 * Tanggal berlakunya sengaja satu periode penuh ke depan, bukan akhir periode
 * berjalan. Tagihan periode berikutnya terbit `invoice_lead_days` SEBELUM
 * periode berjalan habis, jadi pelepasan yang berlaku di akhir periode berjalan
 * akan menagih seat yang sudah tidak bisa dipakai. Satu periode penuh membuat
 * jendela pakai dan jendela bayar berimpit persis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedSmallInteger('purchased_extra_seats')->default(0)->after('seats');
            $table->unsignedSmallInteger('scheduled_extra_seats')->nullable()->after('purchased_extra_seats');
            $table->date('seat_release_at')->nullable()->after('scheduled_extra_seats');
        });

        // Backfill dari keadaan yang berlaku hari ini. Baris lama memang tidak
        // punya catatan "berapa yang dibeli" — satu-satunya bukti yang ada
        // adalah selisih antara jatahnya sekarang dan jatah paketnya, dan itu
        // persis angka yang selama ini ditegakkan kepada mereka.
        //
        // Dijepit di nol: tenant yang dipindahkan ke paket berjatah lebih besar
        // bisa punya `seats` di bawah `included_seats`, dan selisih negatif di
        // kolom unsigned akan gagal, bukan diam-diam salah.
        //
        // Dihitung di PHP, bukan sebagai satu `UPDATE ... JOIN`. Produksi
        // memakai MySQL sementara test berjalan di SQLite, dan keduanya berbeda
        // baik pada sintaks UPDATE-dengan-JOIN maupun pada nama fungsi
        // penjepitnya (`GREATEST` vs `MAX`). Migrasi yang hanya benar di salah
        // satunya akan lolos test lalu gagal saat rilis.
        DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->select('subscriptions.id', 'subscriptions.seats', 'plans.included_seats')
            ->orderBy('subscriptions.id')
            ->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('subscriptions')
                        ->where('id', $row->id)
                        ->update(['purchased_extra_seats' => max(0, $row->seats - $row->included_seats)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['purchased_extra_seats', 'scheduled_extra_seats', 'seat_release_at']);
        });
    }
};
