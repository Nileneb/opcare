<?php

namespace App\Domains\Qdvs\Engine\Support;

use App\Domains\Qdvs\Data\QdvsResidentPackage;

class FieldMap
{
    /** @var array<string, FieldBinding> */
    private array $bindings;

    public function __construct()
    {
        $this->bindings = $this->define();
    }

    public function has(string $dasField): bool
    {
        return isset($this->bindings[$dasField]);
    }

    public function kind(string $dasField): ?string
    {
        return $this->bindings[$dasField]->kind ?? null;
    }

    /**
     * Liefert den auf das DAS-Vokabular transformierten Wert eines Feldes aus dem Paket.
     * Wirft, wenn das Feld nicht gemappt ist — Aufrufer prüfen has() vorab.
     */
    public function value(string $dasField, QdvsResidentPackage $package): mixed
    {
        $binding = $this->bindings[$dasField];
        $raw = ($binding->accessor)($package);

        return $binding->transform ? ($binding->transform)($raw) : $raw;
    }

    /** @return array<string, FieldBinding> */
    private function define(): array
    {
        return [
            // WHY(DSGVO): bewohnerbezogene Nummer als reine Ziffern aus dem Pseudonym (R-123 → 123)
            'IDBEWOHNER' => new FieldBinding(
                fn (QdvsResidentPackage $p) => $p->pseudonym,
                'int',
                fn ($v) => $v === null ? null : preg_replace('/\D/', '', (string) $v),
            ),
            'GEBURTSJAHR' => new FieldBinding(fn (QdvsResidentPackage $p) => $p->geburtsjahr, 'int'),
            'GEBURTSMONAT' => new FieldBinding(fn (QdvsResidentPackage $p) => $p->geburtsmonat, 'int'),
            // WHY(DAS_REGELN): DAS-Feld 7 ist „Pflegegrad vorhanden? 0/1" — opcare speichert den Grad 1–5.
            // Vorhanden→'1' ist immer DAS-gültig, daher können die Schlüsselwert-/Typregeln (20003/30007)
            // strukturell nicht auslösen; die fachliche 1–5-Prüfung liegt nativ im QdvsValidator.
            'PFLEGEGRAD' => new FieldBinding(
                fn (QdvsResidentPackage $p) => $p->pflegegrad,
                'scalar',
                fn ($v) => $v === null ? null : '1',
            ),
            'ERHEBUNGSDATUM' => new FieldBinding(fn (QdvsResidentPackage $p) => $p->erhebungsdatum, 'date'),
            'EINZUGSDATUM' => new FieldBinding(fn (QdvsResidentPackage $p) => $p->aufnahme_am, 'date'),
            'AUSZUGSDATUM' => new FieldBinding(fn (QdvsResidentPackage $p) => $p->auszug_am, 'date'),
            'KOERPERGEWICHT' => new FieldBinding(fn (QdvsResidentPackage $p) => $p->gewicht_kg, 'decimal'),
            'KOERPERGEWICHTDATUM' => new FieldBinding(fn (QdvsResidentPackage $p) => $p->gewicht_datum, 'date'),
            // WHY(DAS_REGELN): DEKUBITUS als 0/1 (vorhanden) — die DAS-1-vs-2-Unterscheidung + LOK-Herkunftscode
            // bleiben bewusst ungemappt (amtliche DAS-Codesemantik nicht verifiziert). Stadium/Beginn/Ende
            // werden aus dem am Stichtag aktiven Dekubitus-CareEvent gespeist; ein Dekubitus ohne Stadium
            // meldet Regel 60019 korrekt als Datenlücke.
            'DEKUBITUS' => new FieldBinding(
                fn (QdvsResidentPackage $p) => $p->indikatoren['dekubitus'] ?? false,
                'int',
                fn ($v) => $v ? '1' : '0',
            ),
            'DEKUBITUSSTADIUM' => new FieldBinding(fn (QdvsResidentPackage $p) => $p->dekubitus_stadium, 'int'),
            'DEKUBITUS1BEGINNDATUM' => new FieldBinding(fn (QdvsResidentPackage $p) => $p->dekubitus_beginn, 'date'),
            'DEKUBITUS1ENDEDATUM' => new FieldBinding(fn (QdvsResidentPackage $p) => $p->dekubitus_ende, 'date'),
            // WHY(DAS_REGELN): STURZ (Feld 71) ist Pflichtfeld 0/1/2 (nein/einmal/mehrmals) — ohne Sturzereignis
            // immer '0' (nein), sonst die erfasste Anzahl. Schaltet 10055/20056/30075/60039 scharf.
            'STURZ' => new FieldBinding(
                fn (QdvsResidentPackage $p) => $p->sturz_anzahl,
                'int',
                fn ($v) => (string) ($v ?? 0),
            ),
            // WHY(DAS_REGELN): STURZFOLGEN (Feld 72, Mehrfachwerte) — nur die aus dem Regeltext verifizierten
            // Codes 0=keine/1=Fraktur werden gemappt. Codes 2–4 (SHT etc.) bleiben bewusst ungemappt
            // (amtliches Erhebungsinstrument nicht frei verfügbar), daher klassifizieren 30076/60040/70003
            // weiter als nicht erkanntes Muster bzw. werden nicht gefälscht.
            'STURZFOLGEN' => new FieldBinding(
                fn (QdvsResidentPackage $p) => $p->sturzfolgen === [] ? null : array_map('strval', $p->sturzfolgen),
                'list',
            ),
            // WHY(DAS_REGELN): DAS-DIAGNOSEN ist ein codiertes 0/1/2/3-Feld (Regeln 20011/70001),
            // NICHT die ICD-10-Codes von OPCare → bewusst nicht gemappt (Regeln laufen als UNMAPPED).
            // Die „keine Diagnose hinterlegt"-Warnung bleibt native im QdvsValidator.
        ];
    }
}
