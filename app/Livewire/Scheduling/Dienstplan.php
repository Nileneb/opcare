<?php

namespace App\Livewire\Scheduling;

use App\Domains\Arbeitsschutz\Data\BelastungsBefund;
use App\Domains\Arbeitsschutz\Models\Belastungsmeldung;
use App\Domains\Arbeitsschutz\Models\Gefaehrdungsbeurteilung;
use App\Domains\Arbeitsschutz\Services\BelastungMelden;
use App\Domains\Arbeitsschutz\Services\BelastungsAnalyzer;
use App\Domains\Arbeitsschutz\Services\EntlastungErgreifen;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Actions\AssignShift;
use App\Domains\Scheduling\Compliance\ArbeitszeitgesetzDefaults;
use App\Domains\Scheduling\Compliance\Betreuungsschluessel;
use App\Domains\Scheduling\Compliance\ComplianceReporter;
use App\Domains\Scheduling\Compliance\Data\QualityFinding;
use App\Domains\Scheduling\Compliance\Data\StaffingAnalysis;
use App\Domains\Scheduling\Compliance\ScheduleQualityAnalyzer;
use App\Domains\Scheduling\Compliance\ScheduleQualityDefaults;
use App\Domains\Scheduling\Compliance\WorkingHoursAnalyzer;
use App\Domains\Scheduling\Data\ShiftAssignmentData;
use App\Domains\Scheduling\Models\ComplianceJustification;
use App\Domains\Scheduling\Models\Dienstwunsch;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Domains\Scheduling\Support\DienstplanGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Wochen-Dienstplan: Mitarbeitende × 7 Tage. Zuweisung direkt in der Zelle; jede Woche wird live gegen die
 * editierbaren ArbZG-Regeln geprüft (WorkingHoursAnalyzer). Offene Verstöße lassen sich mit einer § 14-
 * Begründung dokumentieren (z. B. ausbleibende Nachfolgekraft) — der Verstoß bleibt, wird aber nachvollziehbar.
 */
#[Layout('layouts.app')]
class Dienstplan extends Component
{
    public string $weekStart = '';

    public ?int $pickUser = null;

    public ?string $pickDatum = null;

    public ?string $begruendeKey = null;

    public string $grund = '';

    // Entlasten-Dialog state
    public ?int $entlastenStation = null;

    public ?int $entlastenGbuId = null;

    public string $entlastenBeschreibung = '';

