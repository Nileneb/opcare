<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Enums\InventurStatus;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Inventur-Kampagne (§§ 240/241 HGB i. V. m. PBV): zu einem Stichtag, optional je Abteilung. Bei Anlage werden
 * die Soll-Mengen je Artikel gesnapshottet; beim Abschluss werden die Zähldifferenzen gebucht und der
 * Bestandswert eingefroren ({@see bestandswert_summe}).
 *
 * @property int $id
 * @property int $tenant_id
 * @property Abteilung|null $abteilung
 * @property Carbon $stichtag
 * @property InventurStatus $status
 * @property numeric|null $bestandswert_summe
 * @property int|null $differenz_buchung_id
 * @property int|null $erstellt_von
 * @property int|null $abgeschlossen_von
 * @property Carbon|null $abgeschlossen_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, Inventurposition> $positionen
 * @property-read int|null $positionen_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur whereAbgeschlossenAm($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur whereAbgeschlossenVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur whereAbteilung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur whereBestandswertSumme($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur whereDifferenzBuchungId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur whereErstelltVon($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur whereStichtag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inventur whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Inventur extends BaseModel
{
    protected $table = 'inventuren';

    protected $fillable = ['tenant_id', 'abteilung', 'stichtag', 'status', 'bestandswert_summe',
        'differenz_buchung_id', 'erstellt_von', 'abgeschlossen_von', 'abgeschlossen_am'];

    protected $casts = [
        'abteilung' => Abteilung::class,
        'stichtag' => 'date',
        'status' => InventurStatus::class,
        'bestandswert_summe' => 'decimal:2',
        'abgeschlossen_am' => 'datetime',
    ];

    /** @return HasMany<Inventurposition, $this> */
    public function positionen(): HasMany
    {
        return $this->hasMany(Inventurposition::class);
    }

    public function offen(): bool
    {
        return $this->status === InventurStatus::Offen;
    }
}
