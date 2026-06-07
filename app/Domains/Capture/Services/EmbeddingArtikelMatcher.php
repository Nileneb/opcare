<?php

namespace App\Domains\Capture\Services;

use App\Domains\Accounting\Models\Artikel;
use App\Domains\Capture\Contracts\ArtikelMatcher;
use App\Domains\Capture\Contracts\TextEmbedder;
use App\Domains\Capture\Data\ArtikelKandidat;
use App\Domains\Capture\Models\LieferantArtikelAlias;
use App\Domains\Capture\Support\TextNorm;
use Illuminate\Support\Facades\Log;

class EmbeddingArtikelMatcher implements ArtikelMatcher
{
    public function __construct(private readonly TextEmbedder $embedder) {}

    /** @return ArtikelKandidat[] */
    public function match(string $positionsText, ?int $lieferantId, int $tenantId, int $topK = 5): array
    {
        $norm = TextNorm::norm($positionsText);

        // WHY(A2): leerer Normtext matcht via str_contains(x,'')===true jeden Alias — kein sinnvolles Ergebnis
        if ($norm === '') {
            return [];
        }

        $kandidaten = [];

        // Primär: Lerngedächtnis
        $aliasse = LieferantArtikelAlias::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($lieferantId) {
                $q->where('lieferant_id', $lieferantId)->orWhereNull('lieferant_id');
            })
            ->get();

        foreach ($aliasse as $alias) {
            $aliasNorm = $alias->norm_text;
            $score = null;

            if ($aliasNorm === $norm) {
                $score = 1.0 + min(0.2, $alias->treffer * 0.02);
            } elseif (str_contains($aliasNorm, $norm) || str_contains($norm, $aliasNorm)) {
                // WHY(B1): 0.75 statt 0.8 damit starker Embedding-Match (cosine≥0.75) Substring schlagen kann
                $score = 0.75;
            }

            if ($score !== null) {
                $id = $alias->artikel_id;
                if (! isset($kandidaten[$id]) || $kandidaten[$id]->score < $score) {
                    $kandidaten[$id] = new ArtikelKandidat(
                        artikel_id: $id,
                        name: '',
                        score: $score,
                        quelle: 'gedaechtnis',
                    );
                }
            }
        }

        // Sekundär: Embedding-Cosine
        $queryVec = $this->embedder->embed($positionsText);

        if ($queryVec === null) {
            Log::info('artikel-match: embedding skipped (model unavailable)');
        } else {
            $artikel = Artikel::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereNotNull('name_embedding')
                ->get();

            foreach ($artikel as $a) {
                $embedding = $a->name_embedding;
                if (! is_array($embedding)) {
                    continue;
                }

                $cosine = $this->cosine($queryVec, $embedding);
                // WHY(B1): Schwelle 0.6 statt 0.5 + kein *0.6-Faktor → Embedding(cosine 0.9)=0.9 > Substring(0.75)
                if ($cosine < 0.6) {
                    continue;
                }

                $score = $cosine;
                $id = $a->id;

                if (! isset($kandidaten[$id]) || $kandidaten[$id]->score < $score) {
                    $kandidaten[$id] = new ArtikelKandidat(
                        artikel_id: $id,
                        name: '',
                        score: $score,
                        quelle: 'embedding',
                    );
                }
            }
        }

        if (empty($kandidaten)) {
            return [];
        }

        // Artikelnamen nachladen
        $ids = array_keys($kandidaten);
        $namen = Artikel::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->pluck('name', 'id');

        foreach ($kandidaten as $id => $k) {
            $kandidaten[$id] = new ArtikelKandidat(
                artikel_id: $k->artikel_id,
                name: $namen[$id] ?? '',
                score: $k->score,
                quelle: $k->quelle,
            );
        }

        usort($kandidaten, fn ($a, $b) => $b->score <=> $a->score);

        return array_slice($kandidaten, 0, $topK);
    }

    public function merke(string $positionsText, ?int $lieferantId, int $tenantId, int $artikelId): void
    {
        $norm = TextNorm::norm($positionsText);

        // WHY(B2): leerer Norm würde Alias mit norm_text='' anlegen — Gift fürs Gedächtnis (str_contains-Alles-Treffer)
        if ($norm === '') {
            return;
        }

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

    /** @param float[] $a @param float[] $b */
    private function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        $denominator = sqrt($normA) * sqrt($normB);

        // WHY: Cosine undefined when either vector is zero-length — math. Definition, kein maskierter Fehler
        if ($denominator == 0.0) {
            return 0.0;
        }

        return $dot / $denominator;
    }
}
