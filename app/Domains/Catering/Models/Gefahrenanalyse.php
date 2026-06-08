<?php

namespace App\Domains\Catering\Models;

use App\Domains\Catering\Enums\GefahrenanalyseStatus;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * HACCP-Gefahrenanalyse eines Prozessschritts (HACCP-Prinzip 1).
 * Norm-Anker: Codex Alimentarius CAC/RCP 1-1969, VO (EG) 852/2004 Art. 5 (HACCP-System),
 * Art. 5 Abs. 2 lit. f (Überprüfung/Verifizierung des Systems).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $prozessschritt
 * @property string|null $bereich
 * @property string|null $beschreibung
 * @property Carbon $erstellt_am
 * @property int $verifizierungsintervall_monate
 * @property Carbon|null $letzte_verifizierung_am
 * @property string|null $verantwortlich
 * @property Carbon|null $freigegeben_am
 * @property GefahrenanalyseStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Collection<int, LebensmittelGefahr> $gefahren
 * @property-read int|null $gefahren_count
 *
 * @mixin \Eloquent
 */
class Gefahrenanalyse extends BaseModel
{
    protected $table = 'gefahrenanalysen';

    protected $fillable = [
        'tenant_id', 'prozessschritt', 'bereich', 'beschreibung',
        'erstellt_am', 'verifizierungsintervall_monate', 'letzte_verifizierung_am',
        'verantwortlich', 'freigegeben_am', 'status',
    ];

    protected $casts = [
        'erstellt_am' => 'date',
        'letzte_verifizierung_am' => 'date',
        'freigegeben_am' => 'date',
        'status' => GefahrenanalyseStatus::class,
        'verifizierungsintervall_monate' => 'integer',
    ];

    /** @return HasMany<LebensmittelGefahr, $this> */
    public function gefahren(): HasMany
    {
        return $this->hasMany(LebensmittelGefahr::class);
    }

    /**
     * Nächste Verifizierungs-Frist: (letzte_verifizierung_am ?? erstellt_am) + Intervall.
     * Nur relevant wenn status === Freigegeben — sonst null.
     */
    public function naechsteVerifizierung(): ?Carbon
    {
        if ($this->status !== GefahrenanalyseStatus::Freigegeben) {
            return null;
        }

        $basis = $this->letzte_verifizierung_am ?? $this->erstellt_am;

        return $basis->copy()->addMonths($this->verifizierungsintervall_monate);
    }

    public function istUeberfaellig(): bool
    {
        $naechste = $this->naechsteVerifizierung();

        return $naechste !== null && $naechste->lt(today());
    }

    /**
     * Frist-Ampel: 'rot' (überfällig) / 'gelb' (≤30 Tage) / 'gruen' (sonst).
     * WHY: Nur scharf wenn status === Freigegeben — Entwürfe haben keine Verifizierungs-Uhr.
     */
    public function faelligkeitsStatus(): string
    {
        if ($this->status !== GefahrenanalyseStatus::Freigegeben) {
            return 'gruen';
        }

        $naechste = $this->naechsteVerifizierung();

        if ($naechste === null || $naechste->lt(today())) {
            return 'rot';
        }

        if ($naechste->lte(today()->addDays(30))) {
            return 'gelb';
        }

        return 'gruen';
    }

    /**
     * Alle offenen Lenkungsmaßnahmen über ALLE Gefahren dieser Analyse (umgesetzt_am IS NULL).
     * SSOT — hatOffeneLenkungsmassnahmen() delegiert hierher.
     *
     * @return SupportCollection<int, Lenkungsmassnahme>
     */
    public function offeneLenkungsmassnahmen(): SupportCollection
    {
        return $this->gefahren
            ->flatMap(fn (LebensmittelGefahr $g) => $g->lenkungsmassnahmen)
            ->filter(fn (Lenkungsmassnahme $m) => $m->istOffen())
            ->values();
    }

    public function hatOffeneLenkungsmassnahmen(): bool
    {
        return $this->offeneLenkungsmassnahmen()->isNotEmpty();
    }

    /**
     * Signifikante Gefahren (Risiko mittel/hoch) ohne jede Lenkungsmaßnahme — HACCP-Lücke.
     * WHY: Eine signifikante Gefahr ohne Lenkung ist der gefährlichste Fall — single source of truth,
     * niemals still kappen (rot ausweisen statt verschweigen).
     *
     * @return SupportCollection<int, LebensmittelGefahr>
     */
    public function signifikanteGefahrenOhneLenkung(): SupportCollection
    {
        return $this->gefahren
            ->filter(fn (LebensmittelGefahr $g) => $g->signifikant() && ! $g->hatLenkung())
            ->values();
    }

    /**
     * Als CCP eingestufte Gefahren ohne verknüpften Überwachungs-Messpunkt — HACCP-Lücke
     * (Prinzip 4: ein CCP MUSS überwacht werden). SSOT, kein stilles Verschlucken.
     *
     * @return SupportCollection<int, LebensmittelGefahr>
     */
    public function ccpOhneUeberwachung(): SupportCollection
    {
        return $this->gefahren
            ->filter(fn (LebensmittelGefahr $g) => $g->istCcpOhneUeberwachung())
            ->values();
    }

    public function hatLuecke(): bool
    {
        return $this->signifikanteGefahrenOhneLenkung()->isNotEmpty()
            || $this->ccpOhneUeberwachung()->isNotEmpty();
    }

    /**
     * Höchste Risikostufe über alle Gefahren (gering < mittel < hoch). Null wenn keine Gefahren.
     */
    public function hoechsteRisikostufe(): ?string
    {
        if ($this->gefahren->isEmpty()) {
            return null;
        }

        $rang = ['gering' => 1, 'mittel' => 2, 'hoch' => 3];

        return $this->gefahren
            ->map(fn (LebensmittelGefahr $g) => $g->risikostufe())
            ->sortByDesc(fn (string $stufe) => $rang[$stufe] ?? 0)
            ->first();
    }
}
