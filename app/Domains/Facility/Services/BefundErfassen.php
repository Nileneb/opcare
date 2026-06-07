<?php

namespace App\Domains\Facility\Services;

use App\Domains\Facility\Models\Legionellenbefund;
use App\Domains\Facility\Models\Trinkwasseranlage;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BefundErfassen
{
    public function handle(
        Trinkwasseranlage $anlage,
        ?int $probenahmestelleId,
        string $untersuchtAm,
        int $kbe,
        ?string $labor = null,
    ): Legionellenbefund {
        return DB::transaction(function () use ($anlage, $probenahmestelleId, $untersuchtAm, $kbe, $labor) {
            $befund = Legionellenbefund::create([
                'tenant_id' => app(CurrentTenant::class)->id(),
                'trinkwasseranlage_id' => $anlage->id,
                'probenahmestelle_id' => $probenahmestelleId,
                'untersucht_am' => $untersuchtAm,
                'labor' => $labor,
                'kbe_pro_100ml' => $kbe,
                'ueberschreitung' => $kbe >= Legionellenbefund::MASSNAHMENWERT,
            ]);

            $untersuchungsDatum = Carbon::parse($untersuchtAm)->toDateString();
            $bisheriges = $anlage->letzte_untersuchung_am?->toDateString();

            if ($bisheriges === null || $untersuchungsDatum > $bisheriges) {
                $anlage->update(['letzte_untersuchung_am' => $untersuchungsDatum]);
            }

            return $befund;
        });
    }
}
