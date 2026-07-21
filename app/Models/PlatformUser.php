<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Akun pemilik SaaS — berdiri di atas semua tenant.
 *
 * Sengaja TIDAK punya `tenant_id`, dan sengaja tidak memakai trait
 * BelongsToTenant maupun HasRoles: akun ini tidak berada di dalam tenant mana
 * pun, dan izinnya dikelola lewat `platform_user_modules` (lihat hasModule()).
 */
class PlatformUser extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\PlatformUserFactory> */
    use HasFactory;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // --- Relationships ---
    public function modules(): HasMany
    {
        return $this->hasMany(PlatformUserModule::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(PlatformAuditLog::class);
    }

    // --- Helpers ---

    /**
     * Daftar nama modul yang boleh dibuka akun ini.
     *
     * @return list<string>
     */
    public function moduleNames(): array
    {
        return $this->modules()->pluck('module')->all();
    }

    public function hasModule(string $module): bool
    {
        return $this->modules()->where('module', $module)->exists();
    }
}
