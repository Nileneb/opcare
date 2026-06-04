<?php

namespace App\Domains\Assessment\Support;

class ScoreCalculator
{
    /** @param array<int, int> $punkte */
    public function sum(array $punkte): int
    {
        return array_sum($punkte);
    }
}
