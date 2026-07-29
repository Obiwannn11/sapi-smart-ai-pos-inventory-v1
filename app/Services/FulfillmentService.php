<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat logika fulfillment tinggal.
 *
 * Web, API self-order, dan mobile memanggilnya — sehingga menambah permukaan
 * tidak pernah berarti menambah aturan, hanya menambah pemanggil.
 */
class FulfillmentService
{
    /** Status yang dianggap "aktif" di papan. */
    private const ACTIVE = [
        Transaction::FULFILLMENT_WAITING,
        Transaction::FULFILLMENT_PREPARING,
        Transaction::FULFILLMENT_READY,
    ];

    /**
     * Majukan satu langkah: waiting → preparing → ready → done.
     *
     * `$expectedFrom` WAJIB dan bukan basa-basi. Papan di-poll tiap 5–10 detik
     * dan bisa dibuka dua orang sekaligus, jadi kartu basi bukan kemungkinan
     * melainkan keadaan normal. Tanpa pemeriksaan ini, satu tap pada kartu yang
     * masih menampilkan "Mulai masak" padahal sudah `preparing` akan melompati
     * satu status diam-diam.
     */
    public function advance(Transaction $transaction, string $expectedFrom): Transaction
    {
        return DB::transaction(function () use ($transaction, $expectedFrom) {
            $transaction->refresh();

            if (! $transaction->hasFulfillmentTracking()) {
                throw new \Exception('Transaksi ini tidak punya fulfillment tracking.');
            }

            if ($transaction->fulfillment_status !== $expectedFrom) {
                throw new \Exception(
                    'Status pesanan sudah berubah (sekarang: '.$transaction->fulfillment_status.'). Papan disegarkan.'
                );
            }

            $next = match ($expectedFrom) {
                Transaction::FULFILLMENT_WAITING => Transaction::FULFILLMENT_PREPARING,
                Transaction::FULFILLMENT_PREPARING => Transaction::FULFILLMENT_READY,
                Transaction::FULFILLMENT_READY => Transaction::FULFILLMENT_DONE,
                default => throw new \Exception('Pesanan sudah selesai.'),
            };

            // Satu tulisan, bukan tiga. Stempel waktu ikut di sini, dan hanya
            // disetel sekali — memundurkan lalu memajukan lagi tidak boleh
            // menghapus jejak kapan masakan benar-benar dimulai.
            $stamps = [];

            if ($next === Transaction::FULFILLMENT_PREPARING && ! $transaction->preparing_at) {
                $stamps['preparing_at'] = now();
            }

            if ($next === Transaction::FULFILLMENT_READY && ! $transaction->ready_at) {
                $stamps['ready_at'] = now();
            }

            $transaction->update(['fulfillment_status' => $next] + $stamps);

            return $transaction;
        });
    }

    /**
     * Dahulukan — aksi prioritas UTAMA. Lompat ke puncak papan sekali tekan.
     *
     * Menukar tetangga satu per satu berarti 9 tap untuk memindahkan kartu ke-10
     * ke puncak, oleh orang yang sedang memasak. Niat sebenarnya hampir selalu
     * "kerjakan yang ini duluan", bukan "geser satu".
     */
    public function moveToTop(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction) {
            $min = $this->activeBoard($transaction->tenant_id)->lockForUpdate()->min('sort_index');

            $transaction->update(['sort_index' => (int) $min - 1]);

            return $transaction->refresh();
        });
    }

    public function moveUp(Transaction $transaction): Transaction
    {
        return $this->swapWithNeighbor($transaction, 'up');
    }

    public function moveDown(Transaction $transaction): Transaction
    {
        return $this->swapWithNeighbor($transaction, 'down');
    }

    /**
     * Tukar posisi dengan kartu tetangga terdekat.
     *
     * Urutan papan adalah (sort_index, id) — `id` bukan hiasan. `sort_index`
     * lahir dari cap waktu milidetik, dan dua self-order bisa jatuh di
     * milidetik yang sama; tanpa pemecah seri, perbandingan strict akan
     * melewati kartu kembar dan tombolnya terasa rusak.
     */
    private function swapWithNeighbor(Transaction $transaction, string $direction): Transaction
    {
        return DB::transaction(function () use ($transaction, $direction) {
            // Jangan percaya sort_index in-memory: dua tap cepat saling
            // menimpa kalau nilainya dibaca dari model yang tidak dikunci.
            $transaction->refresh();
            [$si, $id] = [(int) $transaction->sort_index, $transaction->id];

            $neighbor = $this->activeBoard($transaction->tenant_id)
                ->lockForUpdate()
                ->when($direction === 'up',
                    fn ($q) => $q->where(fn ($w) => $w->where('sort_index', '<', $si)
                        ->orWhere(fn ($t) => $t->where('sort_index', $si)->where('id', '<', $id)))
                        ->orderByDesc('sort_index')->orderByDesc('id'),
                    fn ($q) => $q->where(fn ($w) => $w->where('sort_index', '>', $si)
                        ->orWhere(fn ($t) => $t->where('sort_index', $si)->where('id', '>', $id)))
                        ->orderBy('sort_index')->orderBy('id'),
                )
                ->first();

            if (! $neighbor) {
                return $transaction;   // sudah di ujung — no-op, bukan error
            }

            // Kartu kembar (sort_index sama) tidak bisa ditukar hanya dengan
            // menukar nilainya — keduanya akan tetap kembar dan posisinya tak
            // berubah. Sisipkan di sisi seberang tetangganya.
            if ((int) $neighbor->sort_index === $si) {
                $transaction->update(['sort_index' => $direction === 'up' ? $si - 1 : $si + 1]);

                return $transaction->refresh();
            }

            $neighborIndex = $neighbor->sort_index;
            $neighbor->update(['sort_index' => $si]);
            $transaction->update(['sort_index' => $neighborIndex]);

            return $transaction->refresh();
        });
    }

    /**
     * Himpunan kartu yang hidup di papan hari ini.
     *
     * Tiga saringan, masing-masing menutup lubang berbeda:
     * - status `voided` dikecualikan (lapis kedua setelah void() menolkan fulfillment)
     * - batas hari lewat effectiveDate(), bukan created_at
     * - hanya status aktif
     */
    private function activeBoard(int $tenantId): Builder
    {
        return Transaction::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('fulfillment_status', self::ACTIVE)
            ->where('status', '!=', Transaction::STATUS_VOIDED)
            ->whereEffectiveDate(now());
    }
}
