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

    // --- Mode identitas pesanan ([BL-026]) ---

    /** Pesanan tidak diberi identitas apa pun. Bawaan. */
    public const ORDER_IDENTITY_NONE = 'none';

    /** Nama pelanggan, diketik bebas. */
    public const ORDER_IDENTITY_NAME = 'name';

    /** Nomor meja, diisi dari papan angka. */
    public const ORDER_IDENTITY_TABLE = 'table';

    /** Kode panggil, dialokasikan sistem — kasir tidak mengetik apa pun. */
    public const ORDER_IDENTITY_CODE = 'code';

    protected $fillable = [
        'name', 'slug', 'business_type', 'logo', 'address', 'phone', 'status', 'is_demo', 'pricing_track',
        'signup_ip', 'flagged_at', 'flag_reason',
        'ai_provider', 'ai_api_key', 'ai_model',
        'kitchen_queue_enabled', 'self_order_enabled', 'ai_enabled',
        'upsell_mandatory', 'order_identity_mode',
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
        'is_demo' => false,
        'business_type' => self::BUSINESS_TYPE_DEFAULT,
        'pricing_track' => Subscription::TRACK_NORMAL,
        'kitchen_queue_enabled' => false,
        'self_order_enabled' => false,
        'ai_enabled' => true,
        'upsell_mandatory' => false,
        'order_identity_mode' => self::ORDER_IDENTITY_NONE,
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
            'is_demo' => 'boolean',
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
     * Mode identitas pesanan beserta keterangannya, untuk halaman Pengaturan
     * DAN untuk aturan validasinya. Satu daftar, dua pemakai — supaya pilihan
     * yang tampil di layar tidak pernah menyimpang dari yang diterima server.
     *
     * @return array<string, string>
     */
    public static function orderIdentityModes(): array
    {
        return [
            self::ORDER_IDENTITY_NONE => 'Tidak dipakai',
            self::ORDER_IDENTITY_NAME => 'Nama pelanggan',
            self::ORDER_IDENTITY_TABLE => 'Nomor meja',
            self::ORDER_IDENTITY_CODE => 'Kode panggil (otomatis)',
        ];
    }

    /**
     * Apakah tiap pesanan outlet ini butuh nomor panggil.
     *
     * Sengaja TIDAK lewat hasFeature(): mode identitas bukan kapabilitas modul
     * dan tidak menggerbangi rute mana pun — ia hanya menentukan apa yang
     * ditanyakan kasir sebelum menyimpan.
     *
     * Perhatikan bahwa nomor panggil juga lahir saat `kitchen_queue` menyala.
     * Keduanya sengaja dipisah: memanggil pelanggan dan menjalankan papan dapur
     * adalah dua kebutuhan berbeda, dan warung yang hanya ingin memanggil tidak
     * seharusnya dipaksa menyalakan papan ([BL-026]).
     */
    public function usesCallNumber(): bool
    {
        return $this->order_identity_mode === self::ORDER_IDENTITY_CODE;
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
