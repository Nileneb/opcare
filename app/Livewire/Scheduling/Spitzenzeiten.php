<?php

namespace App\Livewire\Scheduling;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Actions\CreateShift;
use App\Domains\Scheduling\Compliance\SpitzenzeitAnalyzer;
use App\Domains\Scheduling\Compliance\SpitzenzeitDefaults;
use App\Domains\Scheduling\Data\ShiftData;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\Spitzenzeit;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Spitzenzeiten & Spitzendienste: editierbarer Katalog der tageszeitlichen Bedarfs-Fenster (Mahlzeiten/Grundpflege)
 * mit Soll-Personenzahl, dazu kurze Spitzendienst-Schichten als Stammdaten. Die Wochen-Deckungsmatrix stellt je
 * Fenster × Tag die anwesenden Mitarbeitenden dem Soll gegenüber und schlägt zusätzliche Dienste bei Unterdeckung vor.
 */
#[Layout('layouts.app')]
class Spitzenzeiten extends Component
{
    public string $weekStart = '';

    /** @var array<int, array{name: string, beginn: string, ende: string, soll_personen: int, nur_werktags: bool, aktiv: bool}> */
    public array $edits = [];

    public string $neu_name = '';

    public string $neu_beginn = '12:00';

    public string $neu_ende = '13:00';

    public int $neu_soll = 2;

    public bool $neu_werktags = false;

    public string $sd_name = 'Spitzendienst Mittag';

    public string $sd_beginn = '11:30';

    public string $sd_ende = '14:00';

    public function mount(): void
    {
        abort_unless($this->darf(), 403);
        $this->weekStart = CarbonImmutable::parse(today())->startOfWeek()->toDateString();
        $this->ladeEdits();
    }

    private function darf(): bool
    {
        return auth()->user()?->can('manage', Shift::class) ?? false;
    }

    private function ladeEdits(): void
    {
        $this->edits = [];
        foreach (SpitzenzeitDefaults::ensureFor(app(CurrentTenant::class)->id()) as $f) {
            $this->edits[$f->id] = [
                'name' => $f->name, 'beginn' => $f->beginn, 'ende' => $f->ende,
                'soll_personen' => $f->soll_personen, 'nur_werktags' => $f->nur_werktags, 'aktiv' => $f->aktiv,
            ];
        }
    }

    public function woche(int $delta): void
    {
        $this->weekStart = CarbonImmutable::parse($this->weekStart)->addWeeks($delta)->toDateString();
    }

    public function speichern(int $id): void
    {
        abort_unless($this->darf(), 403);
        $f = Spitzenzeit::findOrFail($id);
        $this->validate([
            "edits.$id.name" => ['required', 'string', 'max:80'],
            "edits.$id.beginn" => ['required', 'date_format:H:i'],
            "edits.$id.ende" => ['required', 'date_format:H:i'],
            "edits.$id.soll_personen" => ['required', 'integer', 'min:1', 'max:50'],
        ]);
        $e = $this->edits[$id];
        $f->update([
            'name' => $e['name'], 'beginn' => $e['beginn'], 'ende' => $e['ende'],
            'soll_personen' => (int) $e['soll_personen'], 'nur_werktags' => (bool) $e['nur_werktags'], 'aktiv' => (bool) $e['aktiv'],
        ]);
        session()->flash('status', $f->name.' gespeichert.');
    }

    public function loeschen(int $id): void
    {
        abort_unless($this->darf(), 403);
        Spitzenzeit::findOrFail($id)->delete();
        $this->ladeEdits();
        session()->flash('status', 'Bedarfs-Fenster entfernt.');
    }

    public function anlegen(): void
    {
        abort_unless($this->darf(), 403);
        $data = $this->validate([
            'neu_name' => ['required', 'string', 'max:80'],
            'neu_beginn' => ['required', 'date_format:H:i'],
            'neu_ende' => ['required', 'date_format:H:i'],
            'neu_soll' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
        Spitzenzeit::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'name' => $data['neu_name'], 'beginn' => $data['neu_beginn'], 'ende' => $data['neu_ende'],
            'soll_personen' => (int) $data['neu_soll'], 'nur_werktags' => $this->neu_werktags, 'aktiv' => true,
        ]);
        $this->reset('neu_name', 'neu_werktags');
        $this->ladeEdits();
        session()->flash('status', 'Bedarfs-Fenster angelegt.');
    }

    public function spitzendienstAnlegen(CreateShift $action): void
    {
        abort_unless($this->darf(), 403);
        $data = $this->validate([
            'sd_name' => ['required', 'string', 'max:80'],
            'sd_beginn' => ['required', 'date_format:H:i'],
            'sd_ende' => ['required', 'date_format:H:i'],
        ]);
        $action->handle(new ShiftData(
            name: $data['sd_name'], kind: ShiftKind::Spitzendienst, beginn: $data['sd_beginn'], ende: $data['sd_ende'],
        ));
        session()->flash('status', 'Spitzendienst „'.$data['sd_name'].'" angelegt — im Dienstplan zuweisbar.');
    }

    public function render()
    {
        $start = CarbonImmutable::parse($this->weekStart);
        $analyse = app(SpitzenzeitAnalyzer::class)->analysiere(app(CurrentTenant::class)->id(), $this->weekStart);

        return view('livewire.scheduling.spitzenzeiten', [
            'analyse' => $analyse,
            'weekLabel' => $start->isoFormat('DD.MM.').'–'.$start->addDays(6)->isoFormat('DD.MM.YYYY'),
            'spitzendienste' => Shift::where('kind', ShiftKind::Spitzendienst)->orderBy('beginn')->get(),
        ]);
    }
}
