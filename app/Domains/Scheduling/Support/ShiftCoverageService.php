<?php

namespace App\Domains\Scheduling\Support;

use App\Domains\Identity\Models\User;
use App\Domains\Scheduling\Compliance\WorkingHoursAnalyzer;
use App\Domains\Scheduling\Enums\AbwesenheitTyp;
use App\Domains\Scheduling\Models\Abwesenheit;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Domains\Scheduling\Models\ShiftSwapRequest;
use App\Domains\Scheduling\Notifications\VertretungBenoetigt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Abwesenheiten (Krankmeldung/Urlaub) und Dienst-Tausch/Vertretung. Eine Krankmeldung öffnet die betroffenen
 * Dienste automatisch als Vertretungs-Anfrage; eine Übernahme reassignt den Dienst nach harter ArbZG-Prüfung.
 */
class ShiftCoverageService
{
    public function krankmelden(User $user, AbwesenheitTyp $typ, string $von, string $bis, ?string $notiz, ?int $gemeldetVon): Abwesenheit
    {
        [$abwesenheit, $geoeffnet] = DB::transaction(function () use ($user, $typ, $von, $bis, $notiz, $gemeldetVon) {
            $abwesenheit = Abwesenheit::create([
                'tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'typ' => $typ->value,
                'von' => $von, 'bis' => $bis, 'notiz' => $notiz, 'gemeldet_von' => $gemeldetVon ?? auth()->id(),
            ]);

            // Betroffene (künftige) Dienste als Vertretung öffnen — exklusive Obergrenze wegen Datetime-Spalte.
            $bisExklusiv = CarbonImmutable::parse($bis)->addDay()->toDateString();
            $assignments = ShiftAssignment::where('user_id', $user->id)
                ->where('dienst_am', '>=', $von)->where('dienst_am', '<', $bisExklusiv)->get();
            $geoeffnet = 0;
            foreach ($assignments as $a) {
                $offen = ShiftSwapRequest::where('shift_assignment_id', $a->id)->where('status', 'offen')->exists();
                if (! $offen) {
                    ShiftSwapRequest::create([
                        'tenant_id' => $a->tenant_id, 'shift_assignment_id' => $a->id, 'requested_by' => $user->id,
                        'typ' => 'krankheit', 'status' => 'offen', 'grund' => $typ->label(),
                    ]);
                    $geoeffnet++;
                }
            }

            return [$abwesenheit, $geoeffnet];
        });

        // Push an die Leitung: nur bei Krankheit (nicht Urlaub) und wenn Dienste zu vertreten sind.
        if ($typ === AbwesenheitTyp::Krank && $geoeffnet > 0) {
            $zeitraum = CarbonImmutable::parse($von)->format('d.m.').'–'.CarbonImmutable::parse($bis)->format('d.m.Y');
            $leitung = User::where('tenant_id', $user->tenant_id)->where('id', '!=', $user->id)->get()
                ->filter(fn (User $u) => $u->hasAnyRole(['admin', 'pflegefachkraft']));
            Notification::send($leitung, new VertretungBenoetigt($user->name, $zeitraum, $geoeffnet));
        }

        return $abwesenheit;
    }

    public function tauschAnbieten(ShiftAssignment $assignment, ?string $grund): ShiftSwapRequest
    {
        $offen = ShiftSwapRequest::where('shift_assignment_id', $assignment->id)->where('status', 'offen')->first();
        if ($offen !== null) {
            return $offen;
        }

        return ShiftSwapRequest::create([
            'tenant_id' => $assignment->tenant_id, 'shift_assignment_id' => $assignment->id,
            'requested_by' => $assignment->user_id, 'typ' => 'tausch', 'status' => 'offen', 'grund' => $grund,
        ]);
    }

    public function uebernehmen(ShiftSwapRequest $request, User $uebernehmer): void
    {
        $grund = $this->uebernahmeHindernis($request, $uebernehmer);
        if ($grund !== null) {
            throw new InvalidArgumentException($grund);
        }

        $assignment = $request->assignment;
        DB::transaction(function () use ($request, $assignment, $uebernehmer) {
            $assignment->update(['user_id' => $uebernehmer->id]);
            $request->update(['status' => 'uebernommen', 'uebernommen_von' => $uebernehmer->id]);
        });
    }

    /**
     * Prüft, ob die übernehmende Person den Dienst übernehmen darf. Gibt den blockierenden Grund zurück
     * (null = darf). Wird sowohl von der Übernahme als auch von der UI (Button-Status) genutzt.
     */
    public function uebernahmeHindernis(ShiftSwapRequest $request, User $uebernehmer): ?string
    {
        if (! $request->offen()) {
            return 'Anfrage ist nicht mehr offen.';
        }
        $assignment = $request->assignment;
        if ($assignment->user_id === $uebernehmer->id) {
            return 'Eigener Dienst.';
        }

        // Qualifikations-Matching: eine Fachkraft-Vertretung erfordert eine Fachkraft (erhält die Fachkraftquote).
        $originalFachkraft = $request->anfrager->employeeProfile?->qualifikation?->istFachkraft() ?? false;
        $uebernehmerFachkraft = $uebernehmer->employeeProfile?->qualifikation?->istFachkraft() ?? false;
        if ($originalFachkraft && ! $uebernehmerFachkraft) {
            return 'Diese Vertretung erfordert eine Fachkraft.';
        }

        $datum = $assignment->dienst_am->toDateString();
        $sameDay = ShiftAssignment::where('user_id', $uebernehmer->id)->whereDate('dienst_am', $datum)
            ->where('id', '!=', $assignment->id)->exists();
        if ($sameDay) {
            return 'An diesem Tag bereits eingeteilt.';
        }

        // § 3 ArbZG Wochenhöchstarbeitszeit (48 h) der übernehmenden Person.
        [$wVon, $wBisExkl] = $this->woche($datum);
        $wochenStunden = ShiftAssignment::with('shift')->where('user_id', $uebernehmer->id)
            ->where('dienst_am', '>=', $wVon)->where('dienst_am', '<', $wBisExkl)->get()
            ->sum(fn ($a) => $a->shift ? WorkingHoursAnalyzer::stunden($a->shift->beginn, $a->shift->ende) : 0);
        $neu = $assignment->shift ? WorkingHoursAnalyzer::stunden($assignment->shift->beginn, $assignment->shift->ende) : 0;
        if ($wochenStunden + $neu > 48) {
            return 'Würde die Wochenhöchstarbeitszeit (48 h) überschreiten.';
        }

        return null;
    }

    public function zurueckziehen(ShiftSwapRequest $request): void
    {
        if ($request->offen()) {
            $request->update(['status' => 'zurueckgezogen']);
        }
    }

    /** @return array{0: string, 1: string} */
    private function woche(string $datum): array
    {
        $start = CarbonImmutable::parse($datum)->startOfWeek();

        return [$start->toDateString(), $start->addDays(7)->toDateString()];
    }
}
