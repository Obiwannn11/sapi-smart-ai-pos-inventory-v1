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

    /**
     * Sebab dijadwalkannya kembali ke jalur harga tetap. Tenant menarik
     * persetujuannya sendiri, versus omzetnya melewati ujung tangga Adaptif —
     * hanya yang kedua ikut memindahkan paket ke penampung Adaptif.
     */
    public const REVERT_REVOKED = 'revoked';

    public const REVERT_ABOVE_CEILING = 'above_ceiling';

    protected $fillable = [
        'tenant_id', 'plan_id', 'pricing_track', 'track_changed_at', 'track_reverts_at',
        'track_revert_reason',
        'seats', 'seat_high_water', 'provisional_blocked', 'price_locked',
        'purchased_extra_seats', 'scheduled_extra_seats', 'seat_release_at',
        'purchased_ai_blocks', 'scheduled_ai_blocks', 'ai_quota_release_at',
        'trial_ends_at', 'current_period_start', 'current_period_end', 'billing_anchor_day',
    ];

    protected function casts(): array
    {
        return [
            'provisional_blocked' => 'boolean',
            'price_locked' => 'decimal:2',
            'track_changed_at' => 'datetime',
            'track_reverts_at' => 'date',
            'seat_release_at' => 'date',
            'ai_quota_release_at' => 'date',
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
     * **Bukan lagi dasar tagihan** sejak keputusan pemilik kedua 2026-08-07
     * (`[BL-053]`): seat tambahan ditagih karena DIBELI, terpakai atau tidak,
     * jadi puncak pemakaian tidak lagi menentukan nominal apa pun. Yang
     * dijawabnya sekarang tinggal satu: `active_seats` sebagai dimensi Harga
     * Adaptif, dibaca `ActiveSeatsResolver`. Di sana alasan lamanya masih utuh —
     * menonaktifkan staf sehari sebelum penagihan tidak boleh menurunkan bracket
     * sebulan penuh.
     */
    public function recordSeatUsage(): void
    {
        $used = $this->activeSeatsUsed();

        if ($used > $this->seat_high_water) {
            $this->update(['seat_high_water' => $used]);
        }
    }

    /**
     * Seat tambahan yang ditagih untuk periode yang dibuka `$periodStart`.
     *
     * Hak yang dibeli, bukan pemakaian yang diamati — itulah seluruh isi
     * keputusan pemilik kedua 2026-08-07. Paket memberi 3 seat, tenant membeli 2
     * lagi, yang benar-benar dipakai baru 4: tagihannya tetap 5 seat. Satu seat
     * menganggur adalah kapasitas yang ia beli, bukan diskon yang ia dapat.
     *
     * `$periodStart` menentukan apakah pelepasan yang sudah dijadwalkan ikut
     * berlaku. Ia harus periode yang DITAGIH, bukan `now()`: tagihan terbit
     * `invoice_lead_days` sebelum periode berjalan habis, jadi menanyakan
     * keadaan hari ini akan menagih periode depan dengan hak hari ini. Tanpa
     * `$periodStart` yang dijawab adalah hak yang berlaku sekarang.
     */
    public function entitledExtraSeats(?CarbonInterface $periodStart = null): int
    {
        $released = $this->seat_release_at !== null
            && $this->scheduled_extra_seats !== null
            && $this->seat_release_at->lte($periodStart ?? now());

        return (int) ($released ? $this->scheduled_extra_seats : $this->purchased_extra_seats);
    }

    /**
     * Ada pelepasan seat yang sudah diminta tapi belum berlaku?
     */
    public function hasPendingSeatRelease(): bool
    {
        return $this->seat_release_at !== null && $this->scheduled_extra_seats !== null;
    }

    /**
     * Blok kuota AI yang berhak ditagih pada periode tertentu (`[BL-069]`).
     *
     * Kembarannya `entitledExtraSeats()`, termasuk perkara `$periodStart`-nya:
     * ia harus periode yang DITAGIH, bukan `now()`. Tagihan terbit
     * `invoice_lead_days` sebelum periode berjalan habis, jadi menanyakan
     * keadaan hari ini akan menagih periode depan dengan hak hari ini — dan
     * pelepasan yang jatuh persis di antara keduanya tertagih satu periode lebih
     * lama daripada yang dijanjikan.
     */
    public function entitledAiBlocks(?CarbonInterface $periodStart = null): int
    {
        $released = $this->ai_quota_release_at !== null
            && $this->scheduled_ai_blocks !== null
            && $this->ai_quota_release_at->lte($periodStart ?? now());

        return (int) ($released ? $this->scheduled_ai_blocks : $this->purchased_ai_blocks);
    }

    /**
     * Berapa analisis per hari yang ditambahkan blok yang berlaku SEKARANG.
     *
     * Perkaliannya di sini, bukan di `AiQuota`, supaya hanya ada satu tempat
     * yang tahu bahwa satu blok berarti `block_size` analisis. Yang dibaca
     * adalah hak hari ini — kuota berlaku harian, jadi pertanyaannya memang
     * "berapa jatah saya hari ini", bukan "berapa yang ditagihkan".
     */
    public function purchasedAiDailyQuota(): int
    {
        return $this->entitledAiBlocks() * (int) config('subscription.ai_quota.block_size', 0);
    }

    /**
     * Ada pelepasan kuota AI yang sudah diminta tapi belum berlaku?
     */
    public function hasPendingAiQuotaRelease(): bool
    {
        return $this->ai_quota_release_at !== null && $this->scheduled_ai_blocks !== null;
    }

    public function isSubsidized(): bool
    {
        return $this->pricing_track === self::TRACK_SUBSIDIZED;
    }

    /**
     * Tarif bulanan yang berlaku bagi tenant ini — harga terkunci bila ada,
     * tarif paket bila tidak.
     *
     * **`price_locked` yang nol dibaca sebagai KOSONG, bukan sebagai tarif Rp 0**
     * (keputusan 2026-08-10). Kolomnya desimal, jadi `0.00` bukan `null` dan
     * `price_locked ?? base_price` tidak pernah jatuh ke tarif paket: tenant
     * `paid-1` yang kolomnya berisi nol membaca "Tarif Anda sekarang: Rp 0/bulan"
     * di layar sementara penerbit menyiapkan tagihan Rp 100.000 untuknya. Layar
     * dan tagihan tidak boleh menyebut dua angka.
     *
     * Nol di kolom itu tidak pernah menjadi kesepakatan yang perlu dihormati.
     * Tagihan langganan Rp 0 tidak bisa lahir sendiri — `issueDuePeriodInvoices()`
     * menolak menerbitkannya dan `settleIfFree()` menolak melunasinya — sehingga
     * satu-satunya jalan menuju nol adalah nilai yang berarti "belum diketahui"
     * yang terlanjur ditulis sebagai angka: backfill migrasi
     * `2026_07_24_181638` dan bawaan `SubscriptionFactory`. Yang jujur adalah
     * `null`, seperti yang ditulis `startTrial()`.
     *
     * Tenant yang memang tidak membayar apa pun tetap terbaca benar: ia tinggal
     * di paket `free`, yang `base_price`-nya juga 0. Menganggap nol sebagai
     * kosong karena itu tidak pernah membesarkan tarif siapa pun di layar.
     *
     * Ini pertanyaan TAMPILAN, bukan penagihan. Penagih tidak pernah membaca
     * `price_locked` sama sekali — `issueDuePeriodInvoices()` selalu menghitung
     * ulang lewat `PricingService::resolveFor()`; lihat koreksi *grandfathering*
     * di `[BL-041]`.
     */
    public function effectivePrice(): float
    {
        $locked = (float) $this->price_locked;

        return $locked > 0.0
            ? $locked
            : (float) ($this->plan?->base_price ?? 0);
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
