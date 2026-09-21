<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Penyimpanan gambar produk.
 *
 * Dua keputusan membentuk kelas ini:
 *
 * 1. Setiap unggahan dinormalkan menjadi WEBP PERSEGI dalam dua ukuran tetap —
 *    800x800 untuk tampilan besar dan 200x200 untuk kisi. Sebelumnya berkas
 *    hanya dikonversi ke WEBP tanpa diubah ukurannya, jadi foto 12 MP dari
 *    kamera ponsel tersimpan apa adanya dan kisi POS mengunduhnya utuh — mahal
 *    di jaringan warung. Ukuran tetap juga membuat setiap permukaan memakai
 *    rasio yang sama, sehingga tak ada lagi kartu yang jomplang.
 *
 * 2. Berkasnya duduk di disk PRIVAT, bukan di storage/app/public. Symlink
 *    publik berarti siapa pun yang tahu path bisa membuka gambar toko mana pun
 *    tanpa login. Path di sini tidak pernah sampai ke browser: klien hanya
 *    melihat rute ber-auth /media/products/{product}/{size}, yang memeriksa
 *    tenant pemilik gambar (lihat MediaController).
 */
class ImageService
{
    /** Disk privat — root-nya storage/app/private, tanpa symlink ke public/. */
    public const DISK = 'local';

    private const MAIN_SIZE = 800;

    private const THUMB_SIZE = 200;

    private const QUALITY = 80;

    /**
     * Unggah gambar, potong persegi, simpan dua rendition ke disk privat.
     *
     * @return string Path rendition utama, yang disimpan di kolom products.image
     */
    public function upload(UploadedFile $file, string $directory = 'products'): string
    {
        $tenantId = auth()->user()->tenant_id;
        $base = "{$directory}/{$tenantId}/".Str::uuid();
        $path = "{$base}.webp";

        // ImageManager dipakai langsung, bukan facade Image milik Intervention:
        // yang terpasang di proyek ini hanya paket inti intervention/image,
        // tanpa intervention/image-laravel yang mendaftarkan facade itu. Kode
        // lama mengimpor facadenya, jadi setiap unggahan berakhir 500.
        //
        // Dibaca sekali lalu dikecilkan bertahap: thumb diturunkan dari hasil
        // 800px, bukan dari berkas asli, supaya tidak ada dekode kedua atas
        // gambar berukuran penuh.
        $image = ImageManager::gd()->read($file->getRealPath())
            ->cover(self::MAIN_SIZE, self::MAIN_SIZE);
        $this->disk()->put($path, (string) $image->toWebp(quality: self::QUALITY));

        $thumb = $image->cover(self::THUMB_SIZE, self::THUMB_SIZE);
        $this->disk()->put($this->thumbPath($path), (string) $thumb->toWebp(quality: self::QUALITY));

        return $path;
    }

    /**
     * Hapus gambar beserta thumbnail-nya.
     */
    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        $this->disk()->delete([$path, $this->thumbPath($path)]);
    }

    /**
     * Path rendition kisi untuk sebuah path rendition utama.
     */
    public function thumbPath(string $path): string
    {
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
}
