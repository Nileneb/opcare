<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Models\Buchung;
use InvalidArgumentException;

/**
 * Erfasst einen Buchungssatz „Soll an Haben". Zentrale Stelle — auch die Warenwirtschaft bucht hierüber,
 * damit jede Lagerbewegung sauber in der Buchhaltung landet.
 */
class Buchen
{
    public function handle(int $sollKontoId, int $habenKontoId, float $betrag, string $text, string $datum, ?string $beleg = null): Buchung
    {
        if ($sollKontoId === $habenKontoId) {
            throw new InvalidArgumentException('Soll- und Haben-Konto müssen verschieden sein.');
        }
        if ($betrag <= 0) {
            throw new InvalidArgumentException('Der Buchungsbetrag muss positiv sein.');
        }

        return Buchung::create([
            'datum' => $datum,
            'soll_konto_id' => $sollKontoId,
            'haben_konto_id' => $habenKontoId,
            'betrag' => round($betrag, 2),
            'text' => $text,
            'beleg' => $beleg,
        ]);
    }
}
