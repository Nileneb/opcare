<?php

namespace App\Domains\Medication\Actions;

use App\Domains\Medication\Enums\BtmVorgang;
use App\Domains\Medication\Models\BtmBuchung;
use App\Domains\Medication\Models\BtmKonto;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Erzeugt eine BtM-Buchung append-only (§ 13 BtMVV): laufende Nummer + fortgeschriebener Bestand werden
 * berechnet, der Bestand darf nicht negativ werden, Vernichtungen verlangen zwei Zeugen, Korrekturen einen
 * Bezug auf die Fehlbuchung + Grund. Bestehende Buchungen werden nie verändert.
 */
class BtmBuchen
{
    /**
     * @param  float  $menge  positive Menge (Vorzeichen ergibt sich aus dem Vorgang); bei Korrektur vorzeichenbehaftet.
     * @param  array<string, mixed>  $extra  lieferant, empfaenger, arzt_name, durchgefuehrt_von, zeuge_1, zeuge_2, vernichtungsmethode, korrigiert_buchung_id, grund
     */
    public function handle(BtmKonto $konto, BtmVorgang $vorgang, float $menge, string $datum, array $extra = []): BtmBuchung
    {
        if (! $konto->offen()) {
            throw new InvalidArgumentException('Konto ist geschlossen.');
        }
        if ($vorgang === BtmVorgang::Korrektur) {
            if (empty($extra['korrigiert_buchung_id']) || empty($extra['grund'])) {
                throw new InvalidArgumentException('Korrektur braucht Bezugsbuchung und Grund.');
            }
        } elseif ($menge <= 0) {
            throw new InvalidArgumentException('Menge muss positiv sein.');
        }
        if ($vorgang->brauchtZeugen() && (empty($extra['zeuge_1']) || empty($extra['zeuge_2']))) {
            throw new InvalidArgumentException('Vernichtung verlangt zwei Zeugen (BtMG § 16).');
        }

        return DB::transaction(function () use ($konto, $vorgang, $menge, $datum, $extra) {
            $letzte = BtmBuchung::where('btm_konto_id', $konto->id)->lockForUpdate()->orderByDesc('lfd_nr')->first();
            $lfdNr = ($letzte->lfd_nr ?? 0) + 1;
            $vorbestand = $letzte ? (float) $letzte->bestand_nach : 0.0;

            $signiert = $vorgang === BtmVorgang::Korrektur ? $menge : $vorgang->vorzeichen() * abs($menge);
            $bestandNach = round($vorbestand + $signiert, 3);
            if ($bestandNach < 0) {
                throw new InvalidArgumentException('Abgang übersteigt den Bestand ('.$vorbestand.' '.$konto->einheit.').');
            }

            return BtmBuchung::create([
                'tenant_id' => $konto->tenant_id,
                'btm_konto_id' => $konto->id,
                'lfd_nr' => $lfdNr,
                'vorgang' => $vorgang->value,
                'datum' => $datum,
                'menge' => $signiert,
                'bestand_nach' => $bestandNach,
                'lieferant' => $extra['lieferant'] ?? null,
                'empfaenger' => $extra['empfaenger'] ?? null,
                'arzt_name' => $extra['arzt_name'] ?? null,
                'durchgefuehrt_von' => $extra['durchgefuehrt_von'] ?? auth()->id(),
                'zeuge_1' => $extra['zeuge_1'] ?? null,
                'zeuge_2' => $extra['zeuge_2'] ?? null,
                'vernichtungsmethode' => $extra['vernichtungsmethode'] ?? null,
                'korrigiert_buchung_id' => $extra['korrigiert_buchung_id'] ?? null,
                'grund' => $extra['grund'] ?? null,
            ]);
        });
    }
}
