<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Enums\BestellStatus;
use App\Domains\Accounting\Models\Bestellposition;
use App\Domains\Accounting\Models\Lagerbewegung;
use App\Domains\Accounting\Models\Lagerschicht;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BestellungWareneingang
{
    public function __construct(private readonly Wareneingang $wareneingang) {}

    public function handle(Bestellposition $pos, float $menge, ?float $preis, string $datum, ?string $chargeNr = null, ?string $mhd = null): Lagerbewegung
    {
        return DB::transaction(function () use ($pos, $menge, $preis, $datum, $chargeNr, $mhd) {
            $rest = (float) $pos->menge_bestellt - (float) $pos->menge_geliefert;
            if ($menge > $rest + 1e-9) {
                throw new InvalidArgumentException(
                    "Liefermenge ({$menge}) übersteigt die offene Bestellmenge ({$rest})."
                );
            }

            $bestellung = $pos->bestellung;
            $verwendetPreis = $preis ?? (float) $pos->einzelpreis;

            $bewegung = $this->wareneingang->handle(
                $pos->artikel,
                $menge,
                $verwendetPreis > 0 ? $verwendetPreis : null,
                $datum,
                'Bestellung #'.$pos->bestellung_id,
                $chargeNr,
                $mhd,
                $bestellung->lieferant_id,
            );

            Lagerschicht::where('eingang_bewegung_id', $bewegung->id)
                ->update(['bestellposition_id' => $pos->id]);

            $pos->menge_geliefert = (float) $pos->menge_geliefert + $menge;
            $pos->save();

            $bestellung->refresh();
            $frischePositionen = $bestellung->positionen()->get();

            if ($frischePositionen->every(fn (Bestellposition $p) => ! $p->offen())) {
                $bestellung->status = BestellStatus::Geliefert;
            } elseif ($frischePositionen->some(fn (Bestellposition $p) => (float) $p->menge_geliefert > 0)) {
                $bestellung->status = BestellStatus::TeilweiseGeliefert;
            }
            $bestellung->save();

            return $bewegung;
        });
    }
}
