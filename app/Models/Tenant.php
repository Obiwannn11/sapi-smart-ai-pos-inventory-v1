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

    /**
     * Tipe usaha bawaan untuk tenant yang belum menjawabnya.
     *
     * Nilainya harus ada di `config('pricing-dimensions.business_type.options')`
     * — ia dipakai sebagai nilai awal form pendaftaran DAN sebagai isian
     * migrasi backfill, jadi keduanya tak boleh menyimpang satu sama lain.
     *
     * `lainnya`, bukan tipe yang paling umum: menebak "kuliner" karena itu
     * mayoritas berarti memasang dasar harga yang salah pada orang yang belum
     * pernah ditanya. Bawaan yang netral tidak mengaku tahu apa pun.
     */
    public const BUSINESS_TYPE_DEFAULT = 'lainnya';

    protected $fillable = [
        'name', 'slug', 'business_type', 'logo', 'address', 'phone', 'status', 'pricing_track',
        'signup_ip', 'flagged_at', 'flag_reason',
        'ai_provider', 'ai_api_key', 'ai_model',
        'kitchen_queue_enabled', 'self_order_enabled', 'ai_enabled',
        'upsell_mandatory',
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
        'business_type' => self::BUSINESS_TYPE_DEFAULT,
        'pricing_track' => Subscription::TRACK_NORMAL,
        'kitchen_queue_enabled' => false,
        'self_order_enabled' => false,
        'ai_enabled' => true,
        'upsell_mandatory' => false,
    ];

    protected function casts(): array
    {
        return [
            'ai_api_key' => 'encrypted',
            'flagged_at' => 'datetime',
            'kitchen_queue_enabled' => 'boolean',
            'self_order_enabled' => 'boolean',
            'ai_enabled' => 'boolean',
            'upsell_mandatory' => 'boolean',
        ];
    }

    // --- Helpers ---

    /**
     * Apakah kapabilitas ini aktif untuk tenant tersebut.
     *
     * Satu-satunya pintu. Route, menu nav, controller, job, dan tool MCP
     * semuanya bertanya ke sini — sehingga menambah permukaan tidak pernah
     * berarti menambah logika, hanya menambah pemanggil.
     *
     * `default => false` disengaja: nama fitur yang salah ketik harus MENUTUP
     * pintu, bukan membukanya diam-diam.
     */
    public function hasFeature(string $feature): bool
    {
        return (bool) match ($feature) {
            'kitchen_queue' => $this->kitchen_queue_enabled,
            'self_order' => $this->self_order_enabled,
            'ai' => $this->ai_enabled,
            default => false,
        };
    }

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
