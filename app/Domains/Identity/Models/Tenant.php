<?php

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $traeger
 * @property string|null $ik_nummer
 * @property array<array-key, mixed>|null $settings
 * @property bool $aktiv
 * @property string|null $strasse
 * @property string|null $hausnummer
 * @property string|null $plz
 * @property string|null $ort
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static Builder<static>|Tenant aktiv()
 * @method static Builder<static>|Tenant newModelQuery()
 * @method static Builder<static>|Tenant newQuery()
 * @method static Builder<static>|Tenant query()
 * @method static Builder<static>|Tenant whereAktiv($value)
 * @method static Builder<static>|Tenant whereCreatedAt($value)
 * @method static Builder<static>|Tenant whereHausnummer($value)
 * @method static Builder<static>|Tenant whereId($value)
 * @method static Builder<static>|Tenant whereIkNummer($value)
 * @method static Builder<static>|Tenant whereName($value)
 * @method static Builder<static>|Tenant whereOrt($value)
 * @method static Builder<static>|Tenant wherePlz($value)
 * @method static Builder<static>|Tenant whereSettings($value)
 * @method static Builder<static>|Tenant whereSlug($value)
 * @method static Builder<static>|Tenant whereStrasse($value)
 * @method static Builder<static>|Tenant whereTraeger($value)
 * @method static Builder<static>|Tenant whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Tenant extends Model
{
    protected $fillable = ['name', 'traeger', 'slug', 'ik_nummer', 'settings', 'aktiv', 'strasse', 'hausnummer', 'plz', 'ort'];

    protected $casts = [
        'settings' => 'array',
        'aktiv' => 'boolean',
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
