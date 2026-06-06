<?php

namespace App\Livewire\Scheduling;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Compliance\ArbeitszeitgesetzDefaults;
use App\Domains\Scheduling\Compliance\Enums\ViolationSeverity;
use App\Domains\Scheduling\Compliance\PersonalbemessungDefaults;
use App\Domains\Scheduling\Compliance\ScheduleQualityDefaults;
use App\Domains\Scheduling\Models\ComplianceRule;
use App\Domains\Scheduling\Models\ScheduleQualityRule;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\StaffingConfig;
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

    /** @var array<int, array{severity:string, aktiv:bool, params:array<string,int|float>}> */
    public array $qedits = [];

    public float $sc_wochenstunden = 38.5;

    public float $sc_fachkraftquote = 0.5;

    public int $sc_nachtdienst = 50;

    public float $sc_multiplikator = 1.0;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        $this->ladeEdits();
    }

    private function ladeEdits(): void
    {
        $tenantId = app(CurrentTenant::class)->id();
        $this->edits = [];
        foreach (ArbeitszeitgesetzDefaults::ensureFor($tenantId) as $rule) {
            $this->edits[$rule->id] = [
                'severity' => $rule->severity->value,
                'aktiv' => $rule->aktiv,
                'note' => $rule->note,
                'params' => $rule->params,
            ];
        }

        $this->qedits = [];
        foreach (ScheduleQualityDefaults::ensureFor($tenantId) as $rule) {
            $this->qedits[$rule->id] = [
                'severity' => $rule->severity->value,
                'aktiv' => $rule->aktiv,
                'params' => $rule->params,
            ];
        }

        $sc = PersonalbemessungDefaults::ensureConfig($tenantId);
        $this->sc_wochenstunden = $sc->wochenstunden;
        $this->sc_fachkraftquote = $sc->fachkraftquote_min;
        $this->sc_nachtdienst = $sc->nachtdienst_je_fachkraft;
        $this->sc_multiplikator = $sc->paw_multiplikator;
    }

    public function qSpeichern(int $id): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        $rule = ScheduleQualityRule::findOrFail($id);
        $e = $this->qedits[$id];
        $this->validate([
            "qedits.$id.severity" => ['required', 'in:warnung,hinweis'],
            "qedits.$id.params.*" => ['nullable', 'numeric', 'min:0', 'max:168'],
        ]);
        $rule->update([
            'severity' => $e['severity'],
            'aktiv' => (bool) $e['aktiv'],
            'params' => array_map(fn ($v) => is_numeric($v) && (float) $v == (int) $v ? (int) $v : (float) $v, $e['params']),
        ]);
        session()->flash('status', $rule->label.' gespeichert.');
    }

    public function staffingSpeichern(): void
    {
        abort_unless(auth()->user()?->can('manage', Shift::class), 403);
        $this->validate([
            'sc_wochenstunden' => ['required', 'numeric', 'min:20', 'max:48'],
            'sc_fachkraftquote' => ['required', 'numeric', 'min:0', 'max:1'],
            'sc_nachtdienst' => ['required', 'integer', 'min:1', 'max:100'],
            'sc_multiplikator' => ['required', 'numeric', 'min:0.5', 'max:3'],
        ]);
        StaffingConfig::updateOrCreate(
            ['tenant_id' => app(CurrentTenant::class)->id()],
            [
                'wochenstunden' => $this->sc_wochenstunden,
                'fachkraftquote_min' => $this->sc_fachkraftquote,
                'nachtdienst_je_fachkraft' => $this->sc_nachtdienst,
                'paw_multiplikator' => $this->sc_multiplikator,
            ],
        );
        session()->flash('status', 'Betreuungsschlüssel-Einstellungen gespeichert.');
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
        $tenantId = app(CurrentTenant::class)->id();

        return view('livewire.scheduling.arbeitsrecht', [
            'rules' => ComplianceRule::where('tenant_id', $tenantId)->orderBy('id')->get(),
            'severities' => ViolationSeverity::editable(),
            'version' => ArbeitszeitgesetzDefaults::VERSION,
            'qualityRules' => ScheduleQualityRule::where('tenant_id', $tenantId)->orderBy('id')->get(),
            'pawVersion' => PersonalbemessungDefaults::VERSION,
        ]);
    }
}
