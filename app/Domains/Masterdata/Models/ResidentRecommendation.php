<?php

namespace App\Domains\Masterdata\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Empfehlung an die aufnehmende Einrichtung (ÜLB-Sektion empfehlung →
 * CarePlan_Recommendation_Receiving_Institution). Der Empfehlungstext steht in
 * CarePlan.activity.detail.code.text; ein generisches SNOMED-Coding kennzeichnet die Aktivität.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property string $empfehlung
 * @property Carbon|null $erstellt_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @mixin \Eloquent
 */
class ResidentRecommendation extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'empfehlung', 'erstellt_am'];

    protected $casts = ['erstellt_am' => 'date'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
