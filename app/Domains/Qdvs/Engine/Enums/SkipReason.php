<?php

namespace App\Domains\Qdvs\Engine\Enums;

enum SkipReason: string
{
    // Kein Pattern-Matcher hat den assert_test erkannt
    case UnknownPattern = 'unknown_pattern';
    // assert_test erkannt, aber ein referenziertes DAS-Feld ist nicht auf das opcare-DTO gemappt
    case UnmappedField = 'unmapped_field';
    // Regel prüft über alle Datensätze hinweg (.//resident) — im Single-Package-Scope nicht auswertbar
    case OutOfScopeAggregate = 'out_of_scope_aggregate';

    public function label(): string
    {
        return match ($this) {
            self::UnknownPattern => 'Regelmuster nicht unterstützt',
            self::UnmappedField => 'Datenfeld in OPCare nicht erhoben',
            self::OutOfScopeAggregate => 'Datensatzübergreifende Regel (nicht im Einzelpaket prüfbar)',
        };
    }
}
