<?php

namespace App\Domains\Speech\Testing;

use App\Domains\Speech\Contracts\TextOptimizer;
use Illuminate\Support\Str;

class FakeTextOptimizer implements TextOptimizer
{
    /** Deterministische „Optimierung" ohne LLM: Whitespace säubern, Satz formen. */
    public function optimize(string $text, ?string $context = null): string
    {
        $clean = Str::of($text)->squish();

        if ($clean->isEmpty()) {
            return '';
        }

        $clean = $clean->ucfirst();

        return $clean->endsWith(['.', '!', '?']) ? (string) $clean : $clean.'.';
    }
}
