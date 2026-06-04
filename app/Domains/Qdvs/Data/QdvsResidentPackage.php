<?php

namespace App\Domains\Qdvs\Data;

use Spatie\LaravelData\Data;

class QdvsResidentPackage extends Data
{
    public function __construct(
        public string $pseudonym,
        // WHY(DSGVO Art. 9): kein Klarname im Export, nur pseudonymisierte Kennung
        public ?int $geburtsjahr,
        public ?string $geschlecht,
        public ?int $pflegegrad,
        public ?string $aufnahme_am,
        /** @var array<int, string> ICD-10-Codes */
        public array $icd_codes = [],
        /** @var array<string, bool|string> indikator => befund (bool oder Schweregrad) */
        public array $indikatoren = [],
        // DAS-Feld-Ausbau (Plan: QDVS-Regel-Engine) — nullable, damit Altaufrufer kompatibel bleiben
        public ?int $geburtsmonat = null,
        public ?float $gewicht_kg = null,
        public ?string $gewicht_datum = null,
        public ?string $auszug_am = null,
        // Stichtag der Erhebung (= ERHEBUNGSDATUM); Bezug für Datums-Plausibilität
        public ?string $erhebungsdatum = null,
        /** @var array<string, string> indikator => ereignisdatum (ISO) für „Ereignis nach Einzug"-Regeln */
        public array $ereignis_daten = [],
    ) {}
}
