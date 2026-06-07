<?php

namespace App\Domains\Voting\Services;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Models\GremiumMitglied;
use App\Domains\Voting\Enums\Abstimmungsart;
use App\Domains\Voting\Enums\AbstimmungStatus;
use App\Domains\Voting\Enums\Elektorat;
use App\Domains\Voting\Enums\Stimmodus;
use App\Domains\Voting\Models\Abstimmung;
use App\Domains\Voting\Models\AbstimmungOption;
use App\Domains\Voting\Models\Wahlteilnahme;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AbstimmungStarten
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function handle(array $daten, array $optionen, ?int $userId = null): Abstimmung
    {
        return DB::transaction(function () use ($daten, $optionen, $userId) {
            $this->pruefGeheimErzwingung($daten);

            $abstimmung = Abstimmung::create(array_merge([
                'tenant_id' => $this->currentTenant->id(),
                'status' => AbstimmungStatus::Entwurf,
                'erstellt_von' => $userId,
            ], $daten));

            foreach ($optionen as $index => $option) {
                AbstimmungOption::create([
                    'tenant_id' => $abstimmung->tenant_id,
                    'abstimmung_id' => $abstimmung->id,
                    'text' => is_array($option) ? $option['text'] : $option,
                    'sortierung' => is_array($option) ? ($option['sortierung'] ?? $index) : $index,
                ]);
            }

            if ($abstimmung->status === AbstimmungStatus::Offen) {
                $this->eroeffne($abstimmung);
            }

            return $abstimmung;
        });
    }

    public function eroeffne(Abstimmung $abstimmung): void
    {
        DB::transaction(function () use ($abstimmung) {
            // WHY: Datenminimierung — keine personenbezogene Wählerliste für gesperrte Wahlen anlegen.
            // Inbetriebnahme-Schalter-Regel (docs/INBETRIEBNAHME.md §6).
            if ($abstimmung->art === Abstimmungsart::Wahl && ! config('voting.online_wahl_aktiv')) {
                throw new InvalidArgumentException('Bindende Online-Wahl nicht freigegeben (Inbetriebnahme) — Eröffnung blockiert.');
            }

            if ($abstimmung->status !== AbstimmungStatus::Offen) {
                $abstimmung->update(['status' => AbstimmungStatus::Offen]);
            }

            $this->generiereWahlteilnahmen($abstimmung);
        });
    }

    private function pruefGeheimErzwingung(array $daten): void
    {
        $art = $daten['art'] ?? null;
        $elektorat = $daten['elektorat'] ?? null;
        $modus = $daten['modus'] ?? null;

        $artIstWahl = $art instanceof Abstimmungsart
            ? $art === Abstimmungsart::Wahl
            : $art === Abstimmungsart::Wahl->value;

        $elektoratIstWahlpflichtig = $elektorat instanceof Elektorat
            ? in_array($elektorat, [Elektorat::Bewohner, Elektorat::Mitarbeitende], true)
            : in_array($elektorat, [Elektorat::Bewohner->value, Elektorat::Mitarbeitende->value], true);

        $modusIstNichtGeheim = $modus instanceof Stimmodus
            ? $modus !== Stimmodus::Geheim
            : $modus !== Stimmodus::Geheim->value;

        // WHY: HeimmwV §5 (Heimmitwirkungsverordnung) + MVG-EKD §11: Heimbeirats- und
        // MAV-Wahlen müssen zwingend geheim durchgeführt werden.
        if ($artIstWahl && $elektoratIstWahlpflichtig && $modusIstNichtGeheim) {
            throw new InvalidArgumentException('Gesetzliche Wahl (Heimbeirat/MAV) muss geheim sein.');
        }
    }

    private function generiereWahlteilnahmen(Abstimmung $abstimmung): void
    {
        match ($abstimmung->elektorat) {
            Elektorat::Bewohner => $this->teilnahmenFuerBewohner($abstimmung),
            Elektorat::Mitarbeitende => $this->teilnahmenFuerMitarbeitende($abstimmung),
            Elektorat::Gremium => $this->teilnahmenFuerGremium($abstimmung),
        };
    }

    private function teilnahmenFuerBewohner(Abstimmung $abstimmung): void
    {
        $bewohner = Resident::where('tenant_id', $abstimmung->tenant_id)
            ->where('status', 'aktiv')
            ->get();

        foreach ($bewohner as $resident) {
            Wahlteilnahme::firstOrCreate(
                [
                    'abstimmung_id' => $abstimmung->id,
                    'resident_id' => $resident->id,
                    'user_id' => null,
                ],
                [
                    'tenant_id' => $abstimmung->tenant_id,
                    'hat_abgestimmt' => false,
                ]
            );
        }
    }

    private function teilnahmenFuerMitarbeitende(Abstimmung $abstimmung): void
    {
        $users = User::where('tenant_id', $abstimmung->tenant_id)->get();

        foreach ($users as $user) {
            Wahlteilnahme::firstOrCreate(
                [
                    'abstimmung_id' => $abstimmung->id,
                    'user_id' => $user->id,
                    'resident_id' => null,
                ],
                [
                    'tenant_id' => $abstimmung->tenant_id,
                    'hat_abgestimmt' => false,
                ]
            );
        }
    }

    private function teilnahmenFuerGremium(Abstimmung $abstimmung): void
    {
        $mitglieder = GremiumMitglied::where('tenant_id', $abstimmung->tenant_id)
            ->where('gremium_id', $abstimmung->gremium_id)
            ->get();

        foreach ($mitglieder as $mitglied) {
            Wahlteilnahme::firstOrCreate(
                [
                    'abstimmung_id' => $abstimmung->id,
                    'user_id' => $mitglied->user_id,
                    'resident_id' => $mitglied->resident_id,
                ],
                [
                    'tenant_id' => $abstimmung->tenant_id,
                    'hat_abgestimmt' => false,
                ]
            );
        }
    }
}
