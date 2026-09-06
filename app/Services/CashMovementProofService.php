<?php

namespace App\Services;

use App\Models\CashDrawerMovement;
use Illuminate\Http\UploadedFile;

/**
 * Foto struk untuk mutasi kas ([BL-093]).
 *
 * **Kenapa TIDAK meniru pola unggah-lalu-klaim `PaymentProofService`.** Entri
 * backlog-nya menyarankan menyalin preseden itu apa adanya, dan alasan
 * preseden itu ada ternyata tidak berlaku di sini. Dua langkah lahir karena
 * checkout POS mengirim JSON BERSARANG — item, modifier, pembayaran, kejadian
 * upsell — dan menyelipkan berkas ke dalamnya memaksa seluruh payload pindah
 * ke `multipart/form-data`, tempat setiap angka berubah jadi string. Formulir
 * mutasi kas datar: tipe, nominal, alasan. Ia memang boleh membawa berkasnya
 * sendiri.
 *
 * Yang ikut hilang bersama langkah kedua bukan cuma kerumitannya, melainkan
 * seluruh KELAS masalahnya: tidak ada direktori `pending/`, tidak ada token
 * yang bisa dikarang client, tidak ada berkas terlantar, dan karena itu tidak
 * ada perintah pembersih kedua yang harus ditulis, dijadwalkan, dan dijaga.
 * Berkas di sini lahir bersama barisnya atau tidak lahir sama sekali.
 *
 * Penyimpanan, thumbnail, dan penghapusannya dipakai apa adanya dari
 * `ProofFileService` — disk privat, tanpa symlink publik, disajikan hanya lewat
 * rute ber-auth.
 */
class CashMovementProofService
{
    /** Akar direktori, di disk privat yang sama dengan bukti bayar. */
    private const ROOT = 'cash-movement-proofs';

    public function __construct(
        private readonly ProofFileService $files,
    ) {}

    /**
     * Simpan foto struk dan kembalikan path yang disimpan di kolom.
     */
    public function store(UploadedFile $file, int $tenantId): string
    {
        return $this->files->store($file, $this->directory($tenantId));
    }

    /**
     * Path rendition yang diminta untuk sebuah mutasi.
     */
    public function pathFor(CashDrawerMovement $movement, string $size): ?string
    {
        if (! $movement->proof_path) {
            return null;
        }

        return $this->files->pathFor($movement->proof_path, $size);
    }

    public function files(): ProofFileService
    {
        return $this->files;
    }

    public function directory(int $tenantId): string
    {
        return self::ROOT."/{$tenantId}";
    }
}
