<?php

namespace App\Domains\Arbeitsschutz\Services;

use App\Domains\Arbeitsschutz\Models\BelastungFreischaltung;
use App\Domains\Arbeitsschutz\Models\PersoenlicheBelastung;
use App\Domains\Identity\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Setzt den persönlichen Belastungswert (Selbst-Ampel, Mode B).
 *
 * Gate: nur aktiv wenn Freischaltung für den Mandanten existiert (§ 87 BetrVG-Analogie).
 * Invariante: Jede:r setzt ausschließlich den eigenen Wert — kein Fremdzugriff möglich.
 */
class PersoenlicheBelastungSetzen
{
    /**
     * @throws HttpException 403 ohne aktive Freischaltung
     * @throws HttpException 422 wenn wert außerhalb 0-10
     */
    public function handle(User $user, int $wert): PersoenlicheBelastung
    {
        abort_unless(BelastungFreischaltung::aktivFuer($user->tenant_id), 403, 'Selbst-Ampel ist für diesen Mandanten nicht freigeschaltet.');

        abort_if($wert < 0 || $wert > 10, 422, 'Belastungswert muss zwischen 0 und 10 liegen.');

        return PersoenlicheBelastung::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'wert' => $wert,
        ]);
    }

    public function aktuellerWert(User $user): ?int
    {
        return PersoenlicheBelastung::where('user_id', $user->id)
            ->where('tenant_id', $user->tenant_id)
            ->latest('id')
            ->value('wert');
    }
}
