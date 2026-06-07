<?php

namespace App\Domains\Capture\Support;

use App\Domains\Accounting\Models\Lieferant;

class LieferantMatch
{
    public static function finde(string $text, int $tenantId): ?Lieferant
    {
        if ($text === '') {
            return null;
        }

        $norm = TextNorm::norm($text);

        // WHY(C3): $text==='' fängt nur exakt leere Strings; Whitespace/Sonderzeichen-Strings ergeben norm='' → Pseudo-Match
        if ($norm === '') {
            return null;
        }

        $lieferanten = Lieferant::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get();

        // Exact norm match first
        foreach ($lieferanten as $lieferant) {
            if (TextNorm::norm($lieferant->name) === $norm) {
                return $lieferant;
            }
        }

        // Substring match fallback
        foreach ($lieferanten as $lieferant) {
            $lieferantNorm = TextNorm::norm($lieferant->name);
            if (str_contains($norm, $lieferantNorm) || str_contains($lieferantNorm, $norm)) {
                return $lieferant;
            }
        }

        return null;
    }
}
