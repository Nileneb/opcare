<?php

namespace App\Domains\Medication\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Enums\VitalType;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property int|null $administration_id
 * @property VitalType $typ
 * @property numeric $wert
 * @property numeric|null $wert2
 * @property string $einheit
 * @property Carbon $gemessen_am
 * @property int|null $gemessen_von
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading whereAdministrationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading whereEinheit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading whereGemessenAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading whereGemessenVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading whereTyp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading whereWert($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|VitalReading whereWert2($value)
 *
 * @mixin \Eloquent
 */
class VitalReading extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'resident_id', 'administration_id', 'typ', 'wert', 'wert2',
        'einheit', 'gemessen_am', 'gemessen_von', 'notiz',
    ];

    protected $casts = [
        'typ' => VitalType::class,
        'wert' => 'decimal:2',
        'wert2' => 'decimal:2',
        'gemessen_am' => 'datetime',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
