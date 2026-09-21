<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = "akun platform ini boleh membuka modul ini".
 *
 * Katalog modul yang sah ada di config/platform-rbac.php.
 */
class PlatformUserModule extends Model
{
    protected $fillable = ['platform_user_id', 'module'];

    // --- Relationships ---
    public function platformUser(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class);
    }
}
