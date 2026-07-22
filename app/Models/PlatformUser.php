<?php

namespace App\Models;

use App\Notifications\PlatformResetPassword;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'is_owner'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_owner' => 'boolean',
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

    // --- Notifications ---

    /**
     * Kirim tautan pemulihan yang mengarah ke panel platform.
     *
     * Bawaan Laravel menyusun tautan lewat route('password.reset') milik tenant;
     * akun ini ada di tabel lain, jadi tautannya harus dialihkan.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new PlatformResetPassword($token));
    }

    // --- Helpers ---

    /**
     * Pemilik SaaS — setara owner di dalam tenant: akses penuh, tak perlu
     * dicentangkan modul apa pun.
     */
    public function isOwner(): bool
    {
        // Cast eksplisit: model yang dibangun tanpa atribut ini (mis. lewat
        // `new PlatformUser`) mengembalikan null, dan null bukan "pemilik".
        return (bool) $this->is_owner;
    }

    /**
     * Daftar nama modul yang boleh dibuka akun ini.
     *
     * Owner mengembalikan seluruh katalog, bukan `['*']`: nav dan gerbang route
     * sama-sama mencocokkan nama modul, jadi daftar konkret membuat keduanya
     * bekerja tanpa cabang khusus.
     *
     * @return list<string>
     */
    public function moduleNames(): array
    {
        if ($this->isOwner()) {
            return array_keys(config('platform-rbac.modules'));
        }

        return $this->modules()->pluck('module')->all();
    }

    public function hasModule(string $module): bool
    {
        return $this->isOwner() || $this->modules()->where('module', $module)->exists();
    }
}
