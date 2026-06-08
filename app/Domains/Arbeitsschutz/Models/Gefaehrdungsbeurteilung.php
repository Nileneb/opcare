<?php

namespace App\Domains\Arbeitsschutz\Models;

use App\Domains\Arbeitsschutz\Enums\GbuStatus;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Gefährdungsbeurteilung eines Arbeitsbereichs / einer Tätigkeit.
 * Norm-Anker: § 5 ArbSchG (Beurteilung), § 6 ArbSchG (Dokumentation), § 3 Abs. 1 ArbSchG (Fortschreibung).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $arbeitsbereich
 * @property string|null $taetigkeit
 * @property string|null $beschreibung
 * @property Carbon $erstellt_am
 * @property int $ueberpruefungsintervall_monate
 * @property Carbon|null $letzte_ueberpruefung_am
 * @property string|null $verantwortlich
 * @property Carbon|null $freigegeben_am
 * @property GbuStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Collection<int, Gefaehrdung> $gefaehrdungen
 * @property-read int|null $gefaehrdungen_count
 *
 * @mixin \Eloquent
 */
class Gefaehrdungsbeurteilung extends BaseModel
{
    protected $table = 'gefaehrdungsbeurteilungen';

    protected $fillable = [
        'tenant_id', 'arbeitsbereich', 'taetigkeit', 'beschreibung',
        'erstellt_am', 'ueberpruefungsintervall_monate', 'letzte_ueberpruefung_am',
        'verantwortlich', 'freigegeben_am', 'status',
    ];

    protected $casts = [
        'erstellt_am' => 'date',
        'letzte_ueberpruefung_am' => 'date',
        'freigegeben_am' => 'date',
        'status' => GbuStatus::class,
        'ueberpruefungsintervall_monate' => 'integer',
    ];

    /** @return HasMany<Gefaehrdung, $this> */
    public function gefaehrdungen(): HasMany
    {
        return $this->hasMany(Gefaehrdung::class);
    }

    /**
     * Nächste Überprüfungs-Frist: (letzte_ueberpruefung_am ?? erstellt_am) + Intervall.
     * Frist nur relevant wenn status === Freigegeben — sonst null zurückgeben.
     */
    public function naechsteUeberpruefung(): ?Carbon
    {
        if ($this->status !== GbuStatus::Freigegeben) {
            return null;
        }

        $basis = $this->letzte_ueberpruefung_am ?? $this->erstellt_am;

        return $basis->copy()->addMonths($this->ueberpruefungsintervall_monate);
    }

    public function istUeberfaellig(): bool
    {
        $naechste = $this->naechsteUeberpruefung();

        return $naechste !== null && $naechste->lt(today());
    }

    /**
     * Frist-Ampel: 'rot' (überfällig) / 'gelb' (≤30 Tage) / 'gruen' (sonst).
     * WHY: Nur scharf wenn status === Freigegeben — Entwürfe/Überarbeitung haben keine Fortschreibungs-Uhr.
     */
    public function faelligkeitsStatus(): string
    {
        if ($this->status !== GbuStatus::Freigegeben) {
            return 'gruen';
        }

        $naechste = $this->naechsteUeberpruefung();

        if ($naechste === null || $naechste->lt(today())) {
            return 'rot';
        }

        if ($naechste->lte(today()->addDays(30))) {
            return 'gelb';
        }

        return 'gruen';
    }

    /**
     * Alle offenen Schutzmaßnahmen über ALLE Gefährdungen dieser GBU (umgesetzt_am IS NULL).
     * SSOT — hatOffeneMassnahmen() delegiert hierher; keine zweite Query.
     *
     * @return SupportCollection<int, Schutzmassnahme>
     */
    public function offeneMassnahmen(): SupportCollection
    {
        return $this->gefaehrdungen
            ->flatMap(fn (Gefaehrdung $g) => $g->massnahmen)
            ->filter(fn (Schutzmassnahme $m) => $m->istOffen())
            ->values();
    }

    public function hatOffeneMassnahmen(): bool
    {
        return $this->offeneMassnahmen()->isNotEmpty();
    }

    /**
     * Höchste Risikostufe über alle Gefährdungen (gering < mittel < hoch).
     * Null wenn keine Gefährdungen vorhanden.
     */
    public function hoechsteRisikostufe(): ?string
    {
        if ($this->gefaehrdungen->isEmpty()) {
            return null;
        }

        $rang = ['gering' => 1, 'mittel' => 2, 'hoch' => 3];

        return $this->gefaehrdungen
            ->map(fn (Gefaehrdung $g) => $g->risikostufe())
            ->sortByDesc(fn (string $stufe) => $rang[$stufe] ?? 0)
            ->first();
    }
}
