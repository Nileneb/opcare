<?php

namespace App\Domains\Voting\Services;

use App\Domains\Voting\Enums\Abstimmungsart;
use App\Domains\Voting\Enums\Stimmodus;
use App\Domains\Voting\Models\Abstimmung;
use App\Domains\Voting\Models\Stimme;
use App\Domains\Voting\Models\Wahlteilnahme;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StimmeAbgeben
{
    public function handle(Abstimmung $abstimmung, string $waehlerTyp, int $waehlerId, array $optionIds): string
    {
        return DB::transaction(function () use ($abstimmung, $waehlerTyp, $waehlerId, $optionIds) {
            if (! $abstimmung->offen()) {
                throw new InvalidArgumentException('Abstimmung nicht offen.');
            }

            // WHY: Online-Wahl-Sperre — bindende Wahlen benötigen Inbetriebnahme-Freigabe
            // (Inbetriebnahme-Schalter-Regel, docs/INBETRIEBNAHME.md).
            if ($abstimmung->art === Abstimmungsart::Wahl && ! config('voting.online_wahl_aktiv')) {
                throw new InvalidArgumentException('Online-Wahl nicht freigegeben (Inbetriebnahme).');
            }

            $teilnahme = $this->holeTeilnahme($abstimmung, $waehlerTyp, $waehlerId);

            if ($teilnahme === null) {
                throw new InvalidArgumentException('Wähler nicht stimmberechtigt.');
            }

            if ($teilnahme->hat_abgestimmt) {
                throw new InvalidArgumentException('Wähler hat bereits abgestimmt.');
            }

            $this->validiereOptionen($abstimmung, $optionIds);

            $teilnahme->update(['hat_abgestimmt' => true]);

            $belegToken = bin2hex(random_bytes(16));

            $waehlerUserId = null;
            $waehlerResidentId = null;

            // WHY: Personenbezug an der Stimme NUR bei namentlicher Abstimmung —
            // DSGVO ErwG 26, Anonymitätsprinzip bei geheimer Wahl.
            if ($abstimmung->modus === Stimmodus::Namentlich) {
                if ($waehlerTyp === 'user') {
                    $waehlerUserId = $waehlerId;
                } else {
                    $waehlerResidentId = $waehlerId;
                }
            }

            foreach ($optionIds as $optionId) {
                Stimme::create([
                    'tenant_id' => $abstimmung->tenant_id,
                    'abstimmung_id' => $abstimmung->id,
                    'option_id' => $optionId,
                    'beleg_token' => $belegToken,
                    'waehler_user_id' => $waehlerUserId,
                    'waehler_resident_id' => $waehlerResidentId,
                ]);
            }

            return $belegToken;
        });
    }

    private function holeTeilnahme(Abstimmung $abstimmung, string $waehlerTyp, int $waehlerId): ?Wahlteilnahme
    {
        $query = Wahlteilnahme::where('abstimmung_id', $abstimmung->id)->lockForUpdate();

        if ($waehlerTyp === 'user') {
            return $query->where('user_id', $waehlerId)->first();
        }

        return $query->where('resident_id', $waehlerId)->first();
    }

    private function validiereOptionen(Abstimmung $abstimmung, array $optionIds): void
    {
        if (! $abstimmung->mehrfachauswahl && count($optionIds) !== 1) {
            throw new InvalidArgumentException('Genau eine Option erwartet (kein Mehrfachauswahl).');
        }

        $gueltigeIds = $abstimmung->optionen()->pluck('id')->all();

        foreach ($optionIds as $id) {
            if (! in_array($id, $gueltigeIds, true)) {
                throw new InvalidArgumentException("Option {$id} gehört nicht zu dieser Abstimmung.");
            }
        }
    }
}
