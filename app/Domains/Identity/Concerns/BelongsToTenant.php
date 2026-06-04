<?php

namespace App\Domains\Identity\Concerns;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Scopes\TenantScope;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = app(CurrentTenant::class)->id();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
