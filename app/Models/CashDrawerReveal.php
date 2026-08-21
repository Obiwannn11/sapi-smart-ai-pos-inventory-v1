<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kali kasir membuka angka "seharusnya di laci" ([BL-090]).
 *
 * Bukan pelanggaran, dan tidak diperlakukan sebagai pelanggaran di mana pun:
 * membukanya sah, tombolnya memang disediakan, dan ada alasan wajar untuk
 * menekannya (mengecek laci di tengah shift, menjawab pertanyaan pemilik).
 * Yang dijawab baris ini hanya satu pertanyaan — apakah angkanya sudah
 * terbaca sebelum uang fisik dihitung — dan pemiliklah yang menilai artinya.
 */
class CashDrawerReveal extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'cash_drawer_id', 'user_id', 'revealed_at'];

    protected function casts(): array
    {
        return [
            'revealed_at' => 'datetime',
        ];
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function cashDrawer(): BelongsTo
    {
        return $this->belongsTo(CashDrawer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
