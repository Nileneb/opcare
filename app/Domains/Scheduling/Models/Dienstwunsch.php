<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Scheduling\Enums\WunschTyp;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Dienstwunsch einer/eines Mitarbeitenden (Vorschlagscharakter, nicht bindend). Die PDL sieht die Wünsche
 * direkt im Dienstplan-Grid bei der Planung. Tenant-scoped über BaseModel.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property Carbon $datum
 * @property WunschTyp $typ
 * @property string|null $schicht_kind
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dienstwunsch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dienstwunsch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dienstwunsch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dienstwunsch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dienstwunsch whereDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dienstwunsch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dienstwunsch whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dienstwunsch whereSchichtKind($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dienstwunsch whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dienstwunsch whereTyp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dienstwunsch whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Dienstwunsch whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Dienstwunsch extends BaseModel
{
    protected $table = 'dienstwuensche';

    protected $fillable = ['tenant_id', 'user_id', 'datum', 'typ', 'schicht_kind', 'notiz'];

    protected $casts = ['datum' => 'date', 'typ' => WunschTyp::class];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
