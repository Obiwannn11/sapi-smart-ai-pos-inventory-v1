<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
        'trial_ends_at', 'current_period_start', 'current_period_end', 'billing_anchor_day',
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

    /**
     * Hari dalam bulan yang menjadi tanggal tagih langganan ini (1–31).
     *
     * Jatuh ke tanggal akhir periode berjalan bila jangkarnya belum pernah
     * ditulis — itu benar untuk baris lama, dan **hanya** benar selama tanggal
     * itu belum pernah terjepit bulan pendek. Karena itulah jangkarnya disimpan
     * begitu periode berbayar pertama dibuka, bukan dihitung ulang tiap kali.
     */
    public function billingAnchorDay(): int
    {
        return $this->billing_anchor_day
            ?? $this->current_period_end?->day
            ?? $this->created_at?->day
            ?? 1;
    }

    /**
     * Tanggal tagih untuk bulan yang memuat `$month`, dijepit ke hari terakhir
     * bila bulan itu terlalu pendek.
     *
     * Penjepitannya sementara, bukan permanen: yang dijepit adalah hasilnya,
     * sementara jangkarnya tetap utuh. Jangkar 31 menghasilkan 28 Feb lalu
     * kembali 31 Mar — inilah yang membedakan `[BL-030]` dari sekadar mengganti
     * `addMonth()` menjadi `addMonthNoOverflow()`, yang akan menetap di 28.
     */
    public function anchoredDateIn(CarbonInterface $month): Carbon
    {
        $start = Carbon::parse($month)->startOfMonth();

        return $start->addDays(min($this->billingAnchorDay(), $start->daysInMonth) - 1);
    }

    /**
     * Tanggal tagih berikutnya setelah `$from`, mengikuti jangkar.
     *
     * `addMonthNoOverflow()` dipakai untuk berpindah bulan, bukan untuk
     * menentukan tanggalnya — pindah bulan dari 31 Jan tanpa penjaga luberan
     * mendarat di 3 Mar dan melewatkan Februari sama sekali. Tanggalnya
     * kemudian ditentukan ulang oleh jangkar, jadi penjepitan bulan sebelumnya
     * tidak menular.
     */
    public function nextAnchoredDateAfter(CarbonInterface $from): Carbon
    {
        return $this->anchoredDateIn(Carbon::parse($from)->addMonthNoOverflow());
    }
}
