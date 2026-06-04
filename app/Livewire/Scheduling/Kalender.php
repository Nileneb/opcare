<?php

namespace App\Livewire\Scheduling;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Actions\CreateCalendarEvent;
use App\Domains\Scheduling\Data\CalendarEventData;
use App\Domains\Scheduling\Data\RecurrenceData;
use App\Domains\Scheduling\Enums\CalendarEventType;
use App\Domains\Scheduling\Enums\RecurrenceFreq;
use App\Domains\Scheduling\Models\CalendarEvent;
use App\Domains\Scheduling\Support\RecurrenceExpander;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Kalender extends Component
{
    public string $monat = '';

    public string $type = 'arzttermin';

    public string $titel = '';

    public string $beginntAm = '';

    public ?string $endetAm = null;

    public ?int $residentId = null;

    public ?string $wiederholung = null; // null|daily|weekly|monthly

    public ?array $byday = null;

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
        $this->monat = now()->format('Y-m');
    }

    public function speichern(CreateCalendarEvent $create): void
    {
        $this->authorize('create', CalendarEvent::class);
        $data = $this->validate([
            'type' => ['required', 'in:'.implode(',', array_column(CalendarEventType::cases(), 'value'))],
            'titel' => ['required', 'string', 'max:255'],
            'beginntAm' => ['required', 'date'],
            'endetAm' => ['nullable', 'date', 'after_or_equal:beginntAm'],
            // WHY(tenant-scope): exists: umgeht den globalen TenantScope — fremde Bewohner dürfen
            // nicht an einen Termin gebunden werden.
            'residentId' => ['nullable', Rule::exists('residents', 'id')->where('tenant_id', app(CurrentTenant::class)->id())],
            'wiederholung' => ['nullable', 'in:daily,weekly,monthly'],
        ]);

        $recurrence = $data['wiederholung']
            ? new RecurrenceData(freq: RecurrenceFreq::from($data['wiederholung']), byday: $this->byday)
            : null;

        $create->handle(new CalendarEventData(
            type: CalendarEventType::from($data['type']),
            titel: $data['titel'],
            beginnt_am: Carbon::parse($data['beginntAm'])->toDateTimeString(),
            created_by: auth()->id(),
            resident_id: $data['residentId'] ?? null,
            endet_am: $data['endetAm'] ?? null,
            recurrence: $recurrence,
        ));

        $this->reset('titel', 'beginntAm', 'endetAm', 'wiederholung', 'byday');
        session()->flash('status', 'Termin gespeichert.');
    }

    /** @return array<int, array{titel:string, type:CalendarEventType, zeitpunkt:Carbon, resident_id:?int, event_id:int}> */
    public function vorkommen(): array
    {
        $von = Carbon::parse($this->monat.'-01')->startOfMonth();
        $bis = $von->copy()->endOfMonth();
        $expander = new RecurrenceExpander;

        $events = CalendarEvent::with('recurrenceRule')
            ->whereNull('abgesagt_am')
            ->where(function ($q) use ($bis) {
                $q->whereNotNull('recurrence_rule_id')->orWhere('beginnt_am', '<=', $bis);
            })
            ->get();

        $out = [];
        foreach ($events as $e) {
            if ($e->istWiederkehrend() && $e->recurrenceRule) {
                $rule = $e->recurrenceRule->only(['freq', 'intervall', 'byday', 'until', 'count']);
                foreach ($expander->expand($e->beginnt_am, $rule, $von->toDateString(), $bis->toDateString()) as $occ) {
                    $out[] = $this->row($e, $occ);
                }
            } elseif ($e->beginnt_am->betweenIncluded($von, $bis)) {
                $out[] = $this->row($e, $e->beginnt_am);
            }
        }

        usort($out, fn ($a, $b) => $a['zeitpunkt'] <=> $b['zeitpunkt']);

        return $out;
    }

    private function row(CalendarEvent $e, Carbon $zeitpunkt): array
    {
        return [
            'event_id' => $e->id,
            'titel' => $e->titel,
            'type' => $e->type,
            'resident_id' => $e->resident_id,
            'zeitpunkt' => $zeitpunkt,
        ];
    }

    public function render()
    {
        return view('livewire.scheduling.kalender', [
            'vorkommen' => $this->vorkommen(),
            'typen' => CalendarEventType::cases(),
        ]);
    }
}
