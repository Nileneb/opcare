<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Ein Buchungssatz „Soll an Haben" — der Betrag wird dem Soll-Konto belastet und dem Haben-Konto
 * gutgeschrieben. Tenant-scoped über BaseModel.
 *
 * @property int $id
 * @property int $tenant_id
 * @property Carbon $datum
 * @property int $soll_konto_id
 * @property int $haben_konto_id
 * @property numeric $betrag
 * @property string $text
 * @property string|null $beleg
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Konto $habenKonto
 * @property-read Konto $sollKonto
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Buchung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Buchung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Buchung query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Buchung whereBeleg($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Buchung whereBetrag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Buchung whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Buchung whereDatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Buchung whereHabenKontoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Buchung whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Buchung whereSollKontoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Buchung whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Buchung whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Buchung whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Buchung extends BaseModel
{
    protected $table = 'buchungen';

    protected $fillable = ['tenant_id', 'datum', 'soll_konto_id', 'haben_konto_id', 'betrag', 'text', 'beleg'];

    protected $casts = ['datum' => 'date', 'betrag' => 'decimal:2'];

    public function sollKonto(): BelongsTo
    {
        return $this->belongsTo(Konto::class, 'soll_konto_id');
    }

    public function habenKonto(): BelongsTo
    {
        return $this->belongsTo(Konto::class, 'haben_konto_id');
    }
}
