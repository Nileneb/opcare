<?php

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    protected $fillable = ['name', 'traeger', 'slug', 'ik_nummer', 'settings', 'aktiv'];

    protected $casts = [
        'settings' => 'array',
        'aktiv'    => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeAktiv(Builder $q): Builder
    {
        return $q->where('aktiv', true);
    }
}
