<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Scheduling\Enums\AbwesenheitTyp;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Abwesenheit (Krankmeldung/Urlaub) eines Mitarbeiters über einen Zeitraum. Eine Krankmeldung öffnet die
 * betroffenen Dienste als Vertretungs-Anfrage in der Tauschbörse und macht die Person für den Auto-Generator
 * an diesen Tagen nicht verfügbar.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property AbwesenheitTyp $typ
 * @property Carbon $von
 * @property Carbon $bis
 * @property string|null $notiz
 * @property int|null $gemeldet_von
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abwesenheit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abwesenheit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abwesenheit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abwesenheit whereBis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abwesenheit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abwesenheit whereGemeldetVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abwesenheit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abwesenheit whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abwesenheit whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abwesenheit whereTyp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abwesenheit whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abwesenheit whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Abwesenheit whereVon($value)
 *
 * @mixin \Eloquent
 */
class Abwesenheit extends BaseModel
{
    protected $table = 'abwesenheiten';

    protected $fillable = ['tenant_id', 'user_id', 'typ', 'von', 'bis', 'notiz', 'gemeldet_von'];

    protected $casts = ['typ' => AbwesenheitTyp::class, 'von' => 'date', 'bis' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deckt(string $datum): bool
    {
        return $datum >= $this->von->toDateString() && $datum <= $this->bis->toDateString();
    }
}
