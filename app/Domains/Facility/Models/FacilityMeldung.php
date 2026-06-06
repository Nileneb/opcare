<?php

namespace App\Domains\Facility\Models;

use App\Domains\Facility\Enums\MeldungPrioritaet;
use App\Domains\Facility\Enums\MeldungStatus;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Mängelmeldung an die Haustechnik (Reparatur/Störung). Jede:r Mitarbeitende kann melden; die Haustechnik
 * arbeitet die Queue ab (offen → in Arbeit → erledigt). Tenant-scoped über BaseModel.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $titel
 * @property string|null $beschreibung
 * @property string|null $standort
 * @property int|null $asset_id
 * @property MeldungPrioritaet $prioritaet
 * @property MeldungStatus $status
 * @property int $gemeldet_von
 * @property Carbon|null $erledigt_am
 * @property string|null $erledigt_notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read FacilityAsset|null $asset
 * @property-read User $melder
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung whereAssetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung whereBeschreibung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung whereErledigtAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung whereErledigtNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung whereGemeldetVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung wherePrioritaet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung whereStandort($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung whereTitel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FacilityMeldung whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class FacilityMeldung extends BaseModel
{
    protected $table = 'facility_meldungen';

    // Defaults schon im frischen Model (nicht erst auf dem gespeicherten Row).
    protected $attributes = ['status' => 'offen', 'prioritaet' => 'mittel'];

    protected $fillable = ['tenant_id', 'titel', 'beschreibung', 'standort', 'asset_id', 'prioritaet', 'status', 'gemeldet_von', 'erledigt_am', 'erledigt_notiz'];

    protected $casts = [
        'prioritaet' => MeldungPrioritaet::class,
        'status' => MeldungStatus::class,
        'erledigt_am' => 'date',
    ];

    public function melder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gemeldet_von');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FacilityAsset::class, 'asset_id');
    }
}
