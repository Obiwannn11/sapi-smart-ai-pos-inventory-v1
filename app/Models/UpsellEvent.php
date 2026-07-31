<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nasib satu saran upsell yang ditampilkan ke manusia.
 *
 * Ditulis sekali saat checkout (POS online, POS offline, dan self-order lewat
 * jalur yang sama) dan tidak pernah diubah setelahnya.
 */
class UpsellEvent extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_ATTACH = 'attach';

    public const TYPE_PRESSED_STOCK = 'pressed_stock';

    public const TYPE_UPSIZE = 'upsize';

    public const SURFACE_POS = 'pos';

    public const SURFACE_SELF_ORDER = 'self_order';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_IGNORED = 'ignored';

    /**
     * Ditolak pelanggan setelah BENAR-BENAR ditawarkan — berbeda dari
     * `ignored`, yang berarti saran sempat tampil lalu berlalu tanpa keputusan.
     * Mencampur keduanya membuat angka "berapa persen saran diterima" tak
     * berarti: penyebutnya jadi campuran penawaran nyata dan saran yang cuma
     * lewat ([BL-025]).
     */
    public const STATUS_REJECTED = 'rejected';

    public const REASON_COOCCURRENCE = 'cooccurrence';

    public const REASON_CATALOG = 'catalog';

    public const REASON_NEAR_EXPIRY = 'near_expiry';

    public const REASON_DEAD_STOCK = 'dead_stock';

    public const REASON_PRICE_STEP = 'price_step';

    protected $fillable = [
        'tenant_id', 'transaction_id', 'type', 'surface', 'status', 'reason',
        'trigger_variant_id', 'suggested_variant_id', 'suggested_modifier_id',
        'label', 'extra_amount',
    ];

    protected function casts(): array
    {
        return [
            'extra_amount' => 'decimal:2',
        ];
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return [self::TYPE_ATTACH, self::TYPE_PRESSED_STOCK, self::TYPE_UPSIZE];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [self::STATUS_ACCEPTED, self::STATUS_IGNORED, self::STATUS_REJECTED];
    }

    /**
     * Saran yang benar-benar sampai ke pelanggan — penyebut yang jujur untuk
     * tingkat penerimaan.
     *
     * @return list<string>
     */
    public static function offeredStatuses(): array
    {
        return [self::STATUS_ACCEPTED, self::STATUS_REJECTED];
    }

    /**
     * @return list<string>
     */
    public static function reasons(): array
    {
        return [
            self::REASON_COOCCURRENCE,
            self::REASON_CATALOG,
            self::REASON_NEAR_EXPIRY,
            self::REASON_DEAD_STOCK,
            self::REASON_PRICE_STEP,
        ];
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function triggerVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'trigger_variant_id');
    }

    public function suggestedVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'suggested_variant_id');
    }

    public function suggestedModifier(): BelongsTo
    {
        return $this->belongsTo(Modifier::class, 'suggested_modifier_id');
    }
}
