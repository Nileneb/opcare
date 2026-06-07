<?php

namespace App\Domains\Capture\Data;

use Spatie\LaravelData\Data;

/**
 * Strukturierte Extraktion eines Lieferschein- oder Rechnungsfotos durch das VLM. Alle Felder ausser
 * konfidenz sind nullable — das Modell wird angewiesen, fehlende Angaben als null zu lassen und KEINE
 * Werte zu erfinden. Diese Daten sind ein Vorschlag, nie autoritativ.
 *
 * @property LieferscheinPositionDaten[] $positionen
 */
class LieferscheinExtraktion extends Data
{
    /** @param  LieferscheinPositionDaten[]  $positionen */
    public function __construct(
        public ?string $lieferant = null,
        public ?string $datum = null,           // YYYY-MM-DD
        public ?string $lieferschein_nr = null,
        public float $konfidenz = 0.0,          // 0..1, Selbsteinschätzung des Modells
        public array $positionen = [],
    ) {}

    /**
     * Defensive Normalisierung aus einem rohen VLM-Array-Response.
     * Separate Factory (nicht from()-Override) um Spatie-Signatur-Konflikt zu vermeiden.
     *
     * @param  array<string, mixed>  $roh
     */
    public static function vonRoh(array $roh): self
    {
        $positionen = [];
        foreach ($roh['positionen'] ?? [] as $pos) {
            if (! is_array($pos)) {
                continue;
            }
            $positionen[] = new LieferscheinPositionDaten(
                text: (string) ($pos['text'] ?? ''),
                menge: isset($pos['menge']) && is_numeric($pos['menge']) ? (float) $pos['menge'] : null,
                einheit: isset($pos['einheit']) ? (string) $pos['einheit'] : null,
                einzelpreis: isset($pos['einzelpreis']) && is_numeric($pos['einzelpreis']) ? (float) $pos['einzelpreis'] : null,
                charge_nr: isset($pos['charge_nr']) ? (string) $pos['charge_nr'] : null,
                mhd: isset($pos['mhd']) ? (string) $pos['mhd'] : null,
            );
        }

        return new self(
            lieferant: isset($roh['lieferant']) ? (string) $roh['lieferant'] : null,
            datum: isset($roh['datum']) ? (string) $roh['datum'] : null,
            lieferschein_nr: isset($roh['lieferschein_nr']) ? (string) $roh['lieferschein_nr'] : null,
            konfidenz: isset($roh['konfidenz']) && is_numeric($roh['konfidenz']) ? (float) $roh['konfidenz'] : 0.0,
            positionen: $positionen,
        );
    }
}
