<?php

namespace App\Domains\Quality\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Laufender Protokolleintrag zu einer FEM (Überwachung/Vitalzeichen/Indikationsprüfung/Beendigung).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $fem_fall_id
 * @property Carbon $zeitpunkt
 * @property string $typ
 * @property string|null $befund
 * @property bool|null $indikation_gegeben
 * @property int|null $dokumentiert_von
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read User|null $dokumentierer
 * @property-read FemFall $fall
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemProtokoll newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemProtokoll newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemProtokoll query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemProtokoll whereBefund($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemProtokoll whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemProtokoll whereDokumentiertVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemProtokoll whereFemFallId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemProtokoll whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemProtokoll whereIndikationGegeben($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemProtokoll whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemProtokoll whereTyp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemProtokoll whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FemProtokoll whereZeitpunkt($value)
 *
 * @mixin \Eloquent
 */
class FemProtokoll extends BaseModel
{
    protected $table = 'fem_protokolle';

    protected $fillable = ['tenant_id', 'fem_fall_id', 'zeitpunkt', 'typ', 'befund', 'indikation_gegeben', 'dokumentiert_von'];

    protected $casts = ['zeitpunkt' => 'datetime', 'indikation_gegeben' => 'boolean'];

    public function fall(): BelongsTo
    {
        return $this->belongsTo(FemFall::class, 'fem_fall_id');
    }

    public function dokumentierer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dokumentiert_von');
    }
}
