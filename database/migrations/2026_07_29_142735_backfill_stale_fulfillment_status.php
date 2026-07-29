<?php

use App\Models\Transaction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Membersihkan timbunan `fulfillment_status` yang selama ini tak terlihat.
 *
 * Open bill SUDAH memperoleh `fulfillment_status = waiting` sejak fitur itu
 * ada, sementara `payOpenBill()` hanya mengubah `status` jadi `completed` dan
 * tidak pernah menyentuh `fulfillment_status`. Artinya setiap open bill yang
 * pernah dibuat — termasuk yang lunas dan selesai berbulan-bulan lalu — sampai
 * detik ini masih berbunyi `waiting`. Selama ini tidak terlihat semata karena
 * belum ada permukaan yang menampilkannya.
 *
 * Begitu papan antrian menyala, semuanya muncul serentak sebagai pesanan aktif.
 * Batas hari pada query papan saja TIDAK cukup: sebagian jatuh di hari yang
 * sama dengan pengaktifan, dan yang lolos tetap salah — hanya tidak terlihat.
 *
 * Sumber timbunannya sendiri ditutup di TransactionService@checkout, yang kini
 * bersyarat mode antrian alih-alih bersyarat open bill.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Sudah tuntas: dibatalkan, atau open bill POS yang sudah lunas.
        //    Tidak ada lagi yang perlu dimasak, berapa pun umurnya.
        DB::table('transactions')
            ->whereNotNull('fulfillment_status')
            ->where('fulfillment_status', '!=', Transaction::FULFILLMENT_DONE)
            ->where(function ($q) {
                $q->where('status', Transaction::STATUS_VOIDED)
                    ->orWhere(fn ($s) => $s->where('status', Transaction::STATUS_COMPLETED)
                        ->where('source', Transaction::SOURCE_POS));
            })
            ->update(['fulfillment_status' => null]);

        // 2. Apa pun yang lebih tua dari hari pemasangan. Papannya belum pernah
        //    ada, jadi tak mungkin ada pesanan yang benar-benar masih menunggu
        //    dimasak — termasuk self-order lunas yang tak pernah ada yang
        //    memajukannya.
        //
        //    Baris HARI INI sengaja disisakan: bisa jadi pesanan yang sungguh
        //    sedang berjalan saat rilis dipasang.
        DB::table('transactions')
            ->whereNotNull('fulfillment_status')
            ->where('fulfillment_status', '!=', Transaction::FULFILLMENT_DONE)
            ->where('created_at', '<', now()->startOfDay())
            ->update(['fulfillment_status' => null]);
    }

    public function down(): void
    {
        // Sengaja no-op. Ini migrasi DATA, bukan struktur: nilai yang dinolkan
        // tidak disimpan di mana pun, jadi tidak ada yang bisa dikembalikan.
        // Menulis down() yang seolah-olah memulihkan akan berbohong.
    }
};
