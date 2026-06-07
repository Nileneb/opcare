<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\BestellStatus;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Bestellung an einen Lieferanten. Positionen werden einzeln geliefert (Teillieferung möglich).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $lieferant_id
 * @property Carbon $bestelldatum
 * @property BestellStatus $status
 * @property int|null $erstellt_von
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read User|null $erstelltVon
 * @property-read Lieferant $lieferant
 * @property-read Collection<int, Bestellposition> $positionen
 * @property-read int|null $positionen_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellung newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellung newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellung query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellung whereBestelldatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellung whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellung whereErstelltVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellung whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellung whereLieferantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellung whereNotiz($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellung whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellung whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Bestellung whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Bestellung extends BaseModel
{
    protected $table = 'bestellungen';

    protected $fillable = ['tenant_id', 'lieferant_id', 'bestelldatum', 'status', 'erstellt_von', 'notiz'];

    protected $casts = [
        'status' => BestellStatus::class,
        'bestelldatum' => 'date',
    ];

    /** @return BelongsTo<Lieferant, $this> */
    public function lieferant(): BelongsTo
    {
        return $this->belongsTo(Lieferant::class);
    }

    /** @return HasMany<Bestellposition, $this> */
    public function positionen(): HasMany
    {
        return $this->hasMany(Bestellposition::class);
    }

    /** @return BelongsTo<User, $this> */
    public function erstelltVon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erstellt_von');
    }

    public function vollGeliefert(): bool
    {
        return $this->positionen->every(fn (Bestellposition $p) => ! $p->offen());
    }
}
