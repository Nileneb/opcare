<?php

namespace App\Livewire\Scheduling;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Enums\WunschTyp;
use App\Domains\Scheduling\Models\Dienstwunsch;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Wunschdienstplan: Mitarbeitende hinterlegen ihre Dienstwünsche je Tag (Vorschlagscharakter). Die PDL
 * sieht diese Wünsche beim Erstellen direkt im Dienstplan-Grid.
 */
#[Layout('layouts.app')]
class Wunschdienstplan extends Component
{
    public string $weekStart = '';

    /** @var array<string, array{typ: string, notiz: string}> datum => Wunsch */
    public array $w = [];

    public function mount(): void
    {
        $this->weekStart = CarbonImmutable::parse(today())->startOfWeek()->toDateString();
        $this->ladeWuensche();
    }

    private function ladeWuensche(): void
    {
        $start = CarbonImmutable::parse($this->weekStart);
        $vorhanden = Dienstwunsch::where('user_id', auth()->id())
            ->whereBetween('datum', [$start->toDateString(), $start->addDays(6)->toDateString()])
            ->get()->keyBy(fn (Dienstwunsch $d) => $d->datum->toDateString());

        $this->w = [];
        foreach (range(0, 6) as $i) {
            $datum = $start->addDays($i)->toDateString();
            $treffer = $vorhanden->get($datum);
            $this->w[$datum] = $treffer
                ? ['typ' => $treffer->typ->value, 'notiz' => $treffer->notiz ?? '']
                : ['typ' => '', 'notiz' => ''];
        }
    }

    public function woche(int $delta): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->addWeeks($delta)->toDateString();
        $this->ladeWuensche();
    }

    public function speichern(): void
    {
        $erlaubt = array_map(fn ($t) => $t->value, WunschTyp::cases());
        foreach ($this->w as $datum => $row) {
            $typ = $row['typ'] ?? '';
            if ($typ === '' || ! in_array($typ, $erlaubt, true)) {
                Dienstwunsch::where('user_id', auth()->id())->whereDate('datum', $datum)->delete();

                continue;
            }
            Dienstwunsch::updateOrCreate(
                ['tenant_id' => app(CurrentTenant::class)->id(), 'user_id' => auth()->id(), 'datum' => $datum],
                ['typ' => $typ, 'notiz' => ($row['notiz'] ?? '') ?: null],
            );
        }
        session()->flash('status', 'Dienstwünsche gespeichert — die Planung sieht sie.');
    }

    public function render()
    {
        $start = CarbonImmutable::parse($this->weekStart);
        $days = collect(range(0, 6))->map(function (int $i) use ($start) {
            $d = $start->addDays($i);

            return ['datum' => $d->toDateString(), 'label' => $d->isoFormat('dddd, DD.MM.'), 'sonntag' => $d->isoWeekday() === 7];
        })->all();

        return view('livewire.scheduling.wunschdienstplan', [
            'days' => $days,
            'weekLabel' => $start->isoFormat('DD.MM.').'–'.$start->addDays(6)->isoFormat('DD.MM.YYYY'),
            'typen' => WunschTyp::cases(),
        ]);
    }
}
