<?php

namespace App\Domains\Capture\Support;

use Illuminate\Support\Str;

class TextNorm
{
    public static function norm(string $s): string
    {
        $cleaned = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s) ?? '';

        return Str::lower(trim(preg_replace('/\s+/', ' ', $cleaned) ?? ''));
    }
}
