<?php

namespace App\Domains\Scheduling\Enums;

enum RecurrenceFreq: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
