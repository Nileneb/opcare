<?php

namespace App\Domains\Scheduling\Support;

/**
 * Ergebnis eines Auto-Dienstplan-Laufs: wie viele Schicht-Slots gefordert/besetzt waren und welche
 * unbesetzt blieben (Unterdeckung — transparent gemeldet, nicht stillschweigend übergangen).
 */
class GenerationResult
{
    /** @param  array<int, string>  $offeneSlots */
    public function __construct(
        public readonly int $slots,
        public readonly int $besetzt,
        public readonly int $erstellt,
        public readonly array $offeneSlots,
    ) {}

    public function deckung(): int
    {
        return $this->slots > 0 ? (int) round($this->besetzt / $this->slots * 100) : 100;
    }
}
