<?php

namespace App\Livewire\Personnel;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Enums\NachweisTyp;
use App\Domains\Personnel\Models\Schutznachweis;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Arbeitsschutz-Nachweise (Matrix Mitarbeiter:innen × Nachweis-Typ) mit Fälligkeits-Ampel — der generische
 * „Nachweis-mit-Frist"-Mechanismus für Unterweisung, arbeitsmedizinische Vorsorge, Erste Hilfe,
 * Brandschutzhelfer und BEM. Zeigt je Zelle den jüngsten Nachweis + Status; neue Nachweise direkt erfassbar.
 */
#[Layout('layouts.app')]
class Arbeitsschutz extends Component
{
    public ?int $erf_user = null;

    public string $erf_typ = 'unterweisung';

    public string $erf_datum = '';

    public ?int $erf_intervall = null;

    public string $erf_notiz = '';

    public function mount(): void
    {
        abort_unless($this->darfSehen(), 403);
        $this->erf_datum = today()->toDateString();
    }

    private function darfSehen(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function erfassen(): void
    {
        abort_unless($this->darfSehen(), 403);
        $data = $this->validate([
            'erf_user' => ['required', 'integer', 'exists:users,id'],
            'erf_typ' => ['required', 'in:'.implode(',', array_map(fn ($t) => $t->value, NachweisTyp::cases()))],
            'erf_datum' => ['required', 'date'],
            'erf_intervall' => ['nullable', 'integer', 'min:1', 'max:120'],
            'erf_notiz' => ['nullable', 'string', 'max:160'],
        ]);

        Schutznachweis::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'user_id' => $data['erf_user'], 'typ' => $data['erf_typ'], 'datum' => $data['erf_datum'],
            'intervall_monate' => $data['erf_intervall'], 'notiz' => $data['erf_notiz'] ?: null,
        ]);
        $this->reset('erf_intervall', 'erf_notiz');
        session()->flash('status', 'Nachweis erfasst.');
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $users = User::where('tenant_id', $tenantId)->whereHas('employeeProfile')->orderBy('name')->get();

        // jüngster Nachweis je (user, typ)
        $latest = [];
        foreach (Schutznachweis::where('tenant_id', $tenantId)->orderBy('datum')->get() as $n) {
            $latest[$n->user_id][$n->typ->value] = $n; // letzter überschreibt → jüngster bleibt
        }

        $ueberfaellig = 0;
        foreach ($latest as $perTyp) {
            foreach ($perTyp as $n) {
                if ($n->status() === 'ueberfaellig') {
                    $ueberfaellig++;
                }
            }
        }

        return view('livewire.personnel.arbeitsschutz', [
            'users' => $users,
            'typen' => NachweisTyp::cases(),
            'latest' => $latest,
            'ueberfaellig' => $ueberfaellig,
        ]);
    }
}
