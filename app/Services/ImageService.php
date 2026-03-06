<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class ImageService
{
    /**
     * Upload gambar, konversi ke WEBP, simpan ke storage.
     *
     * @return string Path relatif yang disimpan di database
     */
    public function upload(UploadedFile $file, string $directory = 'products'): string
    {
        $tenantId = auth()->user()->tenant_id;
        $filename = Str::uuid() . '.webp';
        $path = "{$directory}/{$tenantId}";

        // Konversi ke WEBP menggunakan Intervention Image v3
        $image = Image::read($file);
        $encoded = $image->toWebp(quality: 80);

        // Simpan ke storage/app/public/
        Storage::disk('public')->put("{$path}/{$filename}", (string) $encoded);

        return "{$path}/{$filename}";
    }

    /**
     * Hapus gambar dari storage.
     */
    public function delete(string $path): void
    {
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Get public URL untuk gambar.
     */
    public function url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($path);
        $version = file_exists($fullPath) ? filemtime($fullPath) : 0;

        return Storage::disk('public')->url($path) . '?v=' . $version;
    }
}
