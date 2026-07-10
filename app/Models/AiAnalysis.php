<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAnalysis extends Model
{
    use BelongsToTenant;

    public const TYPE_GENERAL = 'general';

    public const TYPE_DISCOUNT = 'discount';

    public const TYPE_PROFIT_PROJECTION = 'profit_projection';

    public const TYPE_CUSTOM = 'custom';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['tenant_id', 'user_id', 'type', 'status', 'params', 'prompt', 'result', 'error', 'tokens_used'];

    protected function casts(): array
    {
        return ['params' => 'array'];
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
}
