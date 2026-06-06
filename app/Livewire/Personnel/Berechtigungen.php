<?php

namespace App\Livewire\Personnel;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Models\Delegation;
use App\Domains\Personnel\Models\Taetigkeit;
use App\Domains\Personnel\Support\Befugnis;
use App\Domains\Personnel\Support\TaetigkeitDefaults;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Berechtigungsmatrix (wer darf welche Tätigkeit — geprüft durch den Befugnis-Service aus Qualifikation,
 * Kompetenz und Delegation) plus die Delegationsverwaltung (Anordnung ärztlicher/technischer Tätigkeiten).
 */
#[Layout('layouts.app')]
class Berechtigungen extends Component
{
    public ?int $selectedUser = null;

    public ?int $d_taetigkeit = null;

    public ?int $d_nehmer = null;

    public string $d_anordner = '';

    public ?string $d_gueltig_bis = null;

    public string $d_notiz = '';

    public function mount(): void
    {
        abort_unless($this->darf(), 403);
        TaetigkeitDefaults::ensureFor(app(CurrentTenant::class)->id());
    }

    private function darf(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function delegieren(): void
    {
        abort_unless($this->darf(), 403);
        $data = $this->validate([
            'd_taetigkeit' => ['required', 'integer', 'exists:taetigkeiten,id'],
            'd_nehmer' => ['required', 'integer', 'exists:users,id'],
            'd_anordner' => ['required', 'string', 'max:120'],
            'd_gueltig_bis' => ['nullable', 'date', 'after:today'],
            'd_notiz' => ['nullable', 'string', 'max:160'],
        ]);
        Delegation::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'taetigkeit_id' => $data['d_taetigkeit'], 'nehmer_id' => $data['d_nehmer'], 'anordner_name' => $data['d_anordner'],
            'delegiert_am' => today()->toDateString(), 'gueltig_bis' => $data['d_gueltig_bis'], 'nachweis_notiz' => $this->d_notiz ?: null,
        ]);
        $this->reset('d_taetigkeit', 'd_nehmer', 'd_anordner', 'd_gueltig_bis', 'd_notiz');
        session()->flash('status', 'Delegation erteilt.');
    }

    public function widerrufen(int $id): void
    {
        abort_unless($this->darf(), 403);
        Delegation::findOrFail($id)->update(['widerruf_am' => now(), 'widerruf_grund' => 'manuell widerrufen']);
    }

    public function render(Befugnis $befugnis)
    {
        $tenantId = app(CurrentTenant::class)->id();
        $taetigkeiten = Taetigkeit::with('erforderlicheKompetenz')->where('tenant_id', $tenantId)->where('aktiv', true)->orderBy('bereich')->orderBy('id')->get();
        $users = User::where('tenant_id', $tenantId)->whereHas('employeeProfile')->orderBy('name')->get();

        $matrix = [];
        if ($this->selectedUser) {
            $user = $users->firstWhere('id', $this->selectedUser);
            if ($user) {
                foreach ($taetigkeiten as $t) {
                    $matrix[$t->id] = $befugnis->hindernis($user, $t);
                }
            }
        }

        return view('livewire.personnel.berechtigungen', [
            'taetigkeiten' => $taetigkeiten,
            'users' => $users,
            'matrix' => $matrix,
            'delegationen' => Delegation::with(['taetigkeit', 'nehmer'])->where('tenant_id', $tenantId)->whereNull('widerruf_am')->orderByDesc('id')->get(),
        ]);
    }
}
