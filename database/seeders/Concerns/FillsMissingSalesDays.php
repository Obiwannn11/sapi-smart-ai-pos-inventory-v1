<?php

namespace Database\Seeders\Concerns;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Kalender penjualan untuk seeder demo: tanggal mana yang sudah ada isinya.
 *
 * Kedua seeder demo menyemai "sampai hari ini", jadi keduanya basi begitu
 * dibiarkan beberapa hari — dan keduanya dijalankan ulang tepat pada hari
 * demo, saat kesalahan paling mahal. Menjalankan ulang seeder yang menumpuk
 * membuat omzet ganda; seeder yang mereset lebih dulu membuang transaksi yang
 * baru saja dibuat lewat UI untuk keperluan demo itu sendiri.
 *
 * Jalan keluarnya adalah tidak melakukan keduanya: yang disemai hanya hari
 * yang benar-benar kosong. Hari yang sudah punya transaksi — dari seeder
 * sebelumnya maupun dari tangan sendiri — tidak disentuh sama sekali.
 */
trait FillsMissingSalesDays
{
    /**
     * Tanggal penjualan yang sudah punya transaksi milik tenant ini.
     *
     * Berkunci 'Y-m-d' supaya pemanggilnya cukup memeriksa keberadaan kunci,
     * bukan menyisir array tiap hari. Memakai tanggal efektif — bukan
     * `created_at` mentah — dengan alasan yang sama seperti seluruh laporan:
     * penjualan offline disinkronkan setelah kejadiannya.
     *
     * @return array<string, true>
     */
    protected function existingSalesDays(int $tenantId, Carbon $start, Carbon $end): array
    {
        $tanggalEfektif = Transaction::effectiveDateSql();

        return DB::table('transactions')
            ->where('tenant_id', $tenantId)
            ->whereRaw("DATE({$tanggalEfektif}) BETWEEN ? AND ?", [$start->toDateString(), $end->toDateString()])
            ->selectRaw("DATE({$tanggalEfektif}) AS sales_day")
            ->distinct()
            ->pluck('sales_day')
            ->mapWithKeys(fn ($day) => [Carbon::parse($day)->toDateString() => true])
            ->all();
    }
}
