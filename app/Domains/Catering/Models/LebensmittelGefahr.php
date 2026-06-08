<?php

namespace App\Domains\Catering\Models;

use App\Domains\Catering\Enums\Gefahrenart;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Eine in einem Prozessschritt identifizierte Lebensmittelgefahr (HACCP-Prinzip 1) inkl.
 * Risikobewertung (Prinzip 2: CCP-Entscheidung) und Verknüpfung zum Überwachungs-Messpunkt.
 * Norm-Anker: Codex Alimentarius CAC/RCP 1-1969, VO (EG) 852/2004 Art. 5.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $gefahrenanalyse_id
 * @property Gefahrenart $gefahrenart
 * @property string $beschreibung
 * @property int $wahrscheinlichkeit
 * @property int $schwere
 * @property bool $ist_ccp
 * @property int|null $haccp_messpunkt_id
 * @property string|null $ccp_begruendung
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Gefahrenanalyse $gefahrenanalyse
 * @property-read HaccpMesspunkt|null $messpunkt
 * @property-read Collection<int, Lenkungsmassnahme> $lenkungsmassnahmen
 * @property-read int|null $lenkungsmassnahmen_count
 *
 * @mixin \Eloquent
 */
class LebensmittelGefahr extends BaseModel
{
    protected $table = 'lebensmittel_gefahren';

    protected $fillable = [
        'tenant_id', 'gefahrenanalyse_id', 'gefahrenart', 'beschreibung',
        'wahrscheinlichkeit', 'schwere', 'ist_ccp', 'haccp_messpunkt_id', 'ccp_begruendung',
    ];

    protected $casts = [
        'gefahrenart' => Gefahrenart::class,
        'wahrscheinlichkeit' => 'integer',
        'schwere' => 'integer',
        'ist_ccp' => 'boolean',
    ];

    /** @return BelongsTo<Gefahrenanalyse, $this> */
    public function gefahrenanalyse(): BelongsTo
    {
        return $this->belongsTo(Gefahrenanalyse::class);
    }

    /** @return BelongsTo<HaccpMesspunkt, $this> */
    public function messpunkt(): BelongsTo
    {
        return $this->belongsTo(HaccpMesspunkt::class, 'haccp_messpunkt_id');
    }

    /** @return HasMany<Lenkungsmassnahme, $this> */
    public function lenkungsmassnahmen(): HasMany
    {
        return $this->hasMany(Lenkungsmassnahme::class);
    }

    /** Risikowert nach Nohl-light: Wahrscheinlichkeit × Schwere (Bereich 1–9). */
    public function risikowert(): int
    {
        return $this->wahrscheinlichkeit * $this->schwere;
    }

    /**
     * Risikostufe: 'gering' (≤2), 'mittel' (3–4), 'hoch' (≥6).
     * WHY: Produkte 1–3×1–3 = {1,2,3,4,6,9}; Wert 5 tritt nie auf → keine Lücke.
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

    /** Signifikante Gefahr (Risiko mittel/hoch) — erfordert eine dokumentierte Lenkungsmaßnahme. */
    public function signifikant(): bool
    {
        return $this->risikostufe() !== 'gering';
    }

    public function hatLenkung(): bool
    {
        return $this->lenkungsmassnahmen->isNotEmpty();
    }

    /**
     * Als CCP eingestuft, aber kein Überwachungs-Messpunkt verknüpft (HACCP-Prinzip 4 nicht erfüllt).
     * WHY: Prüfung über die FK-Spalte (haccp_messpunkt_id), nicht die Relation — Larastan typt belongsTo
     * non-null, ein nullsafe vor ?? würde fälschlich als nie-null gemeldet.
     */
    public function istCcpOhneUeberwachung(): bool
    {
        return $this->ist_ccp && $this->haccp_messpunkt_id === null;
    }
}
