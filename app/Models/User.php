<?php

namespace App\Models;

use App\Notifications\TenantResetPassword;
use App\Notifications\TenantVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'password', 'role', 'is_active', 'email_verified_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Kembaran dari default kolom di migrasi. Tanpa ini, instance yang baru
     * dibuat memegang `is_active` bernilai null sampai dibaca ulang dari
     * database — dan gerbang "pengguna nonaktif tidak boleh masuk" akan
     * menolak pengguna yang baru saja dibuat.
     */
    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
        ];
    }

    // --- Notifications ---

    /**
     * Kirim tautan pemulihan berbahasa Indonesia, bukan bawaan Laravel yang
     * berbahasa Inggris — seluruh antarmuka lain sudah berbahasa Indonesia.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new TenantResetPassword($token));
    }

    /**
     * Sama alasannya: seluruh antarmuka lain sudah berbahasa Indonesia.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new TenantVerifyEmail);
    }

    // --- Helpers ---
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cashDrawers(): HasMany
    {
        return $this->hasMany(CashDrawer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
