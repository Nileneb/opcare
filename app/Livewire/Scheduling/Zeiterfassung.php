<?php

namespace App\Livewire\Scheduling;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Compliance\Enums\Pausenstatus;
use App\Domains\Scheduling\Compliance\WorkingHoursAnalyzer;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Domains\Scheduling\Models\Zeitbuchung;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Arbeitszeit-Ist-Erfassung (BAG/EuGH-Erfassungspflicht): Mitarbeitende stempeln Kommen/Gehen, korrigieren
 * ihre eigenen Buchungen und sehen Ist vs. geplantes Dienstplan-Soll je Woche. Die Leitung sieht alle.
 */
#[Layout('layouts.app')]
class Zeiterfassung extends Component
{
    public string $weekStart = '';

    public int $g_pause = 30;

    public string $m_datum = '';

    public string $m_beginn = '08:00';

    public string $m_ende = '16:00';

    public int $m_pause = 30;

    public function mount(): void
    {
        $this->weekStart = CarbonImmutable::parse(today())->startOfWeek()->toDateString();
        $this->m_datum = today()->toDateString();
    }

    private function darfAlle(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function woche(int $delta): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->addWeeks($delta)->toDateString();
    }

    public function kommen(): void
    {
        $laufend = Zeitbuchung::where('user_id', auth()->id())->whereNull('ende')->exists();
        abort_if($laufend, 422);
        Zeitbuchung::create([
            'user_id' => auth()->id(), 'datum' => today()->toDateString(),
            'beginn' => now()->format('H:i'),
        ]);
    }

    public function gehen(): void
    {
        $b = Zeitbuchung::where('user_id', auth()->id())->whereNull('ende')->latest()->first();
        if ($b !== null) {
            $b->update(['ende' => now()->format('H:i'), 'pause_minuten' => max(0, $this->g_pause)]);
        }
    }

    public function manuellAnlegen(): void
    {
        $data = $this->validate([
            'm_datum' => ['required', 'date'],
            'm_beginn' => ['required', 'date_format:H:i'],
            'm_ende' => ['required', 'date_format:H:i'],
            'm_pause' => ['integer', 'min:0', 'max:480'],
        ]);
        Zeitbuchung::create([
            'user_id' => auth()->id(), 'datum' => $data['m_datum'],
            'beginn' => $data['m_beginn'], 'ende' => $data['m_ende'], 'pause_minuten' => $data['m_pause'],
        ]);
        session()->flash('status', 'Zeit erfasst.');
    }

    public function entfernen(int $id): void
    {
        $b = Zeitbuchung::findOrFail($id);
        abort_unless($b->user_id === auth()->id() || $this->darfAlle(), 403);
        $b->delete();
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $start = CarbonImmutable::parse($this->weekStart);
        $von = $start->toDateString();
        $bis = $start->addDays(6)->toDateString();

        // whereDate statt whereBetween: die als date gecastete Spalte wird als '…00:00:00' gespeichert —
        // ein reiner Bis-String würde den letzten Wochentag ausschließen (Bug: Wochenansicht sonntags leer).
        $eigene = Zeitbuchung::where('user_id', auth()->id())
            ->whereDate('datum', '>=', $von)->whereDate('datum', '<=', $bis)->orderBy('datum')->orderBy('beginn')->get();
        $laufend = Zeitbuchung::where('user_id', auth()->id())->whereNull('ende')->latest()->first();

        // Soll je User aus dem Dienstplan (geplante Schichtstunden) für die Woche.
        $soll = [];
        foreach (ShiftAssignment::with('shift')->whereDate('dienst_am', '>=', $von)->whereDate('dienst_am', '<=', $bis)->get() as $a) {
            if ($a->shift) {
                $soll[$a->user_id] = ($soll[$a->user_id] ?? 0) + WorkingHoursAnalyzer::stunden($a->shift->beginn, $a->shift->ende);
            }
        }
        $istEigene = round($eigene->sum(fn (Zeitbuchung $b) => $b->istStunden() ?? 0), 2);

        $alleUebersicht = [];
        if ($this->darfAlle()) {
            $istProUser = Zeitbuchung::whereDate('datum', '>=', $von)->whereDate('datum', '<=', $bis)->whereNotNull('ende')->get()
                ->groupBy('user_id')->map(fn ($g) => round($g->sum(fn (Zeitbuchung $b) => $b->istStunden() ?? 0), 2));
            foreach (User::where('tenant_id', $tenantId)->orderBy('name')->get() as $u) {
                $ist = $istProUser[$u->id] ?? 0;
                $s = $soll[$u->id] ?? 0;
                if ($ist > 0 || $s > 0) {
                    $alleUebersicht[] = ['name' => $u->name, 'ist' => $ist, 'soll' => round($s, 1)];
                }
            }
        }

        // § 4 ArbZG ist auf der Ist-Zeit prüfbar (die Pause ist erfasst): zu kurze Pausen je Woche zählen.
        $pausenVerstoesse = $eigene->filter(fn (Zeitbuchung $b) => $b->pausenStatus() === Pausenstatus::Unzureichend)->count();

        return view('livewire.scheduling.zeiterfassung', [
            'eigene' => $eigene,
            'laufend' => $laufend,
            'istEigene' => $istEigene,
            'sollEigene' => round($soll[auth()->id()] ?? 0, 1),
            'pausenVerstoesse' => $pausenVerstoesse,
            'weekLabel' => $start->isoFormat('DD.MM.').'–'.$start->addDays(6)->isoFormat('DD.MM.YYYY'),
            'darfAlle' => $this->darfAlle(),
            'alleUebersicht' => $alleUebersicht,
        ]);
    }
}
