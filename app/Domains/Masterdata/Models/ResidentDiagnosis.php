<?php

namespace App\Domains\Masterdata\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property int $icd_code_id
 * @property string $art
 * @property Carbon|null $diagnostiziert_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read IcdCode $icdCode
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDiagnosis newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDiagnosis newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDiagnosis query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDiagnosis whereArt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDiagnosis whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDiagnosis whereDiagnostiziertAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDiagnosis whereIcdCodeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDiagnosis whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDiagnosis whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDiagnosis whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDiagnosis whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ResidentDiagnosis extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'icd_code_id', 'art', 'diagnostiziert_am'];

    protected $casts = ['diagnostiziert_am' => 'date'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function icdCode(): BelongsTo
    {
        return $this->belongsTo(IcdCode::class);
    }
}
