<?php

namespace App\Domains\CarePlanning\Enums;

enum ZielErreichung: string
{
    case Erreicht = 'erreicht';
    case Teilweise = 'teilweise';
    case Nicht = 'nicht';
}
