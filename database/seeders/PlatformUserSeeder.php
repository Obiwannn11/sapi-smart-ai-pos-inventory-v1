<?php

namespace Database\Seeders;

use App\Models\PlatformUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun pemilik SaaS pertama.
 *
 * Idempotent — aman dijalankan ulang. Kata sandi diambil dari
 * PLATFORM_ADMIN_PASSWORD; nilai bawaan hanya untuk lingkungan lokal dan
 * WAJIB diganti sebelum dipakai di server sungguhan.
 */
class PlatformUserSeeder extends Seeder
{
    public function run(): void
    {
        // Akun pertama adalah pemilik: aksesnya berasal dari penanda `is_owner`,
        // bukan dari baris modul. Karena itu tak ada modul yang di-seed untuknya
        // — menuliskannya hanya akan jadi data menyesatkan yang seolah bisa
        // dicabut padahal tidak berpengaruh.
        PlatformUser::firstOrCreate(
            ['email' => env('PLATFORM_ADMIN_EMAIL', 'platform@sapi.test')],
            [
                'name' => env('PLATFORM_ADMIN_NAME', 'Pemilik Platform'),
                'password' => Hash::make(env('PLATFORM_ADMIN_PASSWORD', 'password')),
                'is_owner' => true,
            ]
        );
    }
}
