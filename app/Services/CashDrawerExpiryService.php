<?php

namespace App\Services;

use App\Models\CashDrawer;
use Illuminate\Support\Facades\DB;

/**
 * Umur sesi kas dan apa yang terjadi sesudahnya ([BL-088]).
 *
 * Sebelum ini tidak ada satu pun tugas terjadwal yang menyentuh `cash_drawers`.
 * Sesi hidup sampai ada yang menutupnya — kasir yang pulang tanpa menekan
 * "Tutup Kas" meninggalkan sesi yang esok paginya MENOLAK dibuka lagi, sambil
 * diam-diam menghitung penjualan dua hari sebagai isi satu laci.
 *
 * **Yang ditutup di sini tidak pernah mengaku sudah dihitung.** `closing_amount`
 * dan `difference` dibiarkan `null`; hanya `expected_amount` yang diisi, karena
 * angka itu milik sistem sendiri dan bukan pernyataan tentang uang fisik.
 * Mengisi `closing_amount` dengan `expected_amount` akan menghasilkan selisih
 * nol yang dikarang — kebohongan yang persis sama dengan yang dilarang
 * `[BL-086]`, hanya berpindah dari layar kasir ke basis data.
 *
 * Ongkos yang diterima sadar: uang fisik sesi itu tidak pernah dihitung siapa
 * pun. Itu ditukar dengan sesi yang tidak menggantung selamanya — dan justru
 * karena ongkosnya nyata, penandanya tidak boleh dilonggarkan.
 */
class CashDrawerExpiryService
{
    public function __construct(
        private CashDrawerReconciliation $reconciliation,
    ) {}

    /**
     * Tutup paksa seluruh sesi kas yang lewat umurnya.
     *
     * `withoutGlobalScopes()` disengaja: perintah terjadwal berjalan tanpa user
     * yang login, jadi TenantScope tidak punya tenant untuk disandarkan. Sapuan
     * ini memang lintas tenant — pola yang sama dengan OpenBillExpiryService.
     *
     * @param  mixed  $now  Titik acuan; null = sekarang. Ada demi pengujian dan
     *                      demi perhitungan ulang manual, bukan hiasan.
     * @return int Jumlah sesi yang ditutup
     */
    public function expire(mixed $now = null, bool $dryRun = false): int
    {
        $query = CashDrawer::withoutGlobalScopes()->stale($now);

        if ($dryRun) {
            return $query->count();
        }

        $closed = 0;

        $query->orderBy('id')->chunkById(100, function ($drawers) use (&$closed) {
            foreach ($drawers as $drawer) {
                // Rekonsiliasinya dihitung SEBELUM `closed_at` diisi: jendela
                // sesi memakai `closed_at ?? now()`, jadi menutupnya lebih dulu
                // akan memotong jendela di titik yang sama dan menghasilkan
                // angka yang benar hanya karena kebetulan urutannya.
                $expected = $this->reconciliation->for($drawer)['expected_amount'];

                DB::transaction(function () use ($drawer, $expected, &$closed) {
                    $drawer->update([
                        'closed_at' => now(),
                        'closed_by_system' => true,
                        'expected_amount' => $expected,
                        // Sengaja TIDAK diisi. Lihat catatan kelas.
                        'closing_amount' => null,
                        'difference' => null,
                        'notes' => trim(($drawer->notes ? $drawer->notes."\n" : '').sprintf(
                            'Ditutup otomatis oleh sistem setelah lewat %d jam. Uang fisik tidak pernah dihitung.',
                            CashDrawer::MAX_SESSION_HOURS,
                        )),
                    ]);

                    $closed++;
                });
            }
        });

        return $closed;
    }
}
