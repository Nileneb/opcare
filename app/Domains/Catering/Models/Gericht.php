<?php

namespace App\Domains\Catering\Models;

use App\Domains\Catering\Enums\LmivAllergen;
use App\Domains\Catering\Enums\Mahlzeit;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Ein Gericht des Speiseplans mit LMIV-Allergenkennzeichnung. Tenant-scoped über BaseModel.
 *
 * @property int $id
 * @property int $tenant_id
 * @property Carbon $datum
 * @property Mahlzeit $mahlzeit
 * @property string $bezeichnung
 * @property array<array-key, mixed>|null $allergene
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gericht newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gericht newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gericht query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gericht whereAllergene($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gericht whereBezeichnung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gericht whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gericht whereDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gericht whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gericht whereMahlzeit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gericht whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Gericht whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Gericht extends BaseModel
{
    protected $table = 'catering_gerichte';

    protected $fillable = ['tenant_id', 'datum', 'mahlzeit', 'bezeichnung', 'allergene'];

    protected $casts = [
        'datum' => 'date',
        'mahlzeit' => Mahlzeit::class,
        'allergene' => 'array',
    ];

    /** @return array<int, LmivAllergen> */
    public function allergeneEnum(): array
    {
        return array_values(array_filter(array_map(
            fn (string $v) => LmivAllergen::tryFrom($v),
            $this->allergene ?? [],
        )));
    }
}
