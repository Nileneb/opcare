<?php

namespace App\Domains\SocialCare\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\SocialCare\Enums\BetreuungsArt;
use App\Domains\SocialCare\Enums\BetreuungsTyp;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Ein Betreuungs-/Aktivierungsangebot der zusätzlichen Betreuung (§ 43b SGB XI). Tenant-scoped über BaseModel.
 *
 * @property int $id
 * @property int $tenant_id
 * @property Carbon $datum
 * @property string|null $beginn
 * @property int $dauer_minuten
 * @property BetreuungsArt $art
 * @property BetreuungsTyp $typ
 * @property string $titel
 * @property string|null $ort
 * @property int|null $leitung_id
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read User|null $leitung
 * @property-read Collection<int, BetreuungsTeilnahme> $teilnahmen
 * @property-read int|null $teilnahmen_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot whereArt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot whereBeginn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot whereDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot whereDauerMinuten($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot whereLeitungId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot whereOrt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot whereTitel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot whereTyp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Betreuungsangebot whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Betreuungsangebot extends BaseModel
{
    protected $table = 'betreuungsangebote';

    protected $fillable = ['tenant_id', 'datum', 'beginn', 'dauer_minuten', 'art', 'typ', 'titel', 'ort', 'leitung_id', 'notiz'];

    protected $casts = [
        'datum' => 'date',
        'dauer_minuten' => 'integer',
        'art' => BetreuungsArt::class,
        'typ' => BetreuungsTyp::class,
    ];

    public function leitung(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leitung_id');
    }

    public function teilnahmen(): HasMany
    {
        return $this->hasMany(BetreuungsTeilnahme::class);
    }
}
