<?php

namespace App\Domains\Catering\Models;

use App\Domains\Catering\Enums\ReinigungsIntervall;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Eine Plan-Position im Reinigungs- und Desinfektionsplan der Küche.
 * Norm-Anker: VO (EG) 852/2004 Anhang II, LMHV §§ 3/4.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $bezeichnung
 * @property string|null $bereich
 * @property ReinigungsIntervall $intervall
 * @property string|null $verantwortlich
 * @property bool $aktiv
 * @property Carbon|null $letzte_erledigung_am
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Collection<int, Reinigungsnachweis> $nachweise
 * @property-read int|null $nachweise_count
 *
 * @mixin \Eloquent
 */
class Reinigungsaufgabe extends BaseModel
{
    protected $table = 'reinigungsaufgaben';

    protected $fillable = [
        'tenant_id', 'bezeichnung', 'bereich', 'intervall',
        'verantwortlich', 'aktiv', 'letzte_erledigung_am',
    ];

    protected $casts = [
        'intervall' => ReinigungsIntervall::class,
        'aktiv' => 'boolean',
        'letzte_erledigung_am' => 'date',
    ];

    /** @return HasMany<Reinigungsnachweis, $this> */
    public function nachweise(): HasMany
    {
        return $this->hasMany(Reinigungsnachweis::class);
    }

    /** Nächste Fälligkeit: letzte Erledigung + Intervall (null = nie erledigt). */
    public function naechsteFaelligkeit(): ?Carbon
    {
        if ($this->letzte_erledigung_am === null) {
            return null;
        }

        return $this->letzte_erledigung_am->copy()->addDays($this->intervall->tage());
    }

    /** Überfällig wenn aktiv UND (nie erledigt ODER Fälligkeit < heute). */
    public function istUeberfaellig(): bool
    {
        if (! $this->aktiv) {
            return false;
        }

        $naechste = $this->naechsteFaelligkeit();

        return $naechste === null || $naechste->lt(today());
    }

    /**
     * Frist-Ampel: 'rot' (überfällig/nie erledigt), 'gelb' (bald fällig), 'gruen' (sonst).
     * Gelb-Schwelle: 3 Tage absolut (verhindert dauerhaft-gelb bei wöchentlichem Intervall,
     * bleibt sinnvoll bei täglich). Inaktive Aufgaben sind immer 'gruen'.
     */
    public function faelligkeitsStatus(): string
    {
        if (! $this->aktiv) {
            return 'gruen';
        }

        $naechste = $this->naechsteFaelligkeit();

        if ($naechste === null || $naechste->lt(today())) {
            return 'rot';
        }

        // WHY: 3-Tage-Schwelle — bei wöchentlichem Intervall wäre min(7, 7)=7 dauerhaft gelb;
        // 3 Tage absolute Vorwarnung ist bei allen Intervallen praktisch und nicht lähmend.
        if ($naechste->lte(today()->addDays(3))) {
            return 'gelb';
        }

        return 'gruen';
    }
}
