<?php

namespace App\Domains\Medication\Enums;

enum StockTransactionType: string
{
    case Zugang = 'zugang';
    case Entnahme = 'entnahme';
    case Korrektur = 'korrektur';
    case Verfall = 'verfall';
}
