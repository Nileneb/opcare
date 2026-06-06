<?php

namespace App\Domains\Capture\Services;

use App\Domains\Accounting\Actions\Buchen;
use App\Domains\Capture\Contracts\BelegVlmAnalyzer;
use App\Domains\Capture\Enums\VorschlagStatus;
use App\Domains\Capture\Enums\ZielTyp;
use App\Domains\Capture\Models\BelegAnalyse;
use App\Domains\Capture\Models\EinsortierungsVorschlag;
use RuntimeException;

/**
 * Orchestriert die Beleg-Erfassung: Foto → VLM-Analyse → persistierter Einsortierungs-Vorschlag → berechtigte
 * Bestätigung schreibt den Zieldatensatz (hier: eine Buchung über die bestehende Buchen-Action). Die VLM-Ausgabe
 * ist nie autoritativ — `bestaetige()`/`verwerfe()` setzt erst der Mensch (Berechtigung wird im Aufrufer gegated).
 */
class BelegCapture
{
    public function __construct(private BelegVlmAnalyzer $analyzer) {}

    public function erfasse(string $pfad, string $dateiname, string $mime, int $userId): BelegAnalyse
    {
        $base64 = base64_encode((string) file_get_contents($pfad));
        $extraktion = $this->analyzer->analysiere($base64, $mime);

        $analyse = BelegAnalyse::create([
            'modell' => config('speech.fake') ? 'fake' : (string) config('speech.capture.model'),
            'konfidenz' => $extraktion->konfidenz,
            'roh_json' => $extraktion->toArray(),
            'erstellt_von' => $userId,
        ]);
        $analyse->addMedia($pfad)->usingFileName($dateiname)->toMediaCollection('beleg');

        // Heuristik: nur mit einem positiven Betrag ist der Beleg als Buchung einsortierbar — sonst „unklar"
        // (kein geratenes Ziel). Der Mensch entscheidet.
        $buchbar = $extraktion->betrag !== null && $extraktion->betrag > 0;

        $analyse->vorschlaege()->create([
            'tenant_id' => $analyse->tenant_id,
            'ziel_typ' => $buchbar ? ZielTyp::BuchhaltungBeleg : ZielTyp::Unklar,
            'ziel_felder' => [
                'betrag' => $extraktion->betrag,
                'datum' => $extraktion->datum,
                'lieferant' => $extraktion->lieferant,
                'belegtyp' => $extraktion->belegtyp,
            ],
            'status' => VorschlagStatus::Vorgeschlagen,
            'konfidenz' => $extraktion->konfidenz,
        ]);

        return $analyse->load('vorschlaege');
    }

    public function bestaetige(EinsortierungsVorschlag $vorschlag, int $userId, int $sollKontoId, int $habenKontoId, string $text, string $datum, Buchen $buchen): EinsortierungsVorschlag
    {
        if (! $vorschlag->offen()) {
            throw new RuntimeException('Vorschlag ist bereits entschieden.');
        }
        if (! $vorschlag->ziel_typ->buchbar()) {
            throw new RuntimeException('Dieser Vorschlag ist nicht als Buchung bestätigbar.');
        }

        $betrag = (float) ($vorschlag->ziel_felder['betrag'] ?? 0);
        $buchung = $buchen->handle($sollKontoId, $habenKontoId, $betrag, $text, $datum, 'VLM-Beleg #'.$vorschlag->beleg_analyse_id);

        $vorschlag->update([
            'status' => VorschlagStatus::Bestaetigt,
            'buchung_id' => $buchung->id,
            'entschieden_von' => $userId,
            'entschieden_am' => now(),
        ]);

        return $vorschlag;
    }

    public function verwerfe(EinsortierungsVorschlag $vorschlag, int $userId): EinsortierungsVorschlag
    {
        if (! $vorschlag->offen()) {
            throw new RuntimeException('Vorschlag ist bereits entschieden.');
        }

        $vorschlag->update([
            'status' => VorschlagStatus::Verworfen,
            'entschieden_von' => $userId,
            'entschieden_am' => now(),
        ]);

        return $vorschlag;
    }
}
