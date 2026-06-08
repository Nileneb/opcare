<?php

namespace App\Domains\Catering\Services;

use App\Domains\Catering\Models\HaccpMesspunkt;
use App\Domains\Catering\Models\Temperaturmessung;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;

/**
 * Erfasst eine Temperaturmessung an einem HACCP-Messpunkt.
 * Setzt das Abweichungs-Flag automatisch (VO (EG) 852/2004 Art. 5 Abs. 2 lit. c).
 */
class MessungErfassen
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    /**
     * @param  HaccpMesspunkt  $mp  Messpunkt (tenant-scoped, bereits validiert)
     * @param  float  $wert  Gemessener Temperaturwert in °C
     * @param  string  $gemessenAm  Messzeitpunkt (datetime-String, muss in der Vergangenheit liegen — UI-Pflicht)
     * @param  int|null  $userId  Erfassender Benutzer (nullable = anonyme Erfassung)
     * @param  string|null  $korrektur  Korrekturmaßnahme (wenn bei Abweichung bereits bekannt)
     */
    public function handle(
        HaccpMesspunkt $mp,
        float $wert,
        string $gemessenAm,
        ?int $userId = null,
        ?string $korrektur = null,
    ): Temperaturmessung {
        return DB::transaction(function () use ($mp, $wert, $gemessenAm, $userId, $korrektur): Temperaturmessung {
            return Temperaturmessung::create([
                'tenant_id' => $this->currentTenant->id(),
                'haccp_messpunkt_id' => $mp->id,
                'gemessen_am' => $gemessenAm,
                'wert' => $wert,
                'abweichung' => $mp->istAbweichung($wert),
                'korrekturmassnahme' => $korrektur,
                'erfasst_von' => $userId,
            ]);
        });
    }
}
