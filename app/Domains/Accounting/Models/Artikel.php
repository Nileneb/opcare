<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Lagerartikel der Warenwirtschaft, einer Abteilung zugeordnet. Tenant-scoped über BaseModel.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $einheit
 * @property Abteilung $abteilung
 * @property numeric $bestand
 * @property numeric|null $mindestbestand
 * @property numeric|null $einkaufspreis
 * @property bool $aktiv
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $pflegehilfsmittel
 * @property string|null $pg_nummer
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read int|null $activities_as_subject_count
 * @property-read Collection<int, Lagerbewegung> $bewegungen
 * @property-read int|null $bewegungen_count
 * @property-read Collection<int, Lagerschicht> $schichten
 * @property-read int|null $schichten_count
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel whereAbteilung($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel whereAktiv($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel whereBestand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel whereEinheit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel whereEinkaufspreis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel whereMindestbestand($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel wherePflegehilfsmittel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel wherePgNummer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artikel whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Artikel extends BaseModel
{
    protected $table = 'artikel';

    protected $fillable = ['tenant_id', 'name', 'einheit', 'abteilung', 'bestand', 'mindestbestand', 'einkaufspreis', 'aktiv', 'pflegehilfsmittel', 'pg_nummer'];

    protected $casts = [
        'abteilung' => Abteilung::class,
        'bestand' => 'decimal:2',
        'mindestbestand' => 'decimal:2',
        'einkaufspreis' => 'decimal:2',
        'aktiv' => 'boolean',
        'pflegehilfsmittel' => 'boolean',
    ];

    /**
     * @return HasMany<Lagerbewegung, $this>
     */
    public function bewegungen(): HasMany
    {
        return $this->hasMany(Lagerbewegung::class);
    }

    /**
     * @return HasMany<Lagerschicht, $this>
     */
    public function schichten(): HasMany
    {
        return $this->hasMany(Lagerschicht::class);
    }

    public function unterbestand(): bool
    {
        return $this->mindestbestand !== null && (float) $this->bestand < (float) $this->mindestbestand;
    }
}
