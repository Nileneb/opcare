<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lagerbewegung;
use App\Domains\Accounting\Support\AccountingDefaults;
use Illuminate\Support\Facades\DB;

/**
 * Wareneingang: erhöht den Bestand, legt eine FIFO-Eingangsschicht (Lot) mit ihrem Einstandspreis an und bucht
 * den Einkauf (Soll Warenbestand an Haben Verbindlichkeiten). Die Bewertung der Vorräte (§ 256 HGB) kommt
 * ausschließlich aus den Schichten; `artikel.einkaufspreis` bleibt nur Anzeige-/Bestell-Default.
 */
class Wareneingang
{
    public function __construct(private readonly Buchen $buchen) {}

    public function handle(Artikel $artikel, float $menge, ?float $preis, string $datum, ?string $notiz = null, ?string $chargeNr = null, ?string $mhd = null, ?int $lieferantId = null, ?string $gegenkonto = null): Lagerbewegung
    {
        return DB::transaction(function () use ($artikel, $menge, $preis, $datum, $notiz, $chargeNr, $mhd, $lieferantId, $gegenkonto) {
            AccountingDefaults::ensureFor($artikel->tenant_id);
            $stueckpreis = $preis ?? (float) ($artikel->einkaufspreis ?? 0);

            $artikel->bestand = (float) $artikel->bestand + $menge;
            if ($preis !== null) {
                $artikel->einkaufspreis = $preis;
            }
            $artikel->save();

            $buchung = null;
            $betrag = round($menge * $stueckpreis, 2);
            if ($betrag > 0) {
                $buchung = $this->buchen->handle(
                    AccountingDefaults::konto(AccountingDefaults::WARENBESTAND)->id,
                    AccountingDefaults::konto($gegenkonto ?? AccountingDefaults::VERBINDLICHKEITEN)->id,
                    $betrag, 'Wareneingang: '.$artikel->name, $datum,
                );
            }

            $bewegung = $artikel->bewegungen()->create([
                'typ' => 'eingang', 'menge' => $menge, 'datum' => $datum, 'notiz' => $notiz, 'buchung_id' => $buchung?->id,
            ]);

            $artikel->schichten()->create([
                'tenant_id' => $artikel->tenant_id,
                'eingang_bewegung_id' => $bewegung->id,
                'eingangsdatum' => $datum,
                'menge_eingang' => $menge,
                'menge_rest' => $menge,
                'einstandspreis' => $stueckpreis,
                'charge_nr' => $chargeNr,
                'mhd' => $mhd,
                'lieferant_id' => $lieferantId,
            ]);

            return $bewegung;
        });
    }
}
