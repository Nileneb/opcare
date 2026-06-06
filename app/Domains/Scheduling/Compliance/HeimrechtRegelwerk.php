<?php

namespace App\Domains\Scheduling\Compliance;

use App\Domains\Identity\Enums\Bundesland;

/**
 * Landes-Override-Ebene des föderalen Heimrechts (Föderalismusreform 2006, siehe docs/recherche-offene-punkte
 * §8). Norm-als-Daten in drei Schichten: Bundes-Default → Landes-Override → Träger-Override (`StaffingConfig`,
 * editierbar). Hier liegt die mittlere Schicht.
 *
 * Quantitativer Bundes-Default: Fachkraftquote 50 % (§ 5 HeimPersV, bundeseinheitlich fortgeltend), nächtliche
 * Personalrelation als bundeseinheitlicher Richtwert. Wo ein Land einen abweichenden, verifizierten Schlüssel
 * normiert, wird er in `OVERRIDES` hinterlegt; sonst gilt der Bundeswert. Es werden KEINE Landeswerte geraten —
 * fehlt ein verifizierter Wert, ist das Feld bewusst nicht gesetzt und der Träger trägt seinen Landeswert im
 * Betreuungsschlüssel ein (transparent statt fälschlich „landesspezifisch").
 */
class HeimrechtRegelwerk
{
    public const FACHKRAFTQUOTE_BUND = 0.5;

    public const NACHTDIENST_RICHTWERT_BUND = 50;

    /**
     * Verifizierte landesrechtliche Abweichungen vom Bundes-Default. Erweiterungs-Punkt: sobald ein Landeswert
     * verifiziert ist (Legal Data Hunter §8), hier eintragen, z. B.
     * `Bundesland::XX->value => ['nachtdienst_je_fachkraft' => 40]`. Bewusst leer statt geratener Werte.
     *
     * @return array<string, array{fachkraftquote_min?: float, nachtdienst_je_fachkraft?: int}>
     */
    public static function overrides(): array
    {
        return [];
    }

    /**
     * Effektive Personalbemessungs-Defaults für ein Bundesland (Bundeswert + Landes-Override).
     *
     * @return array{fachkraftquote_min: float, nachtdienst_je_fachkraft: int, landesspezifisch: bool}
     */
    public static function fuer(?Bundesland $land): array
    {
        $override = $land !== null ? (self::overrides()[$land->value] ?? []) : [];

        return [
            'fachkraftquote_min' => $override['fachkraftquote_min'] ?? self::FACHKRAFTQUOTE_BUND,
            'nachtdienst_je_fachkraft' => $override['nachtdienst_je_fachkraft'] ?? self::NACHTDIENST_RICHTWERT_BUND,
            'landesspezifisch' => $override !== [],
        ];
    }

    public static function hatLandeswert(?Bundesland $land): bool
    {
        return $land !== null && (self::overrides()[$land->value] ?? []) !== [];
    }
}
