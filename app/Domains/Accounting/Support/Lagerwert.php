<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lagerschicht;

/**
 * Berechnet den FIFO-Bestandswert aus den offenen Eingangsschichten (§ 256 HGB): Σ Restmenge × Einstandspreis.
 * Liefert den aktuellen (Live-)Wert; historische Werte stammen aus den eingefrorenen Inventur-Abschlüssen.
 */
class Lagerwert
{
    public function bestandswert(Artikel $artikel): float
    {
        return round((float) $artikel->schichten()->where('menge_rest', '>', 0)->get()
            ->sum(fn (Lagerschicht $s) => (float) $s->menge_rest * (float) $s->einstandspreis), 2);
    }

    public function bestandswertGesamt(int $tenantId, ?Abteilung $abteilung = null): float
    {
        $artikel = Artikel::where('tenant_id', $tenantId)
            ->when($abteilung, fn ($q) => $q->where('abteilung', $abteilung->value))->get();

        return round((float) $artikel->sum(fn (Artikel $a) => $this->bestandswert($a)), 2);
    }
}
