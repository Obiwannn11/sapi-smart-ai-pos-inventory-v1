<?php

namespace App\Models;

use App\Services\SubscriptionService;
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
     * Tenggang setelah trial habis atau tagihan lewat jatuh tempo.
     *
     * BERTINGKAT sejak keputusan pemilik 2026-08-07 (`[BL-054]`): kasir tetap
     * hidup sampai dua pertiga tenggat lewat, lalu barulah kemampuan menulis
     * dicabut. Sebelumnya satu status ini berarti hanya-baca sejak hari
     * pertama — dan warung yang tidak bisa berjualan tidak punya uang untuk
     * membayar. Yang tidak pernah dicabut di tahap mana pun: membaca data yang
     * sudah ada. Tangganya di `graceStage()`.
     */
    public const STATUS_GRACE = 'grace';

    // --- Tahap masa tenggang ([BL-054]) ---

    /** Hari 1 sampai sebelum `grace_intensive_from_day` — notifikasi halus, semua jalan. */
    public const GRACE_STAGE_SOFT = 'soft';

    /** Sejak `grace_intensive_from_day` — peringatan mengganggu, tapi masih boleh menulis. */
    public const GRACE_STAGE_INTENSIVE = 'intensive';

    /** Sejak `grace_lock_from_day` — kemampuan menulis dicabut, membaca tetap utuh. */
    public const GRACE_STAGE_LOCKED = 'locked';

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

    // --- Mode pajak ([BL-065]) ---

    /**
     * Pajak DITAMBAHKAN di atas harga katalog; yang dibayar pelanggan naik,
     * pendapatan toko tetap.
     */
    public const TAX_MODE_EXCLUSIVE = 'exclusive';

    /**
     * Harga katalog SUDAH mengandung pajak; yang dibayar pelanggan tidak
     * berubah, pendapatan toko turun sebesar pajak yang harus disetor.
     */
    public const TAX_MODE_INCLUSIVE = 'inclusive';

    protected $fillable = [
        'name', 'slug', 'business_type', 'logo', 'address', 'phone', 'status', 'is_demo', 'pricing_track',
        'signup_ip', 'flagged_at', 'flag_reason',
        'ai_provider', 'ai_api_key', 'ai_model',
        'kitchen_queue_enabled', 'self_order_enabled', 'ai_enabled', 'payment_proof_enabled',
        'min_margin_percent', 'cash_payout_approval_threshold',
        'upsell_mandatory', 'order_identity_mode',
        'tax_enabled', 'tax_mode', 'tax_rate', 'tax_label',
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
        'payment_proof_enabled' => false,
        'min_margin_percent' => 10.00,
        'cash_payout_approval_threshold' => 50000.00,
        'upsell_mandatory' => false,
        'order_identity_mode' => self::ORDER_IDENTITY_NONE,
        'tax_enabled' => false,
        'tax_mode' => self::TAX_MODE_EXCLUSIVE,
        'tax_rate' => 0,
        // `tax_label` sengaja tidak punya bawaan — lihat migrasinya. Menebak
        // kata yang tercetak di struk adalah kesalahan yang `[BL-079]` baru
        // saja hentikan.
    ];

    protected function casts(): array
    {
        return [
            'ai_api_key' => 'encrypted',
            'flagged_at' => 'datetime',
            'kitchen_queue_enabled' => 'boolean',
            'self_order_enabled' => 'boolean',
            'ai_enabled' => 'boolean',
            'payment_proof_enabled' => 'boolean',
            'min_margin_percent' => 'decimal:2',
            'cash_payout_approval_threshold' => 'decimal:2',
            'upsell_mandatory' => 'boolean',
            'is_demo' => 'boolean',
            'tax_enabled' => 'boolean',
            'tax_rate' => 'decimal:2',
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
            'payment_proof' => $this->payment_proof_enabled,
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
     * @return array<string, string>
     */
    public static function taxModes(): array
    {
        return [
            self::TAX_MODE_EXCLUSIVE => 'Dibebankan ke pembeli (ditambahkan di atas harga)',
            self::TAX_MODE_INCLUSIVE => 'Sudah termasuk di harga jual',
        ];
    }

    /**
     * Apakah sakelar dan mode pajak sudah terkunci ([BL-065] butir 3 & 4).
     *
     * Terkunci begitu ada SATU transaksi yang membawa konteks pajak beku —
     * bukan begitu pajak dinyalakan. Tenant yang menyalakannya lalu berubah
     * pikiran sebelum menjual apa pun tidak terjebak; yang terkunci hanya
     * yang sudah benar-benar memungut dari pelanggan.
     *
     * Yang dikunci HANYA `tax_enabled` dan `tax_mode` — keduanya mengubah
     * ARTI angka uang yang sudah tercatat. `tax_rate` dan `tax_label` tetap
     * bebas berubah dan berlaku maju: tarif memang berubah di dunia nyata
     * (PPN pernah naik 10% → 11%, dan tarif PBJT berbeda tiap Perda), dan
     * tarif yang berbeda tidak membuat angka lama tidak sebanding.
     */
    public function taxLocked(): bool
    {
        return $this->transactions()->whereNotNull('tax_mode')->exists();
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
     * Umur masa tenggang dalam hari, dihitung sejak akhir periode berjalan.
     *
     * Hari 1 adalah hari SETELAH periode habis — hari periodenya berakhir masih
     * hari terakhir yang dibayar, bukan hari pertama tunggakan. Dengan begitu
     * hari terakhir tenggang sama persis dengan `grace_days`, dan tanggal
     * penangguhannya sama dengan yang dihitung `suspensionDateFor()`.
     *
     * Dihitung, bukan disimpan: kolom "hari tenggat" harus dimutakhirkan setiap
     * tengah malam oleh sesuatu, dan sesuatu itu pasti akan gagal pada hari
     * penjadwalnya tidak jalan.
     *
     * `null` di luar masa tenggang, dan juga bila periodenya tidak diketahui —
     * lihat `graceStage()` untuk apa artinya bagi tenant.
     */
    public function graceDay(): ?int
    {
        if ($this->status !== self::STATUS_GRACE) {
            return null;
        }

        $periodEnd = $this->subscription?->current_period_end;

        if ($periodEnd === null) {
            return null;
        }

        return max(1, (int) $periodEnd->copy()->startOfDay()->diffInDays(now()->startOfDay(), false));
    }

    /**
     * Tahap tenggat tenant ini: `soft`, `intensive`, atau `locked`.
     *
     * Ambang keduanya dibaca dari config lewat `SubscriptionService` — angkanya
     * kebijakan komersial, dan kebijakan komersial tidak boleh menuntut
     * membaca kelas mana pun untuk diubah.
     *
     * Periode yang tidak diketahui menghasilkan `soft`, BUKAN `locked`: data
     * langganan yang bolong adalah masalah kami, dan menutup kasir orang karena
     * masalah kami adalah cara terburuk menemukannya.
     */
    public function graceStage(): ?string
    {
        if ($this->status !== self::STATUS_GRACE) {
            return null;
        }

        $day = $this->graceDay();

        return match (true) {
            $day === null => self::GRACE_STAGE_SOFT,
            $day >= SubscriptionService::graceLockFromDay() => self::GRACE_STAGE_LOCKED,
            $day >= SubscriptionService::graceIntensiveFromDay() => self::GRACE_STAGE_INTENSIVE,
            default => self::GRACE_STAGE_SOFT,
        };
    }

    /**
     * Boleh membuat data baru (transaksi, produk, staf).
     *
     * `grace` ikut termasuk sampai tahap `locked` — itu inti `[BL-054]`.
     */
    public function canWrite(): bool
    {
        if (in_array($this->status, [self::STATUS_TRIAL, self::STATUS_ACTIVE], true)) {
            return true;
        }

        return $this->status === self::STATUS_GRACE
            && $this->graceStage() !== self::GRACE_STAGE_LOCKED;
    }

    /**
     * Tenant yang kehilangan kemampuan menulis tapi masih boleh membaca.
     *
     * Sejak `[BL-054]` ini BUKAN lagi sinonim "sedang di masa tenggang":
     * tenggat hari 1–19 tetap bisa menulis. Yang bertanya "apakah tenant ini
     * sedang dibatasi?" harus memeriksa statusnya, bukan memanggil ini.
     */
    public function isReadOnly(): bool
    {
        return $this->status === self::STATUS_GRACE
            && $this->graceStage() === self::GRACE_STAGE_LOCKED;
    }

    /** Sedang di masa tenggang, tahap mana pun. */
    public function isInGrace(): bool
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
