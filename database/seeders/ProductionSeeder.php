<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'My Business']
        );

        $adminEmail = env('ADMIN_EMAIL', 'admin@sapi.com');
        $adminPassword = env('ADMIN_INITIAL_PASSWORD');
        $generatedPassword = false;

        if (empty($adminPassword)) {
            $adminPassword = Str::random(24);
            $generatedPassword = true;
        }

        $admin = User::firstOrCreate(
            ['email' => $adminEmail],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Admin',
                'password' => Hash::make($adminPassword),
                'role' => 'owner',
            ]
        );

        if ($admin->wasRecentlyCreated && $generatedPassword && $this->command) {
            $this->command->warn('ADMIN_INITIAL_PASSWORD tidak diset. Password admin sementara: '.$adminPassword);
            $this->command->warn('Segera ganti password setelah login pertama.');
        }

        // Default payment method
        PaymentMethod::firstOrCreate(
            ['tenant_id' => $tenant->id, 'type' => 'cash'],
            ['name' => 'Cash', 'is_active' => true]
        );
    }
}
