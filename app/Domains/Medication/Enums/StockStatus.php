<?php

namespace App\Domains\Medication\Enums;

enum StockStatus: string
{
    case Vorraetig = 'vorraetig';
    case Angebrochen = 'angebrochen';
    case Leer = 'leer';
    case Verfallen = 'verfallen';
}
