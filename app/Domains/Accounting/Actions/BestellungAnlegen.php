<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Enums\BestellStatus;
use App\Domains\Accounting\Models\Bestellung;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BestellungAnlegen
{
    /**
     * @param  array<int, array{artikel_id: int, menge: float, preis?: float|null}>  $positionen
     */
    public function handle(int $lieferantId, array $positionen, ?int $userId = null, ?string $notiz = null, ?string $bestelldatum = null): Bestellung
    {
        if (empty($positionen)) {
            throw new InvalidArgumentException('Eine Bestellung muss mindestens eine Position enthalten.');
        }

        return DB::transaction(function () use ($lieferantId, $positionen, $userId, $notiz, $bestelldatum) {
            $tenantId = app(CurrentTenant::class)->id();

            $bestellung = Bestellung::create([
                'tenant_id' => $tenantId,
                'lieferant_id' => $lieferantId,
                'bestelldatum' => $bestelldatum ?? today()->toDateString(),
                'status' => BestellStatus::Bestellt,
                'erstellt_von' => $userId,
                'notiz' => $notiz,
            ]);

            foreach ($positionen as $pos) {
                $bestellung->positionen()->create([
                    'tenant_id' => $tenantId,
                    'artikel_id' => $pos['artikel_id'],
                    'menge_bestellt' => $pos['menge'],
                    'menge_geliefert' => 0,
                    'einzelpreis' => $pos['preis'] ?? null,
                ]);
            }

            return $bestellung;
        });
    }
}
