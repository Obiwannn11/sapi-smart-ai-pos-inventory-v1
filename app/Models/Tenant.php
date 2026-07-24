<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasFactory;

    /** Masa coba 1 bulan. Aplikasi berjalan penuh, belum ada tagihan. */
    public const STATUS_TRIAL = 'trial';

    /** Berlangganan dan lunas. */
    public const STATUS_ACTIVE = 'active';

    /**
     * Tenggang setelah trial habis atau tagihan lewat jatuh tempo: aplikasi
     * jadi HANYA-BACA. Data lama tetap terbuka dan bisa diekspor, tapi
     * transaksi baru ditolak. Menyandera data pelanggan bukan alat penagihan
     * yang sah; menahan layanan baru adalah.
     */
    public const STATUS_GRACE = 'grace';

    /** Tenggang habis tanpa penyelesaian — akses ditutup. */
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name', 'slug', 'logo', 'address', 'phone', 'status', 'pricing_track',
        'ai_provider', 'ai_api_key', 'ai_model',
    ];

    protected $hidden = ['ai_api_key'];

    /**
     * Kembaran dari default kolom di migrasi, supaya instance yang baru dibuat
     * sudah memegang nilainya sebelum dibaca ulang dari database. Tanpa ini
     * `canWrite()` pada tenant yang baru saja dibuat akan menjawab dari `null`
     * — dan menjawab salah.
     */
    protected $attributes = [
        'status' => self::STATUS_TRIAL,
        'pricing_track' => Subscription::TRACK_NORMAL,
    ];

    protected function casts(): array
    {
        return [
            'ai_api_key' => 'encrypted',
        ];
    }

    // --- Helpers ---

    /**
     * Boleh membuat data baru (transaksi, produk, staf). Hanya `trial` dan
     * `active`; `grace` sengaja tidak termasuk.
     */
    public function canWrite(): bool
    {
        return in_array($this->status, [self::STATUS_TRIAL, self::STATUS_ACTIVE], true);
    }

    public function isReadOnly(): bool
    {
        return $this->status === self::STATUS_GRACE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    // --- Relationships ---
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(TenantConsent::class);
    }

    public function aiUsages(): HasMany
    {
        return $this->hasMany(AiUsage::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Akun owner tenant — kontak penagihan bagi pemilik SaaS.
     */
    public function owners(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'owner');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function cashDrawers(): HasMany
    {
        return $this->hasMany(CashDrawer::class);
    }
}
