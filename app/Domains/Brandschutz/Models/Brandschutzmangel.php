<?php

namespace App\Domains\Brandschutz\Models;

use App\Domains\Brandschutz\Enums\MangelSchwere;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Ein bei einer Brandschutz-Begehung festgestellter Mangel mit Behebungs-Workflow.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $brandschutzbegehung_id
 * @property string $beschreibung
 * @property MangelSchwere $schwere
 * @property Carbon|null $frist
 * @property Carbon|null $behoben_am
 * @property string|null $behoben_notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Brandschutzbegehung $begehung
 * @property-read Tenant $tenant
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzmangel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzmangel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzmangel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzmangel whereBehobenAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzmangel whereBehobenNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzmangel whereBeschreibung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzmangel whereBrandschutzbegehungId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzmangel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzmangel whereFrist($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzmangel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzmangel whereSchwere($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzmangel whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Brandschutzmangel whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Brandschutzmangel extends BaseModel
{
    protected $table = 'brandschutzmaengel';

    protected $fillable = [
        'tenant_id', 'brandschutzbegehung_id', 'beschreibung', 'schwere',
        'frist', 'behoben_am', 'behoben_notiz',
    ];

    protected $casts = [
        'schwere' => MangelSchwere::class,
        'frist' => 'date',
        'behoben_am' => 'date',
    ];

    /** @return BelongsTo<Brandschutzbegehung, $this> */
    public function begehung(): BelongsTo
    {
        return $this->belongsTo(Brandschutzbegehung::class, 'brandschutzbegehung_id');
    }

    public function istOffen(): bool
    {
        return $this->behoben_am === null;
    }
}
