<?php

namespace App\Livewire\Scheduling;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Compliance\ArbeitszeitgesetzDefaults;
use App\Domains\Scheduling\Compliance\Enums\ViolationSeverity;
use App\Domains\Scheduling\Models\ComplianceRule;
use App\Domains\Scheduling\Models\Shift;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Editor für das einrichtungseigene ArbZG-Regelwerk. Schwellwerte (`params`), Schwere, Notiz und Aktivierung
 * sind anpassbar (Tarif-/Betriebsvereinbarungen können abweichen); jede Regel verlinkt den amtlichen
 * Gesetzestext + Zitat. Zurücksetzen stellt den ableitbaren ArbZG-Default wieder her.
 */
#[Layout('layouts.app')]
class Arbeitsrecht extends Component
{
    /** @var array<int, array{severity:string, aktiv:bool, note:?string, params:array<string,int>}> */
    public array $edits = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        $this->ladeEdits();
    }

    private function ladeEdits(): void
    {
        $this->edits = [];
        foreach (ArbeitszeitgesetzDefaults::ensureFor(app(CurrentTenant::class)->id()) as $rule) {
            $this->edits[$rule->id] = [
                'severity' => $rule->severity->value,
                'aktiv' => $rule->aktiv,
                'note' => $rule->note,
                'params' => $rule->params,
            ];
        }
    }

    public function speichern(int $id): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        $rule = ComplianceRule::findOrFail($id);
        $e = $this->edits[$id];

        $this->validate([
            "edits.$id.severity" => ['required', 'in:'.implode(',', array_map(fn ($s) => $s->value, ViolationSeverity::editable()))],
            "edits.$id.params.*" => ['nullable', 'numeric', 'min:0', 'max:168'],
            "edits.$id.note" => ['nullable', 'string', 'max:500'],
        ]);

        $rule->update([
            'severity' => $e['severity'],
            'aktiv' => (bool) $e['aktiv'],
            'note' => $e['note'] ?: null,
            'params' => array_map(fn ($v) => (int) $v, $e['params']),
        ]);
        session()->flash('status', $rule->label.' gespeichert.');
    }

    public function zuruecksetzen(int $id): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        $rule = ComplianceRule::findOrFail($id);
        $default = collect(ArbeitszeitgesetzDefaults::rules())->firstWhere('key', $rule->key);
        if ($default !== null) {
            $rule->update([
                'severity' => $default['severity'], 'aktiv' => true,
                'note' => $default['note'], 'params' => $default['params'],
            ]);
            $this->ladeEdits();
            session()->flash('status', $rule->label.' auf den ArbZG-Standard zurückgesetzt.');
        }
    }

    public function render()
    {
        return view('livewire.scheduling.arbeitsrecht', [
            'rules' => ComplianceRule::where('tenant_id', app(CurrentTenant::class)->id())->orderBy('id')->get(),
            'severities' => ViolationSeverity::editable(),
            'version' => ArbeitszeitgesetzDefaults::VERSION,
        ]);
    }
}
