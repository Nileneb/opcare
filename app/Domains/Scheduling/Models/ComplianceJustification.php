<?php

namespace App\Domains\Scheduling\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Dokumentierte Begründung für einen bewusst in Kauf genommenen Arbeitszeit-Verstoß (§ 14 ArbZG —
 * außergewöhnlicher Fall, z. B. ausbleibende Nachfolgekraft). Identifiziert den Befund über
 * (user_id, rule_key, datum) und macht die Abweichung nachvollziehbar — sie bleibt ein Verstoß.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $rule_key
 * @property Carbon $datum
 * @property string $grund
 * @property int $begruendet_von
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read User $begruender
 * @property-read Tenant $tenant
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceJustification newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceJustification newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceJustification query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceJustification whereBegruendetVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceJustification whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceJustification whereDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceJustification whereGrund($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceJustification whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceJustification whereRuleKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceJustification whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceJustification whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ComplianceJustification whereUserId($value)
 *
 * @mixin \Eloquent
 */
class ComplianceJustification extends BaseModel
{
    protected $fillable = ['tenant_id', 'user_id', 'rule_key', 'datum', 'grund', 'begruendet_von'];

    protected $casts = ['datum' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function begruender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'begruendet_von');
    }

    /** Stabiler Schlüssel zum Abgleich mit einem ComplianceFinding. */
    public function matchKey(): string
    {
        return $this->rule_key.'|'.$this->user_id.'|'.Carbon::parse($this->datum)->toDateString();
    }
}
