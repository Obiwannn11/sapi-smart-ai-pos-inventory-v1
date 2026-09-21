<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Omset bulanan tenant jalur subsidi.
 *
 * Sengaja TIDAK memakai trait BelongsToTenant. TenantScope memfilter dengan
 * `auth()->user()->tenant_id`, dan akun platform tidak punya tenant_id — model
 * ini akan selalu mengembalikan kosong justru di tempat ia dibutuhkan. Batas
 * aksesnya dijaga di lapisan lain: job memfilter `pricing_track`, dan halaman
 * platform digerbang modul `revenue_data`.
 */
class TenantMonthlyMetric extends Model
{
    /** @use HasFactory<\Database\Factories\TenantMonthlyMetricFactory> */
    use HasFactory;

    protected $fillable = ['tenant_id', 'period', 'revenue', 'transaction_count', 'computed_at'];

    protected function casts(): array
    {
        return [
            'revenue' => 'decimal:2',
            'computed_at' => 'datetime',
        ];
    }

    // --- Relationships ---
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
