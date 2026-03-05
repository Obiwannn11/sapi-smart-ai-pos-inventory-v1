<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Modifier extends Model
{
    use SoftDeletes;

    protected $fillable = ['modifier_group_id', 'name', 'extra_price'];

    protected function casts(): array
    {
        return [
            'extra_price' => 'decimal:2',
        ];
    }

    // --- Relationships ---
    public function group(): BelongsTo
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
