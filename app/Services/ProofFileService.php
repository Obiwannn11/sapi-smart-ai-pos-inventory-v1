<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Penyimpanan berkas BUKTI — bukti transfer langganan dan bukti bayar non-tunai
 * di kasir.
 *
 * Sengaja terpisah dari ImageService, dan bukan karena kemalasan menyatukannya.
 * ImageService punya kontrak yang tegas: produk, PERSEGI, dua rendition, disk
 * privat. Dua di antaranya salah untuk bukti bayar:
 *
 *   1. BUKTI BAYAR TIDAK PERSEGI. Tangkapan layar e-wallet itu tinggi dan
 *      sempit; `cover(800,800)` memotong tepat bagian yang jadi alasan foto itu
 *      diambil — nominal dan kode referensinya. Di sini gambar dimuat KE DALAM
 *      kotak (rasio dijaga), tidak pernah dipotong.
 *
 *   2. BUKTI BAYAR BISA BERUPA PDF. Tagihan langganan menerima PDF di samping
 *      JPG/PNG, dan sebuah PDF tidak bisa dilewatkan ke encoder WEBP. Berkas
 *      bukan-gambar disimpan APA ADANYA; percabangannya di sini, sekali, bukan
 *      di tiap pemanggil.
 *
 * Yang DIWARISI dari ImageService dan tidak berubah: disk privat
 * (storage/app/private), tanpa symlink publik, dan penyajian hanya lewat rute
 * ber-auth. Alasannya di sini justru lebih kuat — tangkapan layar e-wallet
 * kerap memuat nama dan nomor telepon pelanggan.
 */
class ProofFileService
{
    /** Disk privat — root-nya storage/app/private, tanpa symlink ke public/. */
    public const DISK = 'local';

    /** Sisi terpanjang rendition utama. Cukup untuk membaca nominal di layar. */
    private const MAIN_SIZE = 1280;

    /** Sisi terpanjang rendition pratinjau, untuk daftar dan kartu. */
    private const THUMB_SIZE = 320;

    private const QUALITY = 80;

    /**
     * Simpan satu berkas bukti.
     *
     * Gambar dinormalkan jadi WEBP dalam dua ukuran; berkas lain (PDF) disalin
     * apa adanya karena mengubahnya berarti kehilangan aslinya.
     *
     * @param  string  $directory  Direktori tujuan, sudah termasuk pemisah tenant bila perlu.
     * @return string Path rendition utama — inilah yang disimpan di kolom.
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $base = trim($directory, '/').'/'.Str::uuid();

        if (! $this->isImage($file)) {
            return $file->storeAs(
                trim($directory, '/'),
                basename($base).'.'.$file->getClientOriginalExtension(),
                self::DISK,
            );
        }

        $path = "{$base}.webp";

        // ImageManager dipakai langsung, bukan facade Image: paket yang
        // terpasang hanya intervention/image inti, tanpa penyedia facade-nya.
        //
        // scaleDown, bukan cover: ia tidak pernah memotong dan tidak pernah
        // memperbesar gambar yang sudah kecil.
        $image = ImageManager::gd()->read($file->getRealPath())
            ->scaleDown(self::MAIN_SIZE, self::MAIN_SIZE);
        $this->disk()->put($path, (string) $image->toWebp(quality: self::QUALITY));

        $thumb = $image->scaleDown(self::THUMB_SIZE, self::THUMB_SIZE);
        $this->disk()->put($this->thumbPath($path), (string) $thumb->toWebp(quality: self::QUALITY));

        return $path;
    }

    /**
     * Hapus berkas bukti beserta pratinjaunya bila ada.
     */
    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        $this->disk()->delete(array_filter([$path, $this->thumbPath($path)]));
    }

    /**
     * Path pratinjau untuk sebuah path utama; sama dengan aslinya untuk PDF,
     * yang memang tidak punya rendition kedua.
     */
    public function thumbPath(string $path): string
    {
        if (! str_ends_with($path, '.webp')) {
            return $path;
        }

        return preg_replace('/\.webp$/', '_thumb.webp', $path);
    }

    /**
     * Path rendition yang diminta, atau null bila ukurannya tidak dikenal.
     */
    public function pathFor(string $path, string $size): ?string
    {
        return match ($size) {
            'full' => $path,
            'thumb' => $this->thumbPath($path),
            default => null,
        };
    }

    public function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    private function isImage(UploadedFile $file): bool
    {
        return str_starts_with((string) $file->getMimeType(), 'image/');
    }
}
