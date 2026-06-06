<?php

namespace App\Domains\Capture\Data;

use Spatie\LaravelData\Data;

/**
 * Strukturierte Extraktion eines Belegfotos durch das VLM. Alle Felder sind nullable — das Modell wird angewiesen,
 * fehlende Angaben als null zu lassen und KEINE Werte zu erfinden. Diese Daten sind ein Vorschlag, nie autoritativ.
 *
 * @property array<int, array{text: string, betrag: float|null}> $positionen
 */
class BelegExtraktion extends Data
{
    /** @param  array<int, array{text: string, betrag: float|null}>  $positionen */
    public function __construct(
        public ?string $belegtyp = null,   // z. B. rechnung, quittung, kassenbon
        public ?string $datum = null,       // YYYY-MM-DD
        public ?float $betrag = null,        // Gesamtbetrag
        public ?string $waehrung = 'EUR',
        public ?string $lieferant = null,
        public array $positionen = [],
        public float $konfidenz = 0.0,       // 0..1, Selbsteinschätzung des Modells
    ) {}
}
