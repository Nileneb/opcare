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
 * @property string|null $beziehung
 * @property string|null $telefon
 * @property bool $benachrichtigen
 * @property string|null $hinweis
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentContact newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentContact newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentContact query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentContact whereBenachrichtigen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentContact whereBeziehung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentContact whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentContact whereHinweis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentContact whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentContact whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentContact whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentContact whereTelefon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentContact whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ResidentContact whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class ResidentContact extends BaseModel
{
    protected $fillable = ['tenant_id', 'resident_id', 'name', 'beziehung', 'telefon', 'benachrichtigen', 'hinweis'];

    protected $casts = ['benachrichtigen' => 'boolean'];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
