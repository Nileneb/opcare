<?php

namespace App\Domains\Scheduling\Compliance\Data;

use App\Domains\Scheduling\Models\Spitzenzeit;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ergebnis der Spitzenzeit-Deckungsanalyse für eine Planungswoche: je Bedarfs-Fenster × Tag die Ist-Besetzung
 * (Mitarbeitende mit überlappender Schicht) gegen die Soll-Vorgabe, mit Ampel, plus Vorschläge für Unterdeckung.
 */
class SpitzenzeitAnalyse
{
    /**
     * @param  Collection<int, Spitzenzeit>  $fenster
     * @param  list<array{datum: string, kurz: string, tag: string, wochenende: bool}>  $tage
     * @param  array<int, array<string, array{ist: int, soll: int, ampel: string, aktiv: bool}>>  $zellen
     * @param  list<string>  $vorschlaege
     */
    public function __construct(
        public readonly Collection $fenster,
        public readonly array $tage,
        public readonly array $zellen,
        public readonly array $vorschlaege,
    ) {}

    public function unterdeckungen(): int
    {
        $n = 0;
        foreach ($this->zellen as $proFenster) {
            foreach ($proFenster as $zelle) {
                if ($zelle['aktiv'] && $zelle['ampel'] !== 'gruen') {
                    $n++;
                }
            }
        }

        return $n;
    }
}
