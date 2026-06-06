<?php

namespace App\Domains\Masterdata\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $fachrichtung
 * @property string|null $kontakt
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $lanr
 * @property string|null $bsnr
 * @property string|null $strasse
 * @property string|null $hausnummer
 * @property string|null $plz
 * @property string|null $ort
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, Resident> $residents
 * @property-read int|null $residents_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician whereBsnr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician whereFachrichtung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician whereHausnummer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician whereKontakt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician whereLanr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician whereOrt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician wherePlz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician whereStrasse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Physician whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Physician extends BaseModel
{
    protected $fillable = ['tenant_id', 'name', 'fachrichtung', 'lanr', 'bsnr', 'kontakt', 'strasse', 'hausnummer', 'plz', 'ort'];

    public function residents(): BelongsToMany
    {
        return $this->belongsToMany(Resident::class, 'resident_physician');
    }
}
