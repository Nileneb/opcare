<?php

namespace App\Domains\Import\Services;

use App\Domains\Capture\Contracts\ArtikelMatcher;
use App\Domains\Capture\Support\LieferantMatch;
use App\Domains\Import\Enums\ImportAktion;
use App\Domains\Import\Models\ImportZeile;

class ImportMatching
{
    public function __construct(private readonly ArtikelMatcher $matcher) {}

    public function fuerZeile(ImportZeile $z, int $tenantId): void
    {
        if ($z->ziel_typ === 'artikel') {
            $kand = $this->matcher->match((string) $z->name, null, $tenantId);

            $z->kandidaten = array_map(fn ($k) => $k->toArray(), $kand);

            if (isset($kand[0]) && $kand[0]->score >= config('import.merge_threshold')) {
                $z->aktion = ImportAktion::Mergen;
                $z->matched_artikel_id = $kand[0]->artikel_id;
            } else {
                $z->aktion = ImportAktion::Anlegen;
            }
        } elseif ($z->ziel_typ === 'lieferant') {
            $lief = LieferantMatch::finde((string) ($z->lieferant_text ?? $z->name), $tenantId);

            if ($lief !== null) {
                $z->aktion = ImportAktion::Mergen;
                $z->matched_lieferant_id = $lief->id;
            } else {
                $z->aktion = ImportAktion::Anlegen;
            }
        }

        $z->save();
    }
}
