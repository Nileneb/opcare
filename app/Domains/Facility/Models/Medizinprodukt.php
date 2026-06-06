<?php

namespace App\Domains\Facility\Models;

use App\Domains\Facility\Enums\MpAnlage;
use App\Domains\Identity\Models\Tenant;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Aktives nichtimplantierbares Medizinprodukt im Bestandsverzeichnis (§ 14 MPBetreibV). Für Anlage-1/2-Produkte
 * zugleich Medizinproduktebuch (§ 13): Einweisungen, STK/MTK-Fristen (Ampel) und Vorkommnisse. Tenant-scoped.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $bezeichnung
 * @property string|null $typ
 * @property string|null $hersteller
 * @property string|null $seriennummer
 * @property string|null $udi_di
 * @property string|null $inventarnummer
 * @property int|null $anschaffungsjahr
 * @property string|null $standort
 * @property string|null $zuordnung
 * @property string|null $risikoklasse
 * @property MpAnlage $anlage
 * @property Carbon|null $inbetriebnahme_am
 * @property Carbon|null $letzte_stk
 * @property int|null $stk_intervall_monate
 * @property Carbon|null $letzte_mtk
 * @property int|null $mtk_intervall_monate
 * @property Carbon|null $ausser_betrieb_am
 * @property string|null $notiz
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Activity> $activitiesAsSubject
 * @property-read Collection<int, MedizinproduktEinweisung> $einweisungen
 * @property-read Collection<int, MedizinproduktVorkommnis> $vorkommnisse
 * @property-read Tenant $tenant
 *
 * @mixin \Eloquent
 */
class Medizinprodukt extends BaseModel
{
    protected $table = 'medizinprodukte';

    protected $fillable = [
        'tenant_id', 'bezeichnung', 'typ', 'hersteller', 'seriennummer', 'udi_di', 'inventarnummer',
        'anschaffungsjahr', 'standort', 'zuordnung', 'risikoklasse', 'anlage', 'inbetriebnahme_am',
        'letzte_stk', 'stk_intervall_monate', 'letzte_mtk', 'mtk_intervall_monate', 'ausser_betrieb_am', 'notiz',
    ];

    protected $casts = [
        'anlage' => MpAnlage::class,
        'inbetriebnahme_am' => 'date',
        'letzte_stk' => 'date',
        'letzte_mtk' => 'date',
        'ausser_betrieb_am' => 'date',
    ];

    /** @return HasMany<MedizinproduktEinweisung, $this> */
    public function einweisungen(): HasMany
    {
        return $this->hasMany(MedizinproduktEinweisung::class, 'medizinprodukt_id');
    }

    /** @return HasMany<MedizinproduktVorkommnis, $this> */
    public function vorkommnisse(): HasMany
    {
        return $this->hasMany(MedizinproduktVorkommnis::class, 'medizinprodukt_id');
    }

    public function aktiv(): bool
    {
        return $this->ausser_betrieb_am === null;
    }

    public function naechsteStk(): ?Carbon
    {
        if (! $this->anlage->brauchtStk() || $this->stk_intervall_monate === null || $this->letzte_stk === null) {
            return null;
        }

        return $this->letzte_stk->copy()->addMonths($this->stk_intervall_monate);
    }

    public function naechsteMtk(): ?Carbon
    {
        if (! $this->anlage->brauchtMtk() || $this->mtk_intervall_monate === null || $this->letzte_mtk === null) {
            return null;
        }

        return $this->letzte_mtk->copy()->addMonths($this->mtk_intervall_monate);
    }

    /**
     * Schlechtester Prüfstatus über STK + MTK: red (überfällig) > amber (≤30 Tage / Pflicht ohne Termin) > green.
     * 'grau' = keine STK/MTK-Pflicht (Anlage „keine").
     */
    public function pruefAmpel(): string
    {
        if (! $this->anlage->brauchtMedizinproduktebuch()) {
            return 'grau';
        }

        $ampeln = [];
        foreach ([$this->anlage->brauchtStk() ? [$this->naechsteStk(), $this->letzte_stk] : null,
            $this->anlage->brauchtMtk() ? [$this->naechsteMtk(), $this->letzte_mtk] : null] as $pflicht) {
            if ($pflicht === null) {
                continue;
            }
            [$faellig, $letzte] = $pflicht;
            // WHY(MPBetreibV §12/§15): pflichtige Kontrolle ohne dokumentierten Termin gilt als offen → amber, nicht green.
            if ($faellig === null) {
                $ampeln[] = $letzte === null ? 'amber' : 'green';

                continue;
            }
            $ampeln[] = $faellig->isPast() ? 'red' : ($faellig->lte(today()->addDays(30)) ? 'amber' : 'green');
        }

        if (in_array('red', $ampeln, true)) {
            return 'red';
        }
        if (in_array('amber', $ampeln, true)) {
            return 'amber';
        }

        return 'green';
    }

    public function pruefungUeberfaellig(): bool
    {
        return $this->pruefAmpel() === 'red';
    }
}
