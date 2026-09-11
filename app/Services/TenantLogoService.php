<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Penyimpanan logo usaha — satu berkas per tenant.
 *
 * Kembarannya yang lebih tua adalah `ImageService` (gambar produk), dan dua
 * perbedaan di bawah ini yang membuatnya tidak cukup dipakai ulang:
 *
 * 1. Logo DIMUAT UTUH ke dalam kotak persegi, bukan dipotong penuhi
 *    (`contain`, bukan `cover`). Logo usaha sering melebar — namanya di
 *    samping lambang. `cover` akan memotong justru bagian yang membuatnya
 *    dikenali, dan pemiliknya tidak punya cara memperbaikinya selain
 *    mengunggah gambar yang sudah dipotong sendiri.
 *
 * 2. Latarnya TRANSPARAN, bukan putih. Lencana di kepala sidebar berlatar
 *    warna merek; logo dengan kotak putih di belakangnya akan terbaca sebagai
 *    tempelan, bukan sebagai identitas toko.
 *
 * Satu rendition saja, 256 px. Permukaan terbesar yang memakainya adalah
 * kepala struk (~48 px), jadi dua rendition seperti gambar produk hanya akan
 * menambah berkas yang tidak pernah diminta siapa pun.
 *
 * Disknya privat, dengan alasan yang sama persis seperti gambar produk: path
 * di sini tidak pernah sampai ke browser, dan berkasnya hanya keluar lewat
 * rute ber-auth `/media/logo` (lihat `MediaController::tenantLogo`).
 */
class TenantLogoService
{
    /** Disk privat — root-nya storage/app/private, tanpa symlink ke public/. */
    public const DISK = 'local';

    private const SIZE = 256;

    private const QUALITY = 85;

    /**
     * Unggah logo baru untuk sebuah tenant dan buang logo lamanya.
     *
     * Penghapusan yang lama terjadi SETELAH yang baru tersimpan: kalau urutannya
     * dibalik dan penyimpanan gagal, tenant berakhir tanpa logo sama sekali —
     * kehilangan yang tidak diminta siapa pun.
     *
     * @return string Path yang disimpan di kolom `tenants.logo`
     */
    public function upload(UploadedFile $file, Tenant $tenant): string
    {
        $previous = $tenant->logo;
        $path = "logos/{$tenant->id}/".Str::uuid().'.webp';

        // ImageManager dipakai langsung, bukan facade Image milik Intervention:
        // yang terpasang di proyek ini hanya paket inti intervention/image.
        //
        // Latarnya ditulis sebagai rgba(...) dan bukan kata 'transparent'
        // supaya tidak bergantung pada kamus warna driver.
        $image = ImageManager::gd()->read($file->getRealPath())
            ->contain(self::SIZE, self::SIZE, 'rgba(255, 255, 255, 0)');

        $this->disk()->put($path, (string) $image->toWebp(quality: self::QUALITY));

        $this->deleteFile($previous);

        return $path;
    }

    /**
     * Hapus logo tenant dari disk dan kosongkan kolomnya.
     */
    public function remove(Tenant $tenant): void
    {
        $this->deleteFile($tenant->logo);
        $tenant->update(['logo' => null]);
    }

    /**
     * Buang satu berkas logo. Aman dipanggil dengan null.
     */
    public function deleteFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        $this->disk()->delete($path);
    }

    /**
     * URL logo tenant, atau null bila belum ada.
     *
     * Sidik `v` diturunkan dari path berkasnya. Rutenya sendiri tidak membawa
     * nama berkas — ia selalu "logo milik tenant saya" — jadi tanpa penanda ini
     * URL-nya tidak pernah berubah, dan header `immutable` di MediaController
     * akan membuat logo lama menempel di peramban setelah pemiliknya
     * menggantinya.
     */
    public function urlFor(Tenant $tenant): ?string
    {
        if (! $tenant->logo) {
            return null;
        }

        return route('media.tenant-logo').'?v='.substr(sha1($tenant->logo), 0, 8);
    }

    public function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
