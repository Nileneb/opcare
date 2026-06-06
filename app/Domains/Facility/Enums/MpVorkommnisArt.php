<?php

namespace App\Domains\Facility\Enums;

/** Art einer Aufzeichnung im Medizinproduktebuch (§ 13 MPBetreibV) bzw. meldepflichtiges Vorkommnis. */
enum MpVorkommnisArt: string
{
    case Funktionsstoerung = 'funktionsstoerung';
    case BeinaheVorkommnis = 'beinahe_vorkommnis';
    case Vorkommnis = 'vorkommnis';

    public function label(): string
    {
        return match ($this) {
            self::Funktionsstoerung => 'Funktionsstörung',
            self::BeinaheVorkommnis => 'Beinahe-Vorkommnis',
            self::Vorkommnis => 'Schwerwiegendes Vorkommnis (meldepflichtig)',
        };
    }

    /** Schwerwiegende Vorkommnisse sind dem BfArM zu melden (§ 3 MPAMIV). */
    public function meldepflichtig(): bool
    {
        return $this === self::Vorkommnis;
    }
}
