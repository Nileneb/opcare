<?php

namespace App\Domains\Capture\Services;

use App\Domains\Accounting\Models\Artikel;
use App\Domains\Capture\Contracts\TextEmbedder;
use Illuminate\Support\Facades\Log;

class ArtikelEmbedder
{
    public function __construct(private readonly TextEmbedder $embedder) {}

    public function aktualisiere(Artikel $artikel): void
    {
        $vector = $this->embedder->embed($artikel->name);

        if ($vector === null) {
            Log::info('ArtikelEmbedder: embedding skipped for artikel '.$artikel->id);

            return;
        }

        $artikel->update([
            'name_embedding' => $vector,
            'embedding_model' => $this->embedder->model(),
        ]);
    }

    public function fehlt(Artikel $artikel): bool
    {
        return $artikel->name_embedding === null
            || $artikel->embedding_model !== $this->embedder->model();
    }
}
