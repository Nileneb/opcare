<?php

namespace App\Livewire\Quality;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Quality\Enums\QmBereich;
use App\Domains\Quality\Enums\QmStatus;
use App\Domains\Quality\Models\QmRequirement;
use App\Domains\Quality\Support\QmKatalogDefaults;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * QM-Norm-Checkliste: norm-verankerte Anforderungen nach Bereich gruppiert, je Anforderung Status/Nachweis/
 * Zuständig/Fälligkeit pflegbar; Erfüllungsgrad je Bereich + gesamt. Eigene Anforderungen ergänzbar.
 */
#[Layout('layouts.app')]
class QmCheckliste extends Component
{
    /** @var array<int, array{status:string, nachweis:?string, zustaendig:?string, faellig_am:?string}> */
    public array $edits = [];

    public string $neu_bereich = '';

    public string $neu_norm = '';

    public string $neu_anforderung = '';

    public function mount(): void
    {
        abort_unless($this->darfPflegen(), 403);
        $this->ladeEdits();
    }

    private function darfPflegen(): bool
    {
        return (bool) auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']);
    }

    private function ladeEdits(): void
    {
        $this->edits = [];
        foreach (QmKatalogDefaults::ensureFor(app(CurrentTenant::class)->id()) as $r) {
            $this->edits[$r->id] = [
                'status' => $r->status->value,
                'nachweis' => $r->nachweis,
                'zustaendig' => $r->zustaendig,
                'faellig_am' => $r->faellig_am?->toDateString(),
            ];
        }
    }

    public function speichern(int $id): void
    {
        abort_unless($this->darfPflegen(), 403);
        $rule = QmRequirement::findOrFail($id);
        $e = $this->edits[$id];
        $this->validate([
            "edits.$id.status" => ['required', 'in:'.implode(',', array_map(fn ($s) => $s->value, QmStatus::cases()))],
            "edits.$id.nachweis" => ['nullable', 'string', 'max:1000'],
            "edits.$id.zustaendig" => ['nullable', 'string', 'max:120'],
            "edits.$id.faellig_am" => ['nullable', 'date'],
        ]);

        $rule->update([
            'status' => $e['status'],
            'nachweis' => $e['nachweis'] ?: null,
            'zustaendig' => $e['zustaendig'] ?: null,
            'faellig_am' => $e['faellig_am'] ?: null,
            'geprueft_am' => $e['status'] === QmStatus::Erfuellt->value ? now()->toDateString() : null,
        ]);
        session()->flash('status', 'Anforderung gespeichert.');
    }

    public function anlegen(): void
    {
        abort_unless($this->darfPflegen(), 403);
        $data = $this->validate([
            'neu_bereich' => ['required', 'in:'.implode(',', array_map(fn ($b) => $b->value, QmBereich::cases()))],
            'neu_norm' => ['required', 'string', 'max:120'],
            'neu_anforderung' => ['required', 'string', 'max:500'],
        ]);

        QmRequirement::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'bereich' => $data['neu_bereich'], 'norm' => $data['neu_norm'], 'anforderung' => $data['neu_anforderung'],
        ]);
        $this->reset('neu_norm', 'neu_anforderung');
        $this->ladeEdits();
        session()->flash('status', 'Eigene Anforderung ergänzt.');
    }

    public function entfernen(int $id): void
    {
        abort_unless($this->darfPflegen(), 403);
        $rule = QmRequirement::findOrFail($id);
        abort_unless($rule->schluessel === null, 403); // nur eigene Anforderungen löschbar
        $rule->delete();
        $this->ladeEdits();
        session()->flash('status', 'Anforderung entfernt.');
    }

    public function render()
    {
        $requirements = QmRequirement::where('tenant_id', app(CurrentTenant::class)->id())->orderBy('id')->get();
        $gruppen = [];
        foreach (QmBereich::cases() as $bereich) {
            $items = $requirements->where('bereich', $bereich);
            if ($items->isEmpty()) {
                continue;
            }
            $gruppen[] = [
                'bereich' => $bereich,
                'items' => $items,
                'erledigt' => $items->filter(fn (QmRequirement $r) => $r->status->erledigt())->count(),
                'total' => $items->count(),
            ];
        }

        return view('livewire.quality.qm-checkliste', [
            'gruppen' => $gruppen,
            'statusOptionen' => QmStatus::cases(),
            'bereiche' => QmBereich::cases(),
            'erledigtGesamt' => $requirements->filter(fn (QmRequirement $r) => $r->status->erledigt())->count(),
            'totalGesamt' => $requirements->count(),
        ]);
    }
}
