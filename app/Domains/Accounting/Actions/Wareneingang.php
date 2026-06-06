<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lagerbewegung;
use App\Domains\Accounting\Support\AccountingDefaults;
use Illuminate\Support\Facades\DB;

/**
 * Wareneingang: erhöht den Bestand und bucht den Einkauf (Soll Warenbestand an Haben Verbindlichkeiten).
 */
class Wareneingang
{
    public function __construct(private readonly Buchen $buchen) {}

    public function handle(Artikel $artikel, float $menge, ?float $preis, string $datum, ?string $notiz = null): Lagerbewegung
    {
        return DB::transaction(function () use ($artikel, $menge, $preis, $datum, $notiz) {
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
                    AccountingDefaults::konto(AccountingDefaults::VERBINDLICHKEITEN)->id,
                    $betrag, 'Wareneingang: '.$artikel->name, $datum,
                );
            }

            return $artikel->bewegungen()->create([
                'typ' => 'eingang', 'menge' => $menge, 'datum' => $datum, 'notiz' => $notiz, 'buchung_id' => $buchung?->id,
            ]);
        });
    }
}
