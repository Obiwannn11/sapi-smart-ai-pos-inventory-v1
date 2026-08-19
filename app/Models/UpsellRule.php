<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu aturan saran jual yang ditulis owner ([BL-074]).
 *
 * Bedanya dari tiga strategi mesin bukan pada bentuk sarannya — keluarannya
 * `Suggestion` yang sama persis — melainkan pada dari mana sarannya berasal.
 * Mesin menemukan; ini diperintahkan.
 */
class UpsellRule extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'trigger_variant_id', 'suggested_variant_id',
        'note', 'starts_on', 'ends_on', 'priority', 'is_active',
    ];

    /**
     * Kembaran default kolom di migrasi, dengan alasan yang sama seperti di
     * Tenant: instance baru harus sudah memegang nilainya sebelum dibaca ulang.
     */
    protected $attributes = [
        'priority' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Aturan yang boleh muncul di kasir HARI INI.
     *
     * Jendela tanggalnya diperiksa di sini, di sisi server, saat indeks dirakit
     * — dan di situ letak keterbatasan yang sudah diketahui: indeksnya ikut
     * ter-snapshot `useCatalogCache`, jadi perangkat yang seharian offline akan
     * terus menawarkan promo yang berakhir semalam. Itu diterima karena
     * HARGANYA tetap harga katalog: yang basi hanya ajakannya, bukan uangnya.
     * Persoalan yang sama akan jauh lebih serius di `[BL-018]`, di mana yang
     * ikut basi adalah potongan harganya.
     *
     * @param  Builder<UpsellRule>  $query
     * @return Builder<UpsellRule>
     */
    public function scopeActiveOn(Builder $query, Carbon $date): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $date->toDateString()))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $date->toDateString()));
    }

    /** Aturan tanpa pemicu berlaku pada setiap keranjang yang tidak kosong. */
    public function isCartLevel(): bool
    {
        return $this->trigger_variant_id === null;
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function triggerVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'trigger_variant_id');
    }

    public function suggestedVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'suggested_variant_id');
    }
}
