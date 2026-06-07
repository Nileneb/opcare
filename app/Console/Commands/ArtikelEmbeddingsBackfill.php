<?php

namespace App\Console\Commands;

use App\Domains\Accounting\Models\Artikel;
use App\Domains\Capture\Services\ArtikelEmbedder;
use Illuminate\Console\Command;

class ArtikelEmbeddingsBackfill extends Command
{
    protected $signature = 'artikel:embeddings-backfill';

    protected $description = 'Berechnet fehlende Artikel-Namens-Embeddings (idempotent, alle Mandanten).';

    public function handle(ArtikelEmbedder $embedder): int
    {
        $aktualisiert = 0;
        $uebersprungen = 0;

        // withoutGlobalScopes überbrückt den BelongsToTenant-TenantScope —
        // WHY: der Backfill soll mandantenübergreifend laufen, ohne einen Tenant im Kontext setzen zu müssen.
        Artikel::withoutGlobalScopes()->cursor()->each(function (Artikel $artikel) use ($embedder, &$aktualisiert, &$uebersprungen) {
            if ($embedder->fehlt($artikel)) {
                $embedder->aktualisiere($artikel);
                $aktualisiert++;
            } else {
                $uebersprungen++;
            }
        });

        $this->line("Aktualisiert: {$aktualisiert} | Übersprungen: {$uebersprungen}");

        return self::SUCCESS;
    }
}
