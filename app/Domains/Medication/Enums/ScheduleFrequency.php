<?php

namespace App\Domains\Medication\Enums;

enum ScheduleFrequency: string
{
    case Taeglich = 'taeglich';
    case Woechentlich = 'woechentlich';
    case Monatlich = 'monatlich';
    case BeiBedarf = 'bei_bedarf';
}
