<?php

namespace Database\Factories;

use App\Models\PlatformUserModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlatformUser>
 */
class PlatformUserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'remember_token' => \Illuminate\Support\Str::random(10),
        ];
    }

    /**
     * Akun dengan seluruh modul platform — dipakai untuk skenario "pemilik SaaS".
     */
    public function withAllModules(): static
    {
        return $this->withModules(array_keys(config('platform-rbac.modules')));
    }

    /**
     * Akun dengan sebagian modul — dipakai untuk menguji gerbang per-modul.
     *
     * @param  list<string>  $modules
     */
    public function withModules(array $modules): static
    {
        return $this->afterCreating(function ($platformUser) use ($modules) {
            foreach ($modules as $module) {
                PlatformUserModule::create([
                    'platform_user_id' => $platformUser->id,
                    'module' => $module,
                ]);
            }
        });
    }
}
