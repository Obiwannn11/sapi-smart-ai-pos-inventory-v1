<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'My Business']
        );

        User::firstOrCreate(
            ['email' => 'admin@sapi.com'],
            [
                'tenant_id' => $tenant->id,
                'name' => 'Admin',
                'password' => Hash::make('change-me-immediately'),
                'role' => 'owner',
            ]
        );

        // Default payment method
        PaymentMethod::firstOrCreate(
            ['tenant_id' => $tenant->id, 'type' => 'cash'],
            ['name' => 'Cash', 'is_active' => true]
        );
    }
}
