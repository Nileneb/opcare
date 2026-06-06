<?php

namespace App\Domains\Scheduling\Compliance\Data;

use App\Domains\Scheduling\Compliance\Enums\ViolationSeverity;
use Spatie\LaravelData\Data;

class ComplianceFinding extends Data
{
    /**
     * @param  array<int, string>  $dates  betroffene Tage/Wochen (Anzeige + Export)
     */
    public function __construct(
        public string $ruleKey,
        public string $paragraph,
        public ViolationSeverity $severity,
        public string $label,
        public string $message,
        public int $userId,
        public string $userName,
        public array $dates,
        public string $gesetzUrl,
        // dokumentierte § 14-Begründung (null = offener Befund). Gesetzt vom ComplianceReporter.
        public ?string $begruendung = null,
        public ?string $begruendetVon = null,
    ) {}

    public function istBegruendet(): bool
    {
        return $this->begruendung !== null;
    }

    /** True, wenn der Befund einer Begründung bedarf (Verstoß/Warnung) und noch keine hat. */
    public function offenerVerstoss(): bool
    {
        return ! $this->istBegruendet()
            && in_array($this->severity, [ViolationSeverity::Verstoss, ViolationSeverity::Warnung], true);
    }
}
