<?php

namespace App\Traits;

use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // Auto-filter: semua query hanya ambil data tenant yang sedang login
        static::addGlobalScope(new TenantScope());

        // Auto-assign: setiap record baru otomatis diisi tenant_id
        static::creating(function (Model $model) {
            if (auth()->check() && !$model->tenant_id) {
                $model->tenant_id = auth()->user()->tenant_id;
            }
        });
    }
}
