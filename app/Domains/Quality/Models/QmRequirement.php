<?php

namespace App\Domains\Quality\Models;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Quality\Enums\QmBereich;
use App\Domains\Quality\Enums\QmStatus;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Eine QM-Anforderung (aus Norm abgeleitet oder einrichtungseigen) mit editierbarem Bearbeitungsstand.
 *
 * Tenant-scoped über BaseModel.
 *
 * @property int $id
 * @property int $tenant_id
 * @property QmBereich $bereich
 * @property string $norm
 * @property string $anforderung
 * @property string|null $gesetz_url
 * @property QmStatus $status
 * @property string|null $nachweis
 * @property string|null $zustaendig
 * @property Carbon|null $faellig_am
 * @property Carbon|null $geprueft_am
 * @property string|null $schluessel
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement whereAnforderung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement whereBereich($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement whereFaelligAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement whereGeprueftAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement whereGesetzUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement whereNachweis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement whereNorm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement whereSchluessel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|QmRequirement whereZustaendig($value)
 *
 * @mixin \Eloquent
 */
class QmRequirement extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'bereich', 'norm', 'anforderung', 'gesetz_url',
        'status', 'nachweis', 'zustaendig', 'faellig_am', 'geprueft_am', 'schluessel',
    ];

    protected $casts = [
        'bereich' => QmBereich::class,
        'status' => QmStatus::class,
        'faellig_am' => 'date',
        'geprueft_am' => 'date',
    ];
}
