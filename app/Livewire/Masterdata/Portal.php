<?php

namespace App\Livewire\Masterdata;

use App\Domains\Accounting\Models\Treuhandkonto;
use App\Domains\Masterdata\Enums\Aufgabenkreis;
use App\Domains\Masterdata\Enums\EreignisKategorie;
use App\Domains\Masterdata\Models\BewohnerEreignis;
use App\Domains\Masterdata\Models\Custodian;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Read-only-Portal für Vertretungen mit Nutzerkonto (Rollen betreuer/angehoeriger). Zeigt je zugeordnetem
 * Bewohner nur die Daten der eigenen Aufgabenkreise (§ 1815 BGB — Gating wie Befugnis), die berechtigten
 * Ereignisse (§ 1821 Informationsrecht) und die eigenen Pflichten (§ 1863 Bericht).
 */
#[Layout('layouts.app')]
class Portal extends Component
{
    public function render()
    {
        $vertretungen = Custodian::with(['resident.diagnoses.icdCode', 'resident.allergies', 'resident.ereignisse'])
            ->where('user_id', auth()->id())
            ->get();

        $items = $vertretungen->map(function (Custodian $v): array {
            $r = $v->resident;
            $hatGesundheit = $v->hatAufgabenkreis(Aufgabenkreis::Gesundheitssorge);
            $hatVermoegen = $v->hatAufgabenkreis(Aufgabenkreis::Vermoegenssorge);
            $hatPost = $v->hatAufgabenkreis(Aufgabenkreis::Postangelegenheiten);

            $konto = $hatVermoegen ? Treuhandkonto::where('resident_id', $r->id)->first() : null;

            return [
                'vertretung' => $v,
                'resident' => $r,
                'diagnosen' => $hatGesundheit ? $r->diagnoses : collect(),
                'allergien' => $hatGesundheit ? $r->allergies : collect(),
                'saldo' => $konto?->saldo(),
                'posteingang' => $hatPost
                    ? $r->ereignisse->filter(fn (BewohnerEreignis $e): bool => $e->kategorie === EreignisKategorie::Posteingang)->values()
                    : collect(),
                'ereignisse' => $r->ereignisse
                    ->filter(fn (BewohnerEreignis $e): bool => $v->darfEreignis($e->kategorie))->values(),
            ];
        });

        return view('livewire.masterdata.portal', [
            'items' => $items,
        ]);
    }
}
