<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    /**
     * Jalur harga. `normal` = tarif publik, data bisnis tertutup penuh.
     * `subsidized` = tenant sukarela membuka omsetnya sebagai ganti harga yang
     * menyesuaikan kemampuan bayar (Tahap C).
     *
     * Nilai yang sama juga hidup di `tenants.pricing_track`, yang menjadi
     * gerbang privasi bagi job penghitung omset.
     */
    public const TRACK_NORMAL = 'normal';

    public const TRACK_SUBSIDIZED = 'subsidized';

    protected $fillable = [
        'tenant_id', 'plan_id', 'pricing_track', 'track_changed_at', 'track_reverts_at',
        'seats', 'seat_high_water', 'provisional_blocked', 'price_locked',
        'trial_ends_at', 'current_period_start', 'current_period_end',
    ];

    protected function casts(): array
    {
        return [
            'provisional_blocked' => 'boolean',
            'price_locked' => 'decimal:2',
            'track_changed_at' => 'datetime',
            'track_reverts_at' => 'date',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'date',
            'current_period_end' => 'date',
        ];
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // --- Helpers ---

    /**
     * Jumlah pengguna aktif tenant ini. Inilah angka yang ditegakkan saat staf
     * baru ditambahkan — pengguna nonaktif tidak ikut dihitung, supaya
     * mengganti staf yang keluar tidak menuntut upgrade paket.
     *
     * Menghitung seat tidak menyentuh data bisnis sama sekali, hanya `count()`
     * di tabel `users` — jadi aman untuk kedua jalur harga.
     */
    public function activeSeatsUsed(): int
    {
        return $this->tenant->users()->where('is_active', true)->count();
    }

    public function hasSeatAvailable(): bool
    {
        return $this->activeSeatsUsed() < $this->seats;
    }

    /**
     * Naikkan penanda puncak bila pemakaian aktif sekarang melampauinya.
     *
     * Puncak inilah — bukan jumlah aktif saat penagihan — yang menjadi dasar
     * tagihan periode berjalan. Tanpa itu, menonaktifkan staf sehari sebelum
     * tanggal tagih akan menghemat biaya sebulan penuh, dan batas seat berubah
     * jadi formalitas.
     */
    public function recordSeatUsage(): void
    {
        $used = $this->activeSeatsUsed();

        if ($used > $this->seat_high_water) {
            $this->update(['seat_high_water' => $used]);
        }
    }

    public function isSubsidized(): bool
    {
        return $this->pricing_track === self::TRACK_SUBSIDIZED;
    }
}
