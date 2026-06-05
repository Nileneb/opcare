<?php

namespace App\Domains\Masterdata\Models;

use App\Domains\CarePlanning\Models\CareMeasure;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Database\Factories\ResidentFactory;
use App\Domains\Quality\Models\CareEvent;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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

    public function careMeasures(): HasMany
    {
        return $this->hasMany(CareMeasure::class);
    }

    public function careEvents(): HasMany
    {
        return $this->hasMany(CareEvent::class)->latest('datum');
    }

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
