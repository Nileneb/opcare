<?php

namespace App\Domains\CarePlanning\Models;

use App\Domains\CarePlanning\Enums\RiskType;
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
 * @property RiskType $risiko
 * @property bool $eingeschaetzt
 * @property string|null $begruendung
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read SisAssessment $sisAssessment
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskItem whereBegruendung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskItem whereEingeschaetzt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskItem whereRisiko($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskItem whereSisAssessmentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskItem whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|RiskItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class RiskItem extends BaseModel
{
    protected $fillable = ['tenant_id', 'sis_assessment_id', 'risiko', 'eingeschaetzt', 'begruendung'];

    // WHY(Track B, At-Rest): Risiko-Begründung ist sensibler Gesundheits-Freitext → verschlüsselt.
    protected $casts = ['risiko' => RiskType::class, 'eingeschaetzt' => 'boolean', 'begruendung' => 'encrypted'];

    public function sisAssessment(): BelongsTo
    {
        return $this->belongsTo(SisAssessment::class);
    }
}
