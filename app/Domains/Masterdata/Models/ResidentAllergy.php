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
 * @property string $substanz
 * @property string $typ
 * @property string|null $kategorie
 * @property string|null $kritikalitaet
 * @property string|null $reaktion
 * @property Carbon|null $erfasst_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentAllergy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentAllergy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentAllergy query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentAllergy whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentAllergy whereErfasstAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentAllergy whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentAllergy whereKategorie($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentAllergy whereKritikalitaet($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentAllergy whereReaktion($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentAllergy whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentAllergy whereSubstanz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentAllergy whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentAllergy whereTyp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentAllergy whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ResidentAllergy extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'substanz', 'typ', 'kategorie', 'kritikalitaet', 'reaktion', 'erfasst_am'];

    protected $casts = ['erfasst_am' => 'date'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
