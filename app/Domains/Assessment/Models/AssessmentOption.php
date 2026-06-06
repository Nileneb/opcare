<?php

namespace App\Domains\Assessment\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $instrument_item_id
 * @property string $label
 * @property int $punkte
 * @property int $reihenfolge
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read InstrumentItem $item
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentOption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentOption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentOption query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentOption whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentOption whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentOption whereInstrumentItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentOption whereLabel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentOption wherePunkte($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentOption whereReihenfolge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentOption whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AssessmentOption whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class AssessmentOption extends BaseModel
{
    protected $fillable = ['tenant_id', 'instrument_item_id', 'label', 'punkte', 'reihenfolge'];

    protected $casts = ['punkte' => 'integer', 'reihenfolge' => 'integer'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(InstrumentItem::class, 'instrument_item_id');
    }
}
