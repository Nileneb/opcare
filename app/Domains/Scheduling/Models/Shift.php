<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Scheduling\Database\Factories\ShiftFactory;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends BaseModel
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'kind', 'beginn', 'ende', 'timeslots', 'aktiv'];

    protected $casts = [
        'kind' => ShiftKind::class,
        'timeslots' => 'array',
        'aktiv' => 'boolean',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    protected static function newFactory(): ShiftFactory
    {
        return ShiftFactory::new();
    }
}
