<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Identity\Concerns\BelongsToTenant;
use App\Domains\Identity\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * FIFO-Eingangsschicht (Lot) eines Artikels: jeder Wareneingang erzeugt genau eine Schicht mit ihrem
 * Einstandspreis. Der Verbrauch zehrt die ältesten Schichten zuerst ab (§ 256 HGB). Bewusst KEIN
 * Activity-Log (deshalb nicht `BaseModel`): `menge_rest` mutiert bei jedem Abgang — der Audit-Trail liegt
 * im unveränderlichen {@see Schichtabgang} + der {@see Lagerbewegung}. Felder `charge_nr`/`mhd` sind für
 * den späteren Chargen-/MHD-Ausbau (Art. 18 VO 178/2002) vorbereitet, aber hier noch ohne Workflow.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $artikel_id
 * @property int|null $eingang_bewegung_id
 * @property Carbon $eingangsdatum
 * @property numeric $menge_eingang
 * @property numeric $menge_rest
 * @property numeric $einstandspreis
 * @property string|null $charge_nr
 * @property Carbon|null $mhd
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $lieferant_id
 * @property int|null $bestellposition_id
 * @property-read Collection<int, Schichtabgang> $abgaenge
 * @property-read int|null $abgaenge_count
 * @property-read Artikel $artikel
 * @property-read Bestellposition|null $bestellposition
 * @property-read Lieferant|null $lieferant
 * @property-read Tenant $tenant
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht whereArtikelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht whereBestellpositionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht whereChargeNr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht whereEingangBewegungId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht whereEingangsdatum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht whereEinstandspreis($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht whereLieferantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht whereMengeEingang($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht whereMengeRest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht whereMhd($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht whereTenantId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Lagerschicht whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Lagerschicht extends Model
{
    use BelongsToTenant;

    protected $table = 'lagerschichten';

    protected $fillable = ['tenant_id', 'artikel_id', 'eingang_bewegung_id', 'eingangsdatum',
        'menge_eingang', 'menge_rest', 'einstandspreis', 'charge_nr', 'mhd', 'lieferant_id', 'bestellposition_id'];

    protected $casts = [
        'eingangsdatum' => 'date',
        'mhd' => 'date',
        'menge_eingang' => 'decimal:2',
        'menge_rest' => 'decimal:2',
        'einstandspreis' => 'decimal:4',
    ];

    public function artikel(): BelongsTo
    {
        return $this->belongsTo(Artikel::class);
    }

    public function lieferant(): BelongsTo
    {
        return $this->belongsTo(Lieferant::class);
    }

    public function bestellposition(): BelongsTo
    {
        return $this->belongsTo(Bestellposition::class);
    }

    /** @return HasMany<Schichtabgang, $this> */
    public function abgaenge(): HasMany
    {
        return $this->hasMany(Schichtabgang::class, 'schicht_id');
    }

    public function offen(): bool
    {
        return (float) $this->menge_rest > 0;
    }
}
