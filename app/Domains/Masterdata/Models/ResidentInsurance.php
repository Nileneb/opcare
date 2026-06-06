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
 * @property int $health_insurance_id
 * @property string|null $versichertennr
 * @property bool $ist_primaer
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read HealthInsurance $healthInsurance
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentInsurance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentInsurance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentInsurance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentInsurance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentInsurance whereHealthInsuranceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentInsurance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentInsurance whereIstPrimaer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentInsurance whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentInsurance whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentInsurance whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentInsurance whereVersichertennr($value)
 *
 * @mixin \Eloquent
 */
class ResidentInsurance extends BaseModel
{
    protected $table = 'resident_insurance';

    protected $fillable = ['tenant_id', 'resident_id', 'health_insurance_id', 'versichertennr', 'ist_primaer'];

    protected $casts = ['ist_primaer' => 'boolean'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function healthInsurance(): BelongsTo
    {
        return $this->belongsTo(HealthInsurance::class);
    }
}
