<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    /**
     * Slug paket bawaan yang selalu ada — ditulis oleh migrasi `plans`, dipakai
     * saat registrasi membuka masa gratis. Merujuk slug, bukan id, supaya tidak
     * terikat auto-increment yang bisa berbeda antar pemasangan.
     *
     * Berganti dari `dasar` menjadi `free` oleh migrasi
     * `rename_default_plan_to_free` (2026-08-07). Keduanya harus berpindah
     * bersama: `default()` memakai `firstOrFail()`, jadi konstanta yang
     * mendahului migrasinya akan menggagalkan pendaftaran, bukan diam-diam
     * memilih paket yang salah.
     */
    public const SLUG_DEFAULT = 'free';

    /**
     * Batas AI harian, dalam `limits`. Kunci yang tidak ada berarti "ikut
     * bawaan platform", bukan nol — lihat `limit()`.
     */
    public const LIMIT_AI_DAILY = 'ai_daily';

    protected $fillable = [
        'name', 'slug', 'base_price', 'included_seats', 'extra_seat_price', 'limits',
        'is_active', 'is_adaptive_fallback',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'extra_seat_price' => 'decimal:2',
            'limits' => 'array',
            'is_active' => 'boolean',
            'is_adaptive_fallback' => 'boolean',
        ];
    }

    // --- Relationships ---
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    // --- Helpers ---

    /**
     * Paket dasar bawaan. Dipakai registrasi tenant baru.
     */
    public static function default(): self
    {
        return static::where('slug', self::SLUG_DEFAULT)->firstOrFail();
    }

    /**
     * Paket yang menampung tenant jalur Harga Adaptif saat tak ada aturan tarif
     * yang cocok untuknya. `null` bila pemilik SaaS belum menunjuk satu pun.
     *
     * Tidak jatuh diam-diam ke paket termahal atau ke paket dasar: menebak di
     * sini berarti memilih tarif untuk orang tanpa seorang pun memutuskannya.
     * Yang benar adalah panel menagih penunjukannya secara terbuka.
     */
    public static function adaptiveFallback(): ?self
    {
        return static::where('is_adaptive_fallback', true)->first();
    }

    /**
     * Batas paket, atau `$default` bila paket ini tidak menyetelnya.
     *
     * Satu-satunya pintu ke `limits` — pemanggil tidak pernah menyentuh
     * arraynya langsung. Batas berikutnya cukup menambah pemanggil baru, bukan
     * kolom baru dan bukan cabang baru di kelas pembacanya (`[BL-046]`(c)).
     */
    public function limit(string $key, ?int $default = null): ?int
    {
        $value = $this->limits[$key] ?? null;

        return $value === null ? $default : (int) $value;
    }

    /**
     * Setel satu batas paket, atau hapus batasnya bila `$value` null.
     *
     * Menghapus kuncinya, bukan menyimpannya sebagai null: `limit()` membedakan
     * "paket ini tidak menyetel batas" dari "paket ini menyetel batas nol", dan
     * kunci bernilai null akan mengaburkan keduanya jadi satu.
     */
    public function setLimit(string $key, ?int $value): void
    {
        $limits = $this->limits ?? [];

        if ($value === null) {
            unset($limits[$key]);
        } else {
            $limits[$key] = $value;
        }

        $this->limits = $limits === [] ? null : $limits;
    }

    /**
     * Jadikan paket ini penampung jalur Harga Adaptif — atau lepaskan perannya.
     *
     * Penampungnya tunggal, dan ketunggalan itu ditegakkan di sini karena ia
     * satu-satunya tempat perannya berpindah. Dua paket yang sama-sama bertanda
     * penampung akan membuat tarif tenant bergantung pada urutan baris di
     * database — persis jenis kebergantungan yang `matchContext()` susah payah
     * hindari di sisi aturan.
     *
     * Dalam transaksi karena perpindahannya dua tulisan: melepas yang lama dan
     * memasang yang baru. Gagal di antaranya meninggalkan keadaan tanpa
     * penampung sama sekali, dan tenant adaptif tanpa aturan yang cocok kembali
     * kehilangan tarifnya tanpa seorang pun memutuskannya.
     */
    public function setAdaptiveFallback(bool $value): void
    {
        DB::transaction(function () use ($value) {
            if ($value) {
                static::where('id', '!=', $this->id)->update(['is_adaptive_fallback' => false]);
            }

            $this->forceFill(['is_adaptive_fallback' => $value])->save();
        });
    }
}
