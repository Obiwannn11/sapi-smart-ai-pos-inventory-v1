<?php

namespace Database\Seeders;

use App\Models\PlatformUser;
use App\Models\PlatformUserModule;
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
        $platformUser = PlatformUser::firstOrCreate(
            ['email' => env('PLATFORM_ADMIN_EMAIL', 'platform@sapi.test')],
            [
                'name' => env('PLATFORM_ADMIN_NAME', 'Pemilik Platform'),
                'password' => Hash::make(env('PLATFORM_ADMIN_PASSWORD', 'password')),
            ]
        );

        // Pemilik SaaS memegang seluruh modul. Pembatasan per-modul baru relevan
        // saat ada staf platform — mekanismenya sudah siap, tinggal dipakai.
        foreach (array_keys(config('platform-rbac.modules')) as $module) {
            PlatformUserModule::firstOrCreate([
                'platform_user_id' => $platformUser->id,
                'module' => $module,
            ]);
        }
    }
}
