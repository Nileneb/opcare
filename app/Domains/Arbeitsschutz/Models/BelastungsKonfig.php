<?php

namespace App\Domains\Arbeitsschutz\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Gewichte + Schwellen für den Belastungs-Live-Index je Einrichtung (§ 5 ArbSchG).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $gewicht_pflegelast
 * @property int $gewicht_deckung
 * @property int $gewicht_spitzenzeit
 * @property int $gewicht_ergonomie
 * @property int $schwelle_hoch
 * @property int $schwelle_kritisch
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelastungsKonfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelastungsKonfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BelastungsKonfig query()
 *
 * @mixin \Eloquent
 */
class BelastungsKonfig extends BaseModel
{
    protected $table = 'belastungs_konfigs';

    protected $fillable = [
        'tenant_id',
        'gewicht_pflegelast',
        'gewicht_deckung',
        'gewicht_spitzenzeit',
        'gewicht_ergonomie',
        'schwelle_hoch',
        'schwelle_kritisch',
    ];

    // WHY: firstOrCreate(['tenant_id'=>…]) lädt die DB-Defaults nicht ins frische Model → ohne diese
    // In-Memory-Defaults wären die Werte null (× null = 0 beim Belastungs-Score).
    protected $attributes = [
        'gewicht_pflegelast' => 40,
        'gewicht_deckung' => 35,
        'gewicht_spitzenzeit' => 15,
        'gewicht_ergonomie' => 10,
        'schwelle_hoch' => 60,
        'schwelle_kritisch' => 80,
    ];

    protected $casts = [
        'gewicht_pflegelast' => 'integer',
        'gewicht_deckung' => 'integer',
        'gewicht_spitzenzeit' => 'integer',
        'gewicht_ergonomie' => 'integer',
        'schwelle_hoch' => 'integer',
        'schwelle_kritisch' => 'integer',
    ];

    public static function ensureFor(int $tenantId): self
    {
        return self::firstOrCreate(['tenant_id' => $tenantId]);
    }
}
