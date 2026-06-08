<?php

namespace App\Domains\Masterdata\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Zurückliegender Krankenhausaufenthalt (ÜLB-Sektion krankenhausaufenthalt → Encounter_Hospital_Stay).
 * Das Profil erfasst ausschließlich das Ende des stationären Aufenthalts (period.start ist verboten,
 * period.end Pflicht); `grund` ist ein interner Vermerk (FHIR reasonCode im Profil verboten).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property Carbon $ende
 * @property string|null $grund
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @mixin \Eloquent
 */
class ResidentHospitalStay extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'ende', 'grund'];

    protected $casts = ['ende' => 'date'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
