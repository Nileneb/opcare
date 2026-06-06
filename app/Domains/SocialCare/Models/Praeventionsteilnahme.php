<?php

namespace App\Domains\SocialCare\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Dokumentierte Teilnahme eines Bewohners an einem Präventionsprogramm (Datum, Dauer, Beobachtung).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $praeventionsprogramm_id
 * @property int $resident_id
 * @property Carbon $datum
 * @property int $dauer_minuten
 * @property string|null $beobachtung
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Praeventionsprogramm $programm
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsteilnahme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsteilnahme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsteilnahme query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsteilnahme whereBeobachtung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsteilnahme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsteilnahme whereDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsteilnahme whereDauerMinuten($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsteilnahme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsteilnahme wherePraeventionsprogrammId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsteilnahme whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsteilnahme whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Praeventionsteilnahme whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Praeventionsteilnahme extends BaseModel
{
    protected $table = 'praeventionsteilnahmen';

    protected $fillable = ['tenant_id', 'praeventionsprogramm_id', 'resident_id', 'datum', 'dauer_minuten', 'beobachtung'];

    protected $casts = ['datum' => 'date', 'dauer_minuten' => 'integer'];

    protected $attributes = ['dauer_minuten' => 30];

    public function programm(): BelongsTo
    {
        return $this->belongsTo(Praeventionsprogramm::class, 'praeventionsprogramm_id');
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
