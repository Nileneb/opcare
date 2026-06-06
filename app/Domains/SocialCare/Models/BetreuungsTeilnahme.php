<?php

namespace App\Domains\SocialCare\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Teilnahme eines Bewohners an einem Betreuungsangebot — der Nachweis der zusätzlichen Betreuung (§ 43b).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $betreuungsangebot_id
 * @property int $resident_id
 * @property bool $teilgenommen
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Betreuungsangebot $angebot
 * @property-read Resident $resident
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BetreuungsTeilnahme newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BetreuungsTeilnahme newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BetreuungsTeilnahme query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BetreuungsTeilnahme whereBetreuungsangebotId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BetreuungsTeilnahme whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BetreuungsTeilnahme whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BetreuungsTeilnahme whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BetreuungsTeilnahme whereResidentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BetreuungsTeilnahme whereTeilgenommen($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BetreuungsTeilnahme whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|BetreuungsTeilnahme whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class BetreuungsTeilnahme extends BaseModel
{
    protected $table = 'betreuungs_teilnahmen';

    protected $attributes = ['teilgenommen' => true];

    protected $fillable = ['tenant_id', 'betreuungsangebot_id', 'resident_id', 'teilgenommen', 'notiz'];

    protected $casts = ['teilgenommen' => 'boolean'];

    public function angebot(): BelongsTo
    {
        return $this->belongsTo(Betreuungsangebot::class, 'betreuungsangebot_id');
    }

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }
}
