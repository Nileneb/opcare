<?php

namespace App\Domains\Arbeitsschutz\Data;

use App\Domains\Arbeitsschutz\Enums\Belastungsstufe;
use Spatie\LaravelData\Data;

/**
 * Wohnbereichs-bezogener Belastungsbefund (schicht-/stationsbezogen, KEIN Personenbezug).
 * Norm-Anker: § 5 Abs. 3 Nr. 6 ArbSchG (psychische Belastung als Arbeitsbedingung).
 */
class BelastungsBefund extends Data
{
    public function __construct(
        public ?int $stationId,
        public string $wohnbereich,
        public Belastungsstufe $stufe,
        public int $score,
        /** @var array<string,string> */
        public array $signale,
        public int $lage = 0,
    ) {}
}
