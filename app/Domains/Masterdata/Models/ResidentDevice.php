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
 * @property string $bezeichnung
 * @property string|null $kategorie
 * @property string|null $hinweis
 * @property Carbon|null $seit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDevice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDevice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDevice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDevice whereBezeichnung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDevice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDevice whereHinweis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDevice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDevice whereKategorie($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDevice whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDevice whereSeit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDevice whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentDevice whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ResidentDevice extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'bezeichnung', 'kategorie', 'hinweis', 'seit'];

    protected $casts = ['seit' => 'date'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
