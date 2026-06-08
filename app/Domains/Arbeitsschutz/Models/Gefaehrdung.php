<?php

namespace App\Domains\Arbeitsschutz\Models;

use App\Domains\Arbeitsschutz\Enums\Gefaehrdungsfaktor;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Eine identifizierte Gefährdung innerhalb einer GBU.
 * Norm-Anker: § 5 Abs. 3 ArbSchG (6 Gefährdungsfaktoren), Nohl-Risikomatrix (3×3).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $gefaehrdungsbeurteilung_id
 * @property Gefaehrdungsfaktor $faktor
 * @property string $beschreibung
 * @property int $wahrscheinlichkeit
 * @property int $schwere
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Gefaehrdungsbeurteilung $gefaehrdungsbeurteilung
 * @property-read Collection<int, Schutzmassnahme> $massnahmen
 * @property-read int|null $massnahmen_count
 *
 * @mixin \Eloquent
 */
class Gefaehrdung extends BaseModel
{
    protected $table = 'gefaehrdungen';

    protected $fillable = [
        'tenant_id', 'gefaehrdungsbeurteilung_id', 'faktor',
        'beschreibung', 'wahrscheinlichkeit', 'schwere',
    ];

    protected $casts = [
        'faktor' => Gefaehrdungsfaktor::class,
        'wahrscheinlichkeit' => 'integer',
        'schwere' => 'integer',
    ];

    /** @return BelongsTo<Gefaehrdungsbeurteilung, $this> */
    public function gefaehrdungsbeurteilung(): BelongsTo
    {
        return $this->belongsTo(Gefaehrdungsbeurteilung::class);
    }

    /** @return HasMany<Schutzmassnahme, $this> */
    public function massnahmen(): HasMany
    {
        return $this->hasMany(Schutzmassnahme::class);
    }

    /** Risikowert nach Nohl-light: Wahrscheinlichkeit × Schwere (Bereich 1–9). */
    public function risikowert(): int
    {
        return $this->wahrscheinlichkeit * $this->schwere;
    }

    /**
     * Risikostufe: 'gering' (≤2), 'mittel' (3–4), 'hoch' (≥6).
     * WHY: Mögliche Produkte 1–3×1–3 = {1,2,3,4,6,9}; Wert 5 tritt nie auf → keine Lücke.
     */
    public function risikostufe(): string
    {
        $wert = $this->risikowert();

        if ($wert <= 2) {
            return 'gering';
        }

        if ($wert <= 4) {
            return 'mittel';
        }

        return 'hoch';
    }
}
