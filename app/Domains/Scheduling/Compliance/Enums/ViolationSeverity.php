<?php

namespace App\Domains\Scheduling\Compliance\Enums;

/**
 * Schwere eines Arbeitszeit-Befunds. `NichtPruefbar` ist bewusst eigenständig: Regeln, deren Datengrundlage
 * opcare nicht erhebt (z. B. § 4 Pausen), werden ehrlich als „nicht prüfbar" ausgewiesen statt fälschlich
 * als bestanden gewertet.
 */
enum ViolationSeverity: string
{
    case Verstoss = 'verstoss';
    case Warnung = 'warnung';
    case Hinweis = 'hinweis';
    case NichtPruefbar = 'nicht_pruefbar';

    public function label(): string
    {
        return match ($this) {
            self::Verstoss => 'Verstoß',
            self::Warnung => 'Warnung',
            self::Hinweis => 'Hinweis',
            self::NichtPruefbar => 'nicht prüfbar',
        };
    }

    /** CSS-Badge-Klasse (admin.css: green/amber/red/gray). */
    public function badge(): string
    {
        return match ($this) {
            self::Verstoss => 'red',
            self::Warnung => 'amber',
            self::Hinweis => 'gray',
            self::NichtPruefbar => 'gray',
        };
    }

    /** Editierbare Schweren im Regel-Editor (NichtPruefbar wird vom Analyzer gesetzt, nicht konfiguriert). */
    public static function editable(): array
    {
        return [self::Verstoss, self::Warnung, self::Hinweis];
    }
}
