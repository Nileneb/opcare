<?php

namespace App\Domains\Masterdata\Models;

use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResidentContact extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'name', 'beziehung', 'telefon', 'benachrichtigen', 'hinweis'];

    protected $casts = ['benachrichtigen' => 'boolean'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
