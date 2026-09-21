<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'category_id', 'name', 'image', 'is_active',
    ];

    /**
     * URL gambar ikut di setiap serialisasi, bukan ditempel per controller.
     * Kolom `image` menyimpan path di disk privat yang tidak boleh bocor ke
     * browser; menempelkan URL-nya di sini berarti tidak ada satu pun halaman
     * yang tergoda menyusun sendiri "/storage/{$product->image}" lagi.
     *
     * @var list<string>
     */
    protected $appends = ['image_url', 'image_thumb_url'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // --- Gambar ---

    /**
     * URL rendition besar (800x800), atau null bila produk tak bergambar.
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->mediaUrl('full');
    }

    /**
     * URL rendition kisi (200x200), untuk grid POS dan daftar produk.
     */
    public function getImageThumbUrlAttribute(): ?string
    {
        return $this->mediaUrl('thumb');
    }

    /**
     * Rute ber-auth yang menyajikan gambar produk ini.
     *
     * Query `v` diturunkan dari path berkas, bukan dari mtime: path berubah
     * setiap gambar diganti (nama berkasnya UUID baru), jadi URL-nya ikut
     * berubah tanpa perlu menyentuh disk untuk setiap baris yang dirender.
     */
    private function mediaUrl(string $size): ?string
    {
        if (! $this->exists || ! $this->image) {
            return null;
        }

        // Relatif, bukan absolut: URL absolut memakai APP_URL, yang di mesin
        // pengembangan dan di belakang proxy kerap berbeda dari host yang
        // sedang dipakai — dan gambar yang host-nya salah gagal diam-diam.
        return route('media.product-image', [
            'product' => $this->getKey(),
            'size' => $size,
            'v' => substr(sha1($this->image), 0, 8),
        ], absolute: false);
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(ModifierGroup::class, 'product_modifier_groups');
    }
}
