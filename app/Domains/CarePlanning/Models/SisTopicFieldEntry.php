<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\SisTopicField;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $sis_assessment_id
 * @property SisTopicField $themenfeld
 * @property string|null $freitext
 * @property array<array-key, mixed>|null $strukturdaten
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read SisAssessment $sisAssessment
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisTopicFieldEntry newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisTopicFieldEntry newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisTopicFieldEntry query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisTopicFieldEntry whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisTopicFieldEntry whereFreitext($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisTopicFieldEntry whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisTopicFieldEntry whereSisAssessmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisTopicFieldEntry whereStrukturdaten($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisTopicFieldEntry whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisTopicFieldEntry whereThemenfeld($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SisTopicFieldEntry whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class SisTopicFieldEntry extends BaseModel
{
    protected $fillable = ['tenant_id', 'sis_assessment_id', 'themenfeld', 'freitext', 'strukturdaten'];

    protected $casts = [
        'themenfeld' => SisTopicField::class,
        // WHY(Track B, At-Rest): SIS-Narrativ (Freitext + strukturierte Themenfeld-Daten) verschlüsselt.
        'freitext' => 'encrypted',
        'strukturdaten' => 'encrypted:array',
    ];

    public function sisAssessment(): BelongsTo
    {
        return $this->belongsTo(SisAssessment::class);
    }
}
