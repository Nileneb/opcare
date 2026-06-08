<?php

namespace App\Domains\Arbeitsschutz\Enums;

enum Gefaehrdungsfaktor: string
{
    case Arbeitsstaette = 'arbeitsstaette';
    case Einwirkungen = 'einwirkungen';
    case Arbeitsmittel = 'arbeitsmittel';
    case Verfahren = 'verfahren';
    case Qualifikation = 'qualifikation';
    case PsychischeBelastung = 'psychische_belastung';

    public function label(): string
    {
        return match ($this) {
            self::Arbeitsstaette => 'Gestaltung der Arbeitsstätte, des Arbeitsplatzes',
            self::Einwirkungen => 'Physikalische, chemische und biologische Einwirkungen',
            self::Arbeitsmittel => 'Gestaltung, Auswahl und Einsatz von Arbeitsmitteln',
            self::Verfahren => 'Gestaltung von Arbeits- und Fertigungsverfahren, Arbeitsabläufen und Arbeitszeit',
            self::Qualifikation => 'Unzureichende Qualifikation und Unterweisung der Beschäftigten',
            self::PsychischeBelastung => 'Psychische Belastungen bei der Arbeit',
        };
    }

    public function nummer(): int
    {
        return match ($this) {
            self::Arbeitsstaette => 1,
            self::Einwirkungen => 2,
            self::Arbeitsmittel => 3,
            self::Verfahren => 4,
            self::Qualifikation => 5,
            self::PsychischeBelastung => 6,
        };
    }

    public function paragraph(): string
    {
        return '§ 5 Abs. 3 Nr. '.$this->nummer().' ArbSchG';
    }
}
