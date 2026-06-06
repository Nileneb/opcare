<?php

namespace App\Domains\Assessment\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $instrument_id
 * @property string $label
 * @property string|null $hilfetext
 * @property int $reihenfolge
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $loinc
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Instrument $instrument
 * @property-read Collection<int, AssessmentOption> $options
 * @property-read int|null $options_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstrumentItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstrumentItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstrumentItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstrumentItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstrumentItem whereHilfetext($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstrumentItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstrumentItem whereInstrumentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstrumentItem whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstrumentItem whereLoinc($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstrumentItem whereReihenfolge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstrumentItem whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|InstrumentItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class InstrumentItem extends BaseModel
{
    protected $fillable = ['tenant_id', 'instrument_id', 'label', 'loinc', 'hilfetext', 'reihenfolge'];

    protected $casts = ['reihenfolge' => 'integer'];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    /** @return HasMany<AssessmentOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(AssessmentOption::class)->orderBy('reihenfolge');
    }
}
