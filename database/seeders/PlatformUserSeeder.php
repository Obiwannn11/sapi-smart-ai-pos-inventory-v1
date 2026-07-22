<?php

namespace Database\Seeders;

use App\Models\PlatformUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Akun pemilik SaaS pertama. Idempotent — aman dijalankan ulang.
 *
 * Kredensial diambil dari PLATFORM_ADMIN_*. Nilai bawaan hanya berlaku di
 * lingkungan lokal; di luar itu seeder menolak jalan (lihat assertUsable()).
 */
class PlatformUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('PLATFORM_ADMIN_EMAIL') ?: 'platform@sapi.test';
        $password = env('PLATFORM_ADMIN_PASSWORD') ?: 'password';

        $this->assertUsable($password);

        // Akun pertama adalah pemilik: aksesnya berasal dari penanda `is_owner`,
        // bukan dari baris modul. Karena itu tak ada modul yang di-seed untuknya
        // — menuliskannya hanya akan jadi data menyesatkan yang seolah bisa
        // dicabut padahal tidak berpengaruh.
        PlatformUser::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('PLATFORM_ADMIN_NAME') ?: 'Pemilik Platform',
                'password' => Hash::make($password),
                'is_owner' => true,
            ]
        );
    }

    /**
     * Tolak berjalan dengan kata sandi bawaan di luar lingkungan lokal.
     *
     * Gagal keras, bukan memakai nilai bawaan diam-diam: akun ini memegang data
     * administratif seluruh klien, dan kata sandi `password` di server sungguhan
     * adalah kegagalan yang tak boleh lolos tanpa suara. Perhatikan juga bahwa
     * `env()` mengembalikan null saat config di-cache — jadi kalau tidak dicegat
     * di sini, deploy yang sudah rapi pun bisa berakhir memakai bawaan.
     */
    private function assertUsable(string $password): void
    {
        if (app()->environment('local', 'testing')) {
            return;
        }

        if ($password === 'password') {
            throw new RuntimeException(
                'PLATFORM_ADMIN_PASSWORD wajib diisi di luar lingkungan lokal. '
                .'Isi di .env lalu jalankan ulang seeder. '
                .'Bila config sudah di-cache, jalankan `php artisan config:clear` lebih dulu.'
            );
        }
    }
}
