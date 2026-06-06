<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lagerbewegung;
use App\Domains\Accounting\Support\AccountingDefaults;
use Illuminate\Support\Facades\DB;

/**
 * Warenverbrauch: mindert den Bestand und bucht den Verbrauch auf das Aufwandskonto der Abteilung des Artikels
 * (Soll Abteilungs-Aufwand an Haben Warenbestand) — so verknüpft sich die Warenwirtschaft mit der Buchhaltung.
 */
class Warenverbrauch
{
    public function __construct(private readonly Buchen $buchen) {}

    public function handle(Artikel $artikel, float $menge, string $datum, ?string $notiz = null): Lagerbewegung
    {
        return DB::transaction(function () use ($artikel, $menge, $datum, $notiz) {
            AccountingDefaults::ensureFor($artikel->tenant_id);

            $artikel->bestand = max(0, (float) $artikel->bestand - $menge);
            $artikel->save();

            $buchung = null;
            $betrag = round($menge * (float) ($artikel->einkaufspreis ?? 0), 2);
            if ($betrag > 0) {
                $buchung = $this->buchen->handle(
                    AccountingDefaults::konto($artikel->abteilung->aufwandKonto())->id,
                    AccountingDefaults::konto(AccountingDefaults::WARENBESTAND)->id,
                    $betrag, 'Verbrauch: '.$artikel->name.' ('.$artikel->abteilung->label().')', $datum,
                );
            }

            return $artikel->bewegungen()->create([
                'typ' => 'verbrauch', 'menge' => $menge, 'datum' => $datum, 'notiz' => $notiz, 'buchung_id' => $buchung?->id,
            ]);
        });
    }
}
