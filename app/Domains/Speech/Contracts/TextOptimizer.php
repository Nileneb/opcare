<?php

namespace App\Domains\Speech\Contracts;

interface TextOptimizer
{
    /** Verbessert/strukturiert einen Pflegedoku-Text sprachlich — ohne Fakten zu erfinden. */
    public function optimize(string $text, ?string $context = null): string;
}