    public string $entlastenFrist = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        $this->weekStart = CarbonImmutable::parse(today())->startOfWeek()->toDateString();
    }

    public function woche(int $delta): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->addWeeks($delta)->toDateString();
        $this->resetInteraktion();
    }

    public function heute(): void
    {
        $this->weekStart = CarbonImmutable::parse(today())->startOfWeek()->toDateString();
        $this->resetInteraktion();
    }

    public function pick(int $userId, string $datum): void
    {
        $this->pickUser = $userId;
        $this->pickDatum = $datum;
    }

    public function zuweisen(int $shiftId, AssignShift $assign): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        $tenantId = app(CurrentTenant::class)->id();
        // WHY(tenant-scope): exists: prüft die Roh-Tabelle und umgeht den globalen TenantScope (IDOR-Write-Schutz).
        $data = validator(
            ['userId' => $this->pickUser, 'shiftId' => $shiftId, 'dienstAm' => $this->pickDatum],
            [
                'userId' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
                'shiftId' => ['required', Rule::exists('shifts', 'id')->where('tenant_id', $tenantId)],
                'dienstAm' => ['required', 'date'],
            ],
        )->validate();

        $assign->handle(new ShiftAssignmentData(
            user_id: $data['userId'], shift_id: $data['shiftId'], dienst_am: $data['dienstAm'],
        ));
        $this->resetInteraktion();
    }

    public function entfernen(int $id): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        ShiftAssignment::findOrFail($id)->delete();
    }

    /** Auto-Generator: füllt offene Slots der Woche als Vorschlag (manuelle Zuweisungen bleiben). */
    public function autoGenerieren(DienstplanGenerator $generator): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        $result = $generator->generate(app(CurrentTenant::class)->id(), $this->weekStart);
        $msg = "Vorschlag erstellt: {$result->erstellt} Dienste, Deckung {$result->deckung()} %.";
        if ($result->offeneSlots !== []) {
            $msg .= ' Unbesetzt: '.implode(', ', array_slice($result->offeneSlots, 0, 8)).(count($result->offeneSlots) > 8 ? ' …' : '');
        }
        session()->flash('status', $msg);
    }

    public function vorschlaegeFreigeben(): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        [$von, $bisExklusiv] = $this->wochenSpanne();
        $n = ShiftAssignment::where('dienst_am', '>=', $von)->where('dienst_am', '<', $bisExklusiv)->where('auto_generiert', true)->update(['auto_generiert' => false]);
        session()->flash('status', "{$n} Vorschläge freigegeben.");
    }

    public function vorschlaegeVerwerfen(): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        [$von, $bisExklusiv] = $this->wochenSpanne();
        $n = ShiftAssignment::where('dienst_am', '>=', $von)->where('dienst_am', '<', $bisExklusiv)->where('auto_generiert', true)->delete();
        session()->flash('status', "{$n} Vorschläge verworfen.");
    }

    /** @return array{0: string, 1: string} von (inkl.) und exklusive Obergrenze (nächster Montag). */
    private function wochenSpanne(): array
    {
        $start = CarbonImmutable::parse($this->weekStart);

        return [$start->toDateString(), $start->addDays(7)->toDateString()];
    }

    public function begruendeStart(string $ruleKey, int $userId, string $datum): void
    {
        $this->begruendeKey = $ruleKey.'|'.$userId.'|'.$datum;
        $this->grund = '';
    }

    public function begruendeSpeichern(): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        $this->validate(['grund' => ['required', 'string', 'min:5', 'max:500']]);
        [$ruleKey, $userId, $datum] = explode('|', (string) $this->begruendeKey);
        $tenantId = app(CurrentTenant::class)->id();

        ComplianceJustification::updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => (int) $userId, 'rule_key' => $ruleKey, 'datum' => $datum],
            ['grund' => $this->grund, 'begruendet_von' => auth()->id()],
        );
        $this->begruendeKey = null;
        $this->grund = '';
    }

    public function begruendeAbbrechen(): void
    {
        $this->begruendeKey = null;
        $this->grund = '';
    }

    private function resetInteraktion(): void
    {
        $this->pickUser = null;
        $this->pickDatum = null;
        $this->begruendeKey = null;
        $this->grund = '';
    }

    public function leitungMelden(?int $stationId): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);

        $tenantId = app(CurrentTenant::class)->id();
        $befund = $this->berechneBelastung($tenantId)->first(fn ($b) => $b->stationId === $stationId);

        if ($befund === null) {
            session()->flash('status', 'Kein Befund für diese Station gefunden.');

            return;
        }

        $result = app(BelastungMelden::class)->handle($befund);

        if ($result === null) {
            session()->flash('status', 'Meldung bereits vorhanden oder Stufe nicht meldepflichtig.');
        } else {
            session()->flash('status', 'Belastungsmeldung fuer "'.$befund->wohnbereich.'" angelegt.');
        }
    }

    public function entlasten(int $stationId): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);

        $this->entlastenStation = $stationId;
        $this->entlastenGbuId = null;
        $this->entlastenBeschreibung = 'Entlastungsmaßnahme aus Dienstplan-Belastungsindex';
        $this->entlastenFrist = '';
    }

    public function entlastenAbbrechen(): void
    {
        $this->entlastenStation = null;
        $this->entlastenGbuId = null;
        $this->entlastenBeschreibung = '';
        $this->entlastenFrist = '';
    }

    public function entlastenSpeichern(): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);

        $tenantId = app(CurrentTenant::class)->id();

        $data = $this->validate([
            'entlastenGbuId' => ['required', Rule::exists('gefaehrdungsbeurteilungen', 'id')->where('tenant_id', $tenantId)],
            'entlastenBeschreibung' => ['required', 'string', 'min:5', 'max:500'],
            'entlastenFrist' => ['nullable', 'date'],
        ]);

        // WHY(IDOR): GBU tenant-scoped prüfen, obwohl Rule::exists bereits filtert — defense in depth.
        $gbu = Gefaehrdungsbeurteilung::where('tenant_id', $tenantId)->findOrFail((int) $data['entlastenGbuId']);

        // Offene Meldung der Station laden oder neu anlegen
        $meldung = Belastungsmeldung::where('tenant_id', $tenantId)
            ->where('station_id', $this->entlastenStation)
            ->whereNull('quittiert_am')
            ->latest('gemeldet_am')
            ->first();

        if ($meldung === null) {
            $befund = $this->berechneBelastung($tenantId)->first(fn ($b) => $b->stationId === $this->entlastenStation);

            if ($befund === null || ! $befund->stufe->istMeldepflichtig()) {
                session()->flash('status', 'Keine offene Meldung und Stufe nicht meldepflichtig — Entlasten nicht möglich.');

                return;
            }

            $meldung = app(BelastungMelden::class)->handle($befund);
        }

        app(EntlastungErgreifen::class)->handle(
            $meldung,
            $gbu,
            $data['entlastenBeschreibung'],
            $data['entlastenFrist'] ?: null,
        );

        $this->entlastenAbbrechen();
        session()->flash('status', 'Entlastungsmaßnahme angelegt und Meldung verknüpft.');
    }

    /**
     * Belastungs-Befunde je Wohnbereich — EINE Quelle für die Anzeige (render) UND die Aktionen
     * (leitungMelden/entlastenSpeichern). WHY: sonst driften angezeigte und gemeldete Stufe auseinander.
     *
     * @return Collection<int, BelastungsBefund>
     */
    private function berechneBelastung(int $tenantId): Collection
    {
        return app(BelastungsAnalyzer::class)->analysiere(
            $tenantId,
            $this->berechneStaffing($tenantId),
            $this->berechneQualityFindings($tenantId),
        );
    }

    private function berechneStaffing(int $tenantId): StaffingAnalysis
    {
        $start = CarbonImmutable::parse($this->weekStart);
        $von = $start->toDateString();
        $bis = $start->addDays(6)->toDateString();

        $users = User::where('tenant_id', $tenantId)->with('employeeProfile')->get();
        $assignments = ShiftAssignment::with(['user', 'shift'])->whereBetween('dienst_am', [$von, $bis])->get();
        $geplant = [];
        foreach ($assignments as $a) {
            if ($a->shift) {
                $geplant[$a->user_id] = ($geplant[$a->user_id] ?? 0) + WorkingHoursAnalyzer::stunden($a->shift->beginn, $a->shift->ende);
            }
        }
        $fachkraftIds = $users->filter(fn (User $u) => $u->employeeProfile?->qualifikation?->istFachkraft() ?? false)->pluck('id')->all();
        $istGesamt = array_sum($geplant);
        $istFachkraft = collect($geplant)->filter(fn ($h, $uid) => in_array($uid, $fachkraftIds, true))->sum();

        return app(Betreuungsschluessel::class)->analysiere($tenantId, (float) $istGesamt, (float) $istFachkraft);
    }

    /** @return array<int, QualityFinding> */
    private function berechneQualityFindings(int $tenantId): array
    {
        $start = CarbonImmutable::parse($this->weekStart);
        $von = $start->toDateString();
        $bis = $start->addDays(6)->toDateString();
        $days = collect(range(0, 6))->map(fn (int $i) => $start->addDays($i)->toDateString())->all();

        $assignments = ShiftAssignment::with(['user', 'shift'])->whereBetween('dienst_am', [$von, $bis])->get();
        $qualityRules = ScheduleQualityDefaults::ensureFor($tenantId);

        return app(ScheduleQualityAnalyzer::class)->findings($assignments, $qualityRules, $days);
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $start = CarbonImmutable::parse($this->weekStart);
        $days = collect(range(0, 6))->map(function (int $i) use ($start) {
            $d = $start->addDays($i);

            return ['datum' => $d->toDateString(), 'kurz' => $d->isoFormat('dd'), 'tag' => $d->isoFormat('DD.MM.'),
                'sonntag' => $d->isoWeekday() === 7, 'heute' => $d->isSameDay(today())];
        })->all();
        $von = $start->toDateString();
        $bis = $start->addDays(6)->toDateString();

        $assignments = ShiftAssignment::with(['user', 'shift'])->whereBetween('dienst_am', [$von, $bis])->get();
        $grid = [];
        $geplant = [];
        foreach ($assignments as $a) {
            $grid[$a->user_id][Carbon::parse($a->dienst_am)->toDateString()][] = $a;
            if ($a->shift) {
                $geplant[$a->user_id] = ($geplant[$a->user_id] ?? 0) + WorkingHoursAnalyzer::stunden($a->shift->beginn, $a->shift->ende);
            }
        }

        $rules = ArbeitszeitgesetzDefaults::ensureFor($tenantId);
        $justifications = ComplianceJustification::with('begruender')->whereBetween('datum', [$von, $bis])->get();
        $findings = app(ComplianceReporter::class)->findings($assignments, $rules, $justifications);

        $findingsByUser = [];
        $marks = [];
        foreach ($findings as $f) {
            $findingsByUser[$f->userId][] = $f;
            if ($f->offenerVerstoss()) {
                foreach ($f->dates as $d) {
                    $marks[$f->userId][$d] = true;
                }
            }
        }

        // Wunschdienstplan: die Wünsche der Mitarbeitenden bei der Planung sichtbar machen (Vorschlag).
        $wuensche = [];
        foreach (Dienstwunsch::with('user')->whereBetween('datum', [$von, $bis])->get() as $dw) {
            $wuensche[$dw->user_id][$dw->datum->toDateString()] = $dw;
        }

        $users = User::where('tenant_id', $tenantId)->with('employeeProfile')->orderBy('name')->get();

        // Betreuungsschlüssel (§ 113c SGB XI): Soll aus dem Pflegegrad-Mix vs. geplante Ist-Wochenstunden.
        $fachkraftIds = $users->filter(fn (User $u) => $u->employeeProfile?->qualifikation?->istFachkraft() ?? false)->pluck('id')->all();
        $istGesamt = array_sum($geplant);
        $istFachkraft = collect($geplant)->filter(fn ($h, $uid) => in_array($uid, $fachkraftIds, true))->sum();
        $staffing = app(Betreuungsschluessel::class)->analysiere($tenantId, (float) $istGesamt, (float) $istFachkraft);

        // Ergonomische Schichtregeln (BAuA/BGHM) — Empfehlungen, der ArbZG-Hartprüfung nachgelagert.
        $qualityRules = ScheduleQualityDefaults::ensureFor($tenantId);
        $qualityFindings = app(ScheduleQualityAnalyzer::class)->findings($assignments, $qualityRules, array_column($days, 'datum'));
        $qualityByUser = [];
        foreach ($qualityFindings as $qf) {
            $qualityByUser[$qf->userId][] = $qf;
        }

        // Belastungs-Index aus der gemeinsamen Quelle — identisch zu leitungMelden/entlastenSpeichern.
        $belastung = $this->berechneBelastung($tenantId);
        $gbus = Gefaehrdungsbeurteilung::where('tenant_id', $tenantId)->orderBy('arbeitsbereich')->get();

        return view('livewire.scheduling.dienstplan', [
            'staffing' => $staffing,
            'qualityByUser' => $qualityByUser,
            'qualityCount' => count($qualityFindings),
            'vorschlaegeCount' => $assignments->where('auto_generiert', true)->count(),
            'days' => $days,
            'weekLabel' => $start->isoFormat('DD.MM.').'–'.$start->addDays(6)->isoFormat('DD.MM.YYYY'),
            'users' => $users,
            'shifts' => Shift::where('aktiv', true)->orderBy('beginn')->get(),
            'grid' => $grid,
            'wuensche' => $wuensche,
            'geplant' => $geplant,
            'findingsByUser' => $findingsByUser,
            'marks' => $marks,
            'offeneVerstoesse' => collect($findings)->filter(fn ($f) => $f->offenerVerstoss())->count(),
            'belastung' => $belastung,
            'gbus' => $gbus,
        ]);
    }
}
