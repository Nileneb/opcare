<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lagerbewegung;
use App\Domains\Accounting\Support\AccountingDefaults;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Warenverbrauch: zehrt FIFO die ältesten Eingangsschichten ab (§ 256 HGB), schreibt je angezehrter Schicht
 * einen unveränderlichen Schichtabgang und bucht die TATSÄCHLICHEN Schichtkosten auf das Aufwandskonto der
 * Abteilung (Soll Abteilungs-Aufwand an Haben Warenbestand). Reicht der Bestand nicht, wird eine Exception
 * geworfen — bewusst KEIN stilles Klemmen; Diskrepanzen korrigiert die Inventur.
 */
class Warenverbrauch
{
    public function __construct(private readonly Buchen $buchen) {}

    public function handle(Artikel $artikel, float $menge, string $datum, ?string $notiz = null): Lagerbewegung
    {
        return DB::transaction(function () use ($artikel, $menge, $datum, $notiz) {
            AccountingDefaults::ensureFor($artikel->tenant_id);

            $schichten = $artikel->schichten()->where('menge_rest', '>', 0)
                ->orderBy('eingangsdatum')->orderBy('id')->lockForUpdate()->get();
            $verfuegbar = (float) $schichten->sum(fn ($s) => (float) $s->menge_rest);
            if ($verfuegbar + 1e-9 < $menge) {
                throw new InvalidArgumentException(
                    'Verbrauch übersteigt den Bestand ('.number_format($verfuegbar, 2, ',', '.').' '.$artikel->einheit.').');
            }

            $bewegung = $artikel->bewegungen()->create([
                'typ' => 'verbrauch', 'menge' => $menge, 'datum' => $datum, 'notiz' => $notiz,
            ]);

            $offen = $menge;
            $kosten = 0.0;
            foreach ($schichten as $schicht) {
                if ($offen <= 1e-9) {
                    break;
                }
                $nimm = min($offen, (float) $schicht->menge_rest);
                $schicht->menge_rest = (float) $schicht->menge_rest - $nimm;
                $schicht->save();
                $bewegung->abgaenge()->create([
                    'tenant_id' => $artikel->tenant_id,
                    'schicht_id' => $schicht->id,
                    'menge' => $nimm,
                    'einstandspreis' => $schicht->einstandspreis,
                ]);
                $kosten += $nimm * (float) $schicht->einstandspreis;
                $offen -= $nimm;
            }

            $artikel->bestand = (float) $artikel->bestand - $menge;
            $artikel->save();

            $betrag = round($kosten, 2);
            if ($betrag > 0) {
                $buchung = $this->buchen->handle(
                    AccountingDefaults::konto($artikel->abteilung->aufwandKonto())->id,
                    AccountingDefaults::konto(AccountingDefaults::WARENBESTAND)->id,
                    $betrag, 'Verbrauch: '.$artikel->name.' ('.$artikel->abteilung->label().')', $datum,
                );
                $bewegung->update(['buchung_id' => $buchung->id]);
            }

            return $bewegung;
        });
    }
}
