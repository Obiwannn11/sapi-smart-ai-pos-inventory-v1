<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * Satu-satunya tipe yang berarti "uang fisik berpindah tangan di sini".
     *
     * Definisi "non-tunai" di seluruh aplikasi diturunkan dari sini, bukan dari
     * daftar terpisah: `qris_static`, `qris_dynamic`, dan `bank_transfer`
     * adalah non-tunai karena mereka BUKAN ini. Menambah tipe baru di kemudian
     * hari otomatis ikut terhitung non-tunai, yang memang jawaban yang benar.
     */
    public const TYPE_CASH = 'cash';

    protected $fillable = ['tenant_id', 'name', 'type', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function isCash(): bool
    {
        return $this->type === self::TYPE_CASH;
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
