<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Enums\KontoTyp;
use App\Domains\Accounting\Models\Konto;
use App\Domains\Identity\Support\CurrentTenant;

/**
 * Standard-Kontenrahmen je Einrichtung (vereinfachter SKR): Bestands-/Verbindlichkeitskonten + je Abteilung
 * ein Aufwandskonto für den Warenverbrauch. Idempotent geseedet.
 */
class AccountingDefaults
{
    public const KASSE = '1000';

    public const BANK = '1200';

    public const VERBINDLICHKEITEN = '1600';

    public const WARENBESTAND = '3980';

    public const INVENTURDIFFERENZ = '4980';

    public const ANFANGSBESTAND = '9000';

    public static function ensureFor(int $tenantId): void
    {
        $standard = [
            [self::KASSE, 'Kasse', KontoTyp::Aktiv],
            [self::BANK, 'Bank', KontoTyp::Aktiv],
            [self::VERBINDLICHKEITEN, 'Verbindlichkeiten aus L+L', KontoTyp::Passiv],
            [self::WARENBESTAND, 'Warenbestand', KontoTyp::Aktiv],
            [self::INVENTURDIFFERENZ, 'Bestandsdifferenzen (Inventur)', KontoTyp::Aufwand],
            [self::ANFANGSBESTAND, 'Anfangsbestand (Eröffnungsbilanz)', KontoTyp::Passiv],
        ];
        foreach ($standard as [$nummer, $name, $typ]) {
            Konto::firstOrCreate(['tenant_id' => $tenantId, 'nummer' => $nummer], ['name' => $name, 'typ' => $typ->value]);
        }
        foreach (Abteilung::cases() as $abteilung) {
            Konto::firstOrCreate(
                ['tenant_id' => $tenantId, 'nummer' => $abteilung->aufwandKonto()],
                ['name' => $abteilung->aufwandName(), 'typ' => KontoTyp::Aufwand->value],
            );
        }
    }

    public static function konto(string $nummer): Konto
    {
        return Konto::where('tenant_id', app(CurrentTenant::class)->id())->where('nummer', $nummer)->firstOrFail();
    }
}
