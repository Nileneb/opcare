<?php

namespace App\Domains\Scheduling\Data;

use App\Domains\Scheduling\Enums\CalendarEventType;
use Spatie\LaravelData\Data;

class CalendarEventData extends Data
{
    public function __construct(
        public CalendarEventType $type,
        public string $titel,
        public string $beginnt_am,
        public int $created_by,
        public ?int $resident_id = null,
        public ?string $beschreibung = null,
        public ?string $endet_am = null,
        public bool $ganztaegig = false,
        public ?RecurrenceData $recurrence = null,
    ) {}
}
