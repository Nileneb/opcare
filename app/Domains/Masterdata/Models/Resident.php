<?php

namespace App\Domains\Masterdata\Models;

use App\Domains\CarePlanning\Models\CareMeasure;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Database\Factories\ResidentFactory;
use App\Domains\Quality\Models\CareEvent;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $room_id
 * @property string $name
 * @property Carbon $geburtsdatum
 * @property string $geschlecht
 * @property int|null $pflegegrad
 * @property Carbon $aufnahme_am
 * @property Carbon|null $entlassung_am
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, ResidentAllergy> $allergies
 * @property-read int|null $allergies_count
 * @property-read Collection<int, CareEvent> $careEvents
 * @property-read int|null $care_events_count
 * @property-read Collection<int, CareMeasure> $careMeasures
 * @property-read int|null $care_measures_count
 * @property-read Collection<int, ResidentContact> $contacts
 * @property-read int|null $contacts_count
 * @property-read Collection<int, Custodian> $custodians
 * @property-read int|null $custodians_count
 * @property-read Collection<int, ResidentDevice> $devices
 * @property-read int|null $devices_count
 * @property-read Collection<int, ResidentDiagnosis> $diagnoses
 * @property-read int|null $diagnoses_count
 * @property-read Collection<int, ResidentInsurance> $insurances
 * @property-read int|null $insurances_count
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, Physician> $physicians
 * @property-read int|null $physicians_count
 * @property-read Room|null $room
 * @property-read Collection<int, SisAssessment> $sisAssessments
 * @property-read int|null $sis_assessments_count
 * @property-read Collection<int, ResidentStatusObservation> $statusObservations
 * @property-read int|null $status_observations_count
 * @property-read Tenant $tenant
 *
 * @method static \App\Domains\Masterdata\Database\Factories\ResidentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident whereAufnahmeAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident whereEntlassungAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident whereGeburtsdatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident whereGeschlecht($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident wherePflegegrad($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident whereRoomId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Resident whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Resident extends BaseModel implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'tenant_id', 'room_id', 'name', 'geburtsdatum', 'geschlecht',
        'pflegegrad', 'aufnahme_am', 'entlassung_am', 'status',
    ];

    protected $casts = [
        'geburtsdatum' => 'date',
        'aufnahme_am' => 'date',
        'entlassung_am' => 'date',
        'pflegegrad' => 'integer',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(ResidentDiagnosis::class);
    }

    public function allergies(): HasMany
    {
        return $this->hasMany(ResidentAllergy::class);
    }

    public function statusObservations(): HasMany
    {
        return $this->hasMany(ResidentStatusObservation::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(ResidentDevice::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ResidentContact::class);
    }

    public function insurances(): HasMany
    {
        return $this->hasMany(ResidentInsurance::class);
    }

    public function custodians(): HasMany
    {
        return $this->hasMany(Custodian::class);
    }

    public function sisAssessments(): HasMany
    {
        return $this->hasMany(SisAssessment::class);
    }

    /** @return HasMany<CareMeasure, $this> */
    public function careMeasures(): HasMany
    {
        return $this->hasMany(CareMeasure::class);
    }

    /** @return HasMany<CareEvent, $this> */
    public function careEvents(): HasMany
    {
        return $this->hasMany(CareEvent::class)->latest('datum');
    }

    /** @return BelongsToMany<Physician, $this> */
    public function physicians(): BelongsToMany
    {
        return $this->belongsToMany(Physician::class, 'resident_physician');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')->useDisk('media');
    }

    protected static function newFactory(): ResidentFactory
    {
        return ResidentFactory::new();
    }
}
