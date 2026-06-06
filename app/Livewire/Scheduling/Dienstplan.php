<?php

namespace App\Livewire\Scheduling;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Actions\AssignShift;
use App\Domains\Scheduling\Compliance\ArbeitszeitgesetzDefaults;
use App\Domains\Scheduling\Compliance\ComplianceReporter;
use App\Domains\Scheduling\Compliance\WorkingHoursAnalyzer;
use App\Domains\Scheduling\Data\ShiftAssignmentData;
use App\Domains\Scheduling\Models\ComplianceJustification;
use App\Domains\Scheduling\Models\Dienstwunsch;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
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

        return view('livewire.scheduling.dienstplan', [
            'days' => $days,
            'weekLabel' => $start->isoFormat('DD.MM.').'–'.$start->addDays(6)->isoFormat('DD.MM.YYYY'),
            'users' => User::where('tenant_id', $tenantId)->with('employeeProfile')->orderBy('name')->get(),
            'shifts' => Shift::where('aktiv', true)->orderBy('beginn')->get(),
            'grid' => $grid,
            'wuensche' => $wuensche,
            'geplant' => $geplant,
            'findingsByUser' => $findingsByUser,
            'marks' => $marks,
            'offeneVerstoesse' => collect($findings)->filter(fn ($f) => $f->offenerVerstoss())->count(),
        ]);
    }
}
