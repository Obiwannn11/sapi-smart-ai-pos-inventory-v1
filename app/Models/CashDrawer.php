<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class CashDrawer extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * Umur sesi kas, dalam jam ([BL-088]).
     *
     * Konstanta model dan bukan config, mengikuti `Transaction::OPEN_BILL_LIFETIME_HOURS`
     * yang menjawab pertanyaan sebentuk: satu keputusan pemilik yang berlaku
     * untuk seluruh aplikasi, bukan setelan per pemasangan.
     *
     * 24 jam datang dari kalimat pemiliknya sendiri — "masa hidup kas cuma
     * sehari". Angka ini menentukan kapan `cash-drawers:expire` menutup paksa,
     * dan karena itu jadwalnya **tiap jam**: sapuan harian akan membuat batas
     * 24 jam berarti "antara satu dan dua hari", tergantung sesi itu lahir
     * beberapa menit sebelum atau sesudah sapuan lewat.
     */
    const MAX_SESSION_HOURS = 24;

    /**
     * Berapa jam sebelum batas, kasir mulai diperingatkan.
     *
     * Peringatan yang muncul sesudah sesi tertutup tidak berguna: uang fisiknya
     * sudah tidak bisa dihitung lagi. Empat jam dipilih karena ia lebih panjang
     * dari sisa shift mana pun yang masuk akal — kasir yang melihatnya masih
     * sempat menutup kasnya sendiri sebelum pulang.
     */
    const STALE_WARNING_HOURS = 4;

    protected $fillable = [
        'tenant_id', 'user_id', 'opening_amount', 'closing_amount',
        'expected_amount', 'difference', 'notes', 'opened_at', 'closed_at',
        'closed_by_system',
    ];

    protected function casts(): array
    {
        return [
            'opening_amount' => 'decimal:2',
            'closing_amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'difference' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'closed_by_system' => 'boolean',
        ];
    }

    /**
     * Kembaran default kolom di migrasi: instance baru harus sudah memegang
     * nilainya sebelum dibaca ulang.
     */
    protected $attributes = [
        'closed_by_system' => false,
    ];

    // --- Helpers ---
    public function isOpen(): bool
    {
        return is_null($this->closed_at);
    }

    /**
     * Sesi yang masih terbuka milik seorang kasir, bila ada.
     *
     * Satu tempat untuk pencarian yang sebelumnya ditulis ulang di tiap
     * pemanggil. Sejak `[BL-028]` Tahap B langkah 1 ia bukan lagi sekadar
     * kenyamanan: inilah yang menjawab "uang ini masuk laci mana" saat
     * penjualan dicatat, jadi jawabannya harus sama persis di layar kas dan di
     * jalur checkout.
     */
    public static function openFor(User|int $user): ?self
    {
        return self::where('user_id', $user instanceof User ? $user->id : $user)
            ->whereNull('closed_at')
            ->first();
    }

    /**
     * Sesi milik kasir itu yang jendelanya MELINGKUPI satu titik waktu —
     * termasuk sesi yang sudah ditutup ([BL-028] Tahap B langkah 1).
     *
     * Hanya jalur offline yang membutuhkannya. Penjualan offline sudah terjadi
     * secara fisik berjam-jam (bisa berhari-hari) sebelum payload-nya sampai,
     * jadi ia milik laci yang benar-benar menerima uangnya, bukan laci yang
     * kebetulan terbuka saat sinkronisasi berjalan. Alasan yang sama sudah
     * dipakai papan antrian dan tanggal efektif rekonsiliasi.
     *
     * Batas jendelanya dibuat sama persis dengan
     * `CashDrawerReconciliation::window()` — `opened_at` sampai `closed_at`,
     * atau sampai sekarang bila sesinya masih terbuka. Dua bentuk jendela yang
     * berbeda untuk pertanyaan yang sama adalah cara termurah membuat sebuah
     * penjualan tercatat di laci yang tidak akan memungutnya.
     */
    public static function coveringAt(User|int $user, mixed $at): ?self
    {
        $moment = $at instanceof \DateTimeInterface ? Carbon::instance($at) : Carbon::parse($at);

        return self::where('user_id', $user instanceof User ? $user->id : $user)
            ->where('opened_at', '<=', $moment)
            ->where(function (Builder $query) use ($moment) {
                $query->where('closed_at', '>=', $moment);

                // Sesi yang masih terbuka melingkupi apa pun sampai sekarang,
                // dan TIDAK melingkupi waktu di masa depan — `commitOffline()`
                // memberi toleransi clock skew beberapa menit, jadi kasusnya
                // bukan hipotesis.
                if ($moment->lessThanOrEqualTo(Carbon::now())) {
                    $query->orWhereNull('closed_at');
                }
            })
            ->orderByDesc('opened_at')
            ->first();
    }

    /** Titik waktu sebelum mana sesi yang masih terbuka dianggap lewat umur. */
    public static function staleCutoff(mixed $now = null): Carbon
    {
        $reference = $now instanceof \DateTimeInterface
            ? Carbon::instance($now)
            : Carbon::now();

        return $reference->copy()->subHours(self::MAX_SESSION_HOURS);
    }

    /**
     * Sesi terbuka yang sudah lewat umurnya. Inilah yang disapu
     * `cash-drawers:expire`.
     *
     * @param  Builder<CashDrawer>  $query
     * @return Builder<CashDrawer>
     */
    public function scopeStale(Builder $query, mixed $now = null): Builder
    {
        return $query
            ->whereNull('closed_at')
            ->where('opened_at', '<=', self::staleCutoff($now));
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Pengungkapan angka "seharusnya di laci" pada sesi ini ([BL-090]). */
    public function reveals(): HasMany
    {
        return $this->hasMany(CashDrawerReveal::class);
    }

    /** Uang keluar-masuk laci di luar penjualan ([BL-087]). */
    public function movements(): HasMany
    {
        return $this->hasMany(CashDrawerMovement::class);
    }

    /**
     * Penjualan yang uangnya masuk ke laci ini ([BL-028] Tahap B langkah 1).
     *
     * Hanya terisi untuk penjualan yang lahir sesudah migrasi kolomnya;
     * rekonsiliasi belum membaca relasi ini. Lihat docblock migrasinya.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
