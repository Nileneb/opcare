<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Enums\InventurStatus;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Inventur;
use App\Domains\Accounting\Models\Inventurposition;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Accounting\Support\Lagerwert;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Schließt eine Inventur ab (§§ 240/241 HGB): bucht je gezählter Position die Zähldifferenz (Schwund FIFO ab
 * gegen Inventurdifferenz/Warenbestand, Mehrbestand als neue Schicht), gleicht den Bestand auf das Ist ab und
 * friert den Bestandswert ein. Nicht gezählte Positionen (ohne `ist_menge`) werden NICHT als 0-Differenz
 * gebucht, sondern transparent zurückgemeldet. Ein bereits abgeschlossener Bestand wird abgewiesen.
 */
class InventurAbschliessen
{
    public function __construct(
        private readonly Buchen $buchen,
        private readonly Lagerwert $lagerwert,
    ) {}

    /**
     * @return array{gebucht: int, nicht_gezaehlt: int}
     */
    public function handle(Inventur $inventur, ?int $userId): array
    {
        if (! $inventur->offen()) {
            throw new InvalidArgumentException('Inventur ist bereits abgeschlossen.');
        }
        AccountingDefaults::ensureFor($inventur->tenant_id);
        $stichtag = $inventur->stichtag->toDateString();

        return DB::transaction(function () use ($inventur, $userId, $stichtag) {
            $gebucht = 0;
            $nichtGezaehlt = 0;

            foreach ($inventur->positionen()->with('artikel')->get() as $pos) {
                if (! $pos->gezaehlt()) {
                    $nichtGezaehlt++;

                    continue; // nie still als 0 buchen
                }
                $diff = round($pos->differenzMenge(), 2);
                if (abs($diff) < 0.005) {
                    continue;
                }
                $artikel = $pos->artikel;
                if ($diff < 0) {
                    $this->bucheSchwund($pos, $artikel, abs($diff), $stichtag);
                } else {
                    $this->bucheMehrbestand($pos, $artikel, $diff, $stichtag);
                }
                $artikel->update(['bestand' => (float) $pos->ist_menge]);
                $gebucht++;
            }

            $inventur->update([
                'status' => InventurStatus::Abgeschlossen->value,
                'bestandswert_summe' => $this->lagerwert->bestandswertGesamt($inventur->tenant_id, $inventur->abteilung),
                'abgeschlossen_von' => $userId,
                'abgeschlossen_am' => now(),
            ]);

            return ['gebucht' => $gebucht, 'nicht_gezaehlt' => $nichtGezaehlt];
        });
    }

    private function bucheSchwund(Inventurposition $pos, Artikel $artikel, float $menge, string $stichtag): void
    {
        $schichten = $artikel->schichten()->where('menge_rest', '>', 0)
            ->orderBy('eingangsdatum')->orderBy('id')->lockForUpdate()->get();
        $bewegung = $artikel->bewegungen()->create([
            'typ' => 'inventur', 'menge' => $menge, 'datum' => $stichtag, 'notiz' => 'Inventur-Schwund',
        ]);
        $offen = $menge;
        $kosten = 0.0;
        foreach ($schichten as $schicht) {
            if ($offen <= 1e-9) {
                break;
            }
            $nimm = min($offen, (float) $schicht->menge_rest);
            $schicht->update(['menge_rest' => (float) $schicht->menge_rest - $nimm]);
            $bewegung->abgaenge()->create([
                'tenant_id' => $artikel->tenant_id, 'schicht_id' => $schicht->id,
                'menge' => $nimm, 'einstandspreis' => $schicht->einstandspreis,
            ]);
            $kosten += $nimm * (float) $schicht->einstandspreis;
            $offen -= $nimm;
        }
        $betrag = round($kosten, 2);
        if ($betrag > 0) {
            $buchung = $this->buchen->handle(
                AccountingDefaults::konto(AccountingDefaults::INVENTURDIFFERENZ)->id,
                AccountingDefaults::konto(AccountingDefaults::WARENBESTAND)->id,
                $betrag, 'Inventur-Schwund: '.$artikel->name, $stichtag, 'Inventur #'.$pos->inventur_id);
            $bewegung->update(['buchung_id' => $buchung->id]);
        }
    }

    private function bucheMehrbestand(Inventurposition $pos, Artikel $artikel, float $menge, string $stichtag): void
    {
        $preis = (float) $pos->einstandspreis_schnitt;
        $bewegung = $artikel->bewegungen()->create([
            'typ' => 'inventur', 'menge' => $menge, 'datum' => $stichtag, 'notiz' => 'Inventur-Mehrbestand',
        ]);
        $artikel->schichten()->create([
            'tenant_id' => $artikel->tenant_id, 'eingang_bewegung_id' => $bewegung->id,
            'eingangsdatum' => $stichtag, 'menge_eingang' => $menge, 'menge_rest' => $menge,
            'einstandspreis' => $preis,
        ]);
        $betrag = round($menge * $preis, 2);
        if ($betrag > 0) {
            $buchung = $this->buchen->handle(
                AccountingDefaults::konto(AccountingDefaults::WARENBESTAND)->id,
                AccountingDefaults::konto(AccountingDefaults::INVENTURDIFFERENZ)->id,
                $betrag, 'Inventur-Mehrbestand: '.$artikel->name, $stichtag, 'Inventur #'.$pos->inventur_id);
            $bewegung->update(['buchung_id' => $buchung->id]);
        }
    }
}
