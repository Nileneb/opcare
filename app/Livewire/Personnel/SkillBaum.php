<?php

namespace App\Livewire\Personnel;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Enums\KompetenzTyp;
use App\Domains\Personnel\Models\Kompetenz;
use App\Domains\Personnel\Models\MitarbeiterKompetenz;
use App\Domains\Personnel\Support\KompetenzDefaults;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Skill-Baum: Kompetenz-Katalog (mit Voraussetzungen) und die erworbenen Kompetenzen je Mitarbeiter:in mit
 * Fälligkeits-Ampel. Beim Erteilen wird der Voraussetzungs-Graph geprüft (z. B. „Wundexperte" nur mit „Fachkraft").
 */
#[Layout('layouts.app')]
class SkillBaum extends Component
{
    public ?int $selectedUser = null;

    public ?int $g_kompetenz = null;

    public string $g_datum = '';

    public function mount(): void
    {
        abort_unless($this->darf(), 403);
        KompetenzDefaults::ensureFor(app(CurrentTenant::class)->id());
        $this->g_datum = today()->toDateString();
    }

    private function darf(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function erteilen(): void
    {
        abort_unless($this->darf(), 403);
        $data = $this->validate([
            'selectedUser' => ['required', 'integer', 'exists:users,id'],
            'g_kompetenz' => ['required', 'integer', 'exists:kompetenzen,id'],
            'g_datum' => ['required', 'date'],
        ]);
        $kompetenz = Kompetenz::findOrFail($data['g_kompetenz']);

        // Voraussetzungs-Graph: alle Voraussetzungen müssen als aktive Kompetenz vorliegen.
        $vorhandene = MitarbeiterKompetenz::where('user_id', $data['selectedUser'])->get()
            ->filter(fn ($mk) => $mk->aktiv())->pluck('kompetenz_id')->all();
        $fehlend = $kompetenz->voraussetzungen->reject(fn ($v) => in_array($v->id, $vorhandene, true));
        if ($fehlend->isNotEmpty()) {
            $this->addError('g_kompetenz', 'Fehlende Voraussetzung(en): '.$fehlend->pluck('name')->implode(', '));

            return;
        }

        $gueltigBis = $kompetenz->gueltigkeit_monate
            ? Carbon::parse($data['g_datum'])->addMonths($kompetenz->gueltigkeit_monate)->toDateString()
            : null;

        MitarbeiterKompetenz::updateOrCreate(
            ['user_id' => $data['selectedUser'], 'kompetenz_id' => $kompetenz->id],
            ['tenant_id' => app(CurrentTenant::class)->id(), 'erworben_am' => $data['g_datum'], 'gueltig_bis' => $gueltigBis, 'verifiziert_von' => auth()->id()],
        );
        $this->reset('g_kompetenz');
        session()->flash('status', $kompetenz->name.' erteilt.');
    }

    public function entziehen(int $id): void
    {
        abort_unless($this->darf(), 403);
        MitarbeiterKompetenz::where('user_id', $this->selectedUser)->findOrFail($id)->delete();
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $kompetenzen = Kompetenz::with('voraussetzungen')->where('tenant_id', $tenantId)->where('aktiv', true)->get();

        $erworben = $this->selectedUser
            ? MitarbeiterKompetenz::with('kompetenz')->where('user_id', $this->selectedUser)->get()->sortBy(fn ($mk) => $mk->kompetenz->name)
            : collect();

        return view('livewire.personnel.skill-baum', [
            'kompetenzenNachTyp' => $kompetenzen->groupBy(fn (Kompetenz $k) => $k->typ->value),
            'typen' => KompetenzTyp::cases(),
            'users' => User::where('tenant_id', $tenantId)->whereHas('employeeProfile')->orderBy('name')->get(),
            'erworben' => $erworben,
        ]);
    }
}
