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
 * @property string $name
 * @property string|null $umfang
 * @property string|null $kontakt
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Custodian newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Custodian newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Custodian query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Custodian whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Custodian whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Custodian whereKontakt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Custodian whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Custodian whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Custodian whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Custodian whereUmfang($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Custodian whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Custodian extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'name', 'umfang', 'kontakt'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
