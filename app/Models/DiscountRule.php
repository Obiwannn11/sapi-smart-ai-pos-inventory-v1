<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu aturan diskon yang disetujui owner ([BL-018]).
 *
 * **Aturan ini ADALAH persetujuannya.** Backlognya meminta fitur ini dimulai
 * dari "sarankan lalu owner menyetujui, bukan otomatis" — dan bentuk
 * persetujuan itu adalah baris di tabel ini. Sistem tidak pernah menurunkan
 * harga sendiri; ia hanya memberlakukan potongan yang sudah owner tuliskan,
 * dengan alasan dan masa berlakunya.
 */
class DiscountRule extends Model
{
    use BelongsToTenant, HasFactory;

    /** Mendekati kedaluwarsa — satu-satunya pemicu yang potongannya mendalam seiring waktu. */
    public const TRIGGER_NEAR_EXPIRY = 'near_expiry';

    /** Lama tak terjual. Pemicunya bukan tanggal, jadi potongannya rata. */
    public const TRIGGER_DEAD_STOCK = 'dead_stock';

    /** Alasan owner sendiri — promo, salah beli, apa pun. */
    public const TRIGGER_MANUAL = 'manual';

    protected $fillable = [
        'tenant_id', 'product_variant_id', 'trigger', 'percent', 'max_percent',
        'reason', 'starts_on', 'ends_on', 'is_active',
    ];

    protected $attributes = [
        'trigger' => self::TRIGGER_MANUAL,
        'is_active' => true,
    ];

    /**
     * `date:Y-m-d`, bukan `date` — dan bedanya BUKAN kosmetik ([BL-082]).
     *
     * Cast `date` menyimpan tengah malam menurut zona bisnis, lalu
     * menyerialkannya ke JSON sebagai UTC: `2026-08-20` berangkat dari server
     * sebagai `"2026-08-19T16:00:00.000000Z"` di Asia/Makassar. Layar yang
     * memotongnya dengan `.slice(0, 10)` — dan semuanya memotong begitu —
     * membaca tanggal SEHARI SEBELUMNYA.
     *
     * Tiga akibatnya, dan yang ketiga merusak data:
     *
     *   1. Kolom "Berlaku" menyebut tanggal yang salah.
     *   2. Lencana "Belum mulai"/"Sudah berakhir" berpindah sehari lebih awal
     *      daripada `activeOn()`, yang membandingkan tanggal di SQL dengan
     *      benar. Layar dan kasir jadi tidak sependapat.
     *   3. Membuka formulir Edit mengisi tanggalnya dengan nilai yang sudah
     *      mundur sehari, dan menyimpannya menuliskan kemunduran itu kembali
     *      ke basis data. Aturan yang berulang kali disunting merayap mundur,
     *      sehari tiap suntingan.
     *
     * Kolomnya memang tanggal-tanpa-jam, jadi ia tidak punya urusan dengan zona
     * waktu sama sekali; formatnya yang dikunci, bukan zonanya yang ditambal.
     */
    protected function casts(): array
    {
        return [
            'percent' => 'decimal:2',
            'max_percent' => 'decimal:2',
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public static function triggers(): array
    {
        return [self::TRIGGER_NEAR_EXPIRY, self::TRIGGER_DEAD_STOCK, self::TRIGGER_MANUAL];
    }

    /**
     * Aturan yang berlaku pada tanggal tersebut.
     *
     * Sama seperti `UpsellRule::scopeActiveOn()`, dan dengan keterbatasan yang
     * sama: harga hasilnya ikut ter-snapshot katalog offline, jadi perangkat
     * yang seharian offline bisa menjual dengan diskon yang berakhir semalam.
     * Di sini konsekuensinya UANG, bukan sekadar ajakan yang basi — lihat
     * catatan di DiscountService.
     *
     * @param  Builder<DiscountRule>  $query
     * @return Builder<DiscountRule>
     */
    public function scopeActiveOn(Builder $query, Carbon $date): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $date->toDateString()))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $date->toDateString()));
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
