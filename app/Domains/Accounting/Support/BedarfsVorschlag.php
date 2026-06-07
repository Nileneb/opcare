<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Models\Artikel;
use Illuminate\Support\Collection;

class BedarfsVorschlag
{
    /**
     * Returns articles below minimum stock for the given tenant.
     *
     * @return Collection<int, array{artikel: Artikel, vorschlag: float}>
     */
    public function fuer(int $tenantId): Collection
    {
        return Artikel::where('tenant_id', $tenantId)
            ->whereNotNull('mindestbestand')
            ->whereRaw('bestand < mindestbestand')
            ->get()
            ->map(fn (Artikel $a) => [
                'artikel' => $a,
                'vorschlag' => max(1.0, (float) $a->mindestbestand - (float) $a->bestand),
            ]);
    }
}
