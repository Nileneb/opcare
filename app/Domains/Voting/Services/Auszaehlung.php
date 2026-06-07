<?php

namespace App\Domains\Voting\Services;

use App\Domains\Voting\Enums\Stimmodus;
use App\Domains\Voting\Models\Abstimmung;

class Auszaehlung
{
    public function ergebnis(Abstimmung $abstimmung): array
    {
        $optionen = $abstimmung->optionen()->withCount('stimmen')->get();

        $optionenErgebnis = [];

        foreach ($optionen as $option) {
            $optionenErgebnis[$option->id] = [
                'text' => $option->text,
                'stimmen' => $option->stimmen_count,
            ];
        }

        $berechtigt = $abstimmung->wahlteilnahmen()->count();
        $abgestimmt = $abstimmung->wahlteilnahmen()->where('hat_abgestimmt', true)->count();

        $namentlich = null;

        if ($abstimmung->modus === Stimmodus::Namentlich) {
            $namentlich = [];

            foreach ($optionen as $option) {
                $namen = [];

                $stimmen = $abstimmung->stimmen()
                    ->where('option_id', $option->id)
                    ->with(['waehlerUser', 'waehlerResident'])
                    ->get();

                foreach ($stimmen as $stimme) {
                    if ($stimme->waehlerUser !== null) {
                        $namen[] = $stimme->waehlerUser->name;
                    } elseif ($stimme->waehlerResident !== null) {
                        $namen[] = $stimme->waehlerResident->name;
                    }
                }

                $namentlich[$option->id] = $namen;
            }
        }

        return [
            'optionen' => $optionenErgebnis,
            'beteiligung' => [
                'berechtigt' => $berechtigt,
                'abgestimmt' => $abgestimmt,
            ],
            'namentlich' => $namentlich,
        ];
    }
}
