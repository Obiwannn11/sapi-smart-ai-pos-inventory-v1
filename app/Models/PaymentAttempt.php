<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu percobaan pembayaran sebuah tagihan lewat payment gateway.
 *
 * Sengaja TIDAK memakai `TenantScope`, mengikuti `Invoice`: baris ini dibaca
 * dari dua dunia yang berbeda — halaman tenant (ada pengguna yang masuk) dan
 * webhook penyedia (tidak ada siapa-siapa). Scope global yang diam-diam
 * mengosongkan hasil di dunia kedua akan membuat notifikasi pembayaran yang sah
 * terlihat seperti transaksi tak dikenal. Pemisahan tenant di sisi halaman
 * ditegakkan terang-terangan lewat `abort_if` di controllernya, sama seperti
 * `Invoice`.
 */
class PaymentAttempt extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentAttemptFactory> */
    use HasFactory;

    /** Instruksi sudah terbit, penyedia belum mengabarkan apa pun. */
    public const STATUS_PENDING = 'pending';

    /** Dibayar, dan tagihannya sudah dilunasi lewat `InvoiceSettlement`. */
    public const STATUS_PAID = 'paid';

    /** Penyedia mengabarkan pembayarannya gagal. */
    public const STATUS_FAILED = 'failed';

    /** Lewat `expires_at` tanpa pernah dibayar. */
    public const STATUS_EXPIRED = 'expired';

    /**
     * Dibayar, tapi nominalnya tidak sama dengan yang ditagih.
     *
     * Keadaan ini sengaja BUKAN `paid`: tagihannya tidak dilunasi dan menunggu
     * orang. Melunasi kekurangan bayar secara diam-diam berarti menutup selisih
     * dari uang sendiri tanpa seorang pun memutuskannya.
     */
    public const STATUS_MISMATCH = 'mismatch';

    protected $fillable = [
        'tenant_id', 'invoice_id', 'gateway', 'channel', 'external_id',
        'amount', 'status', 'expires_at', 'paid_at', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // --- Helpers ---
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Masih menunggu pembayaran DAN belum lewat tenggatnya.
     *
     * Dua hal yang berbeda, dan keduanya harus benar. Percobaan yang tenggatnya
     * lewat tetap berstatus `pending` sampai ada yang membacanya — tidak ada
     * penjadwal yang mengubahnya, dan sengaja begitu: yang menentukan nasib
     * sebuah percobaan adalah waktunya, bukan kapan kita sempat memeriksanya.
     */
    public function isOpen(): bool
    {
        return $this->isPending() && ! $this->hasExpired();
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
