<?php

namespace App\Domains\Capture\Testing;

use App\Domains\Accounting\Models\Artikel;
use App\Domains\Capture\Contracts\ArtikelMatcher;
use App\Domains\Capture\Data\ArtikelKandidat;
use App\Domains\Capture\Models\LieferantArtikelAlias;
use App\Domains\Capture\Support\TextNorm;

class FakeArtikelMatcher implements ArtikelMatcher
{
    /** @return ArtikelKandidat[] */
    public function match(string $positionsText, ?int $lieferantId, int $tenantId, int $topK = 5): array
    {
        $norm = TextNorm::norm($positionsText);

        $kandidaten = Artikel::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->filter(function (Artikel $a) use ($norm) {
                $artikelNorm = TextNorm::norm($a->name);

                return str_contains($norm, $artikelNorm) || str_contains($artikelNorm, $norm);
            })
            ->map(fn (Artikel $a) => new ArtikelKandidat(
                artikel_id: $a->id,
                name: $a->name,
                score: 1.0,
                quelle: 'gedaechtnis',
            ))
            ->values()
            ->all();

        return array_slice($kandidaten, 0, $topK);
    }

    public function merke(string $positionsText, ?int $lieferantId, int $tenantId, int $artikelId): void
    {
        $norm = TextNorm::norm($positionsText);

        $existing = LieferantArtikelAlias::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('lieferant_id', $lieferantId)
            ->where('norm_text', $norm)
            ->where('artikel_id', $artikelId)
            ->first();

        if ($existing) {
            $existing->increment('treffer');
        } else {
            LieferantArtikelAlias::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'lieferant_id' => $lieferantId,
                'norm_text' => $norm,
                'artikel_id' => $artikelId,
                'treffer' => 1,
            ]);
        }
    }
}
