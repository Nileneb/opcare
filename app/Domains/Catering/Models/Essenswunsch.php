<?php

namespace App\Domains\Catering\Models;

use App\Domains\Catering\Enums\EssenswunschArt;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Allgemeiner Essenswunsch eines Bewohners (Vorliebe/Abneigung/Hinweis) — die Küche sieht ihn jederzeit.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $resident_id
 * @property EssenswunschArt $art
 * @property string $text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Essenswunsch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Essenswunsch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Essenswunsch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Essenswunsch whereArt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Essenswunsch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Essenswunsch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Essenswunsch whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Essenswunsch whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Essenswunsch whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Essenswunsch whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Essenswunsch extends BaseModel
{
    protected $table = 'essenswuensche';

    protected $fillable = ['tenant_id', 'resident_id', 'art', 'text'];

    protected $casts = ['art' => EssenswunschArt::class];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
