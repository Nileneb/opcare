<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Eine Position innerhalb einer Bestellung (ein Artikel, eine Menge).
 *
 * Teillieferungen sind möglich: menge_geliefert steigt mit jeder Lieferung.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $bestellung_id
 * @property int $artikel_id
 * @property numeric $menge_bestellt
 * @property numeric $menge_geliefert
 * @property numeric|null $einzelpreis
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Artikel $artikel
 * @property-read Bestellung $bestellung
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellposition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellposition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellposition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellposition whereArtikelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellposition whereBestellungId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellposition whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellposition whereEinzelpreis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellposition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellposition whereMengeBestellt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellposition whereMengeGeliefert($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellposition whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellposition whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Bestellposition extends BaseModel
{
    protected $table = 'bestellpositionen';

    protected $fillable = ['tenant_id', 'bestellung_id', 'artikel_id', 'menge_bestellt', 'menge_geliefert', 'einzelpreis'];

    protected $casts = [
        'menge_bestellt' => 'decimal:2',
        'menge_geliefert' => 'decimal:2',
        'einzelpreis' => 'decimal:2',
    ];

    /** @return BelongsTo<Bestellung, $this> */
    public function bestellung(): BelongsTo
    {
        return $this->belongsTo(Bestellung::class);
    }

    /** @return BelongsTo<Artikel, $this> */
    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class);
    }

    public function offen(): bool
    {
        return $this->restMenge() > 1e-9;
    }

    public function restMenge(): float
    {
        return (float) $this->menge_bestellt - (float) $this->menge_geliefert;
    }
}
