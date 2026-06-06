<?php

namespace App\Livewire\Personnel;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Enums\FortbildungsThema;
use App\Domains\Personnel\Models\Fortbildung;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Fortbildungsplan (QPR QB6): geplante und absolvierte Fortbildungen je Mitarbeiter:in plus die Pflicht-Themen-
 * Matrix mit Auffrischungs-Ampel (Hygiene/Datenschutz/Gewaltschutz/Reanimation/Brandschutz). Ein Pflichtthema,
 * das eine Person nie absolviert hat, ist rot — das macht die Fortbildungspflicht des Trägers operativ.
 */
#[Layout('layouts.app')]
class Fortbildungsplan extends Component
{
    public ?int $f_user = null;

    public string $f_thema = 'hygiene';

    public string $f_titel = '';

    public string $f_anbieter = '';

    public ?string $f_geplant_am = null;

    public ?string $f_absolviert_am = null;

    public ?int $f_stunden = null;

    public bool $f_pflicht = true;

    public ?int $f_intervall = 12;

    public function mount(): void
    {
        abort_unless($this->darfSehen(), 403);
        $this->f_geplant_am = today()->toDateString();
    }

    private function darfSehen(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    /** Belegt Pflicht-Flag und Intervall aus dem Thema vor (überschreibbar). */
    public function updatedFThema(string $value): void
    {
        $thema = FortbildungsThema::tryFrom($value);
        $this->f_pflicht = $thema?->pflicht() ?? false;
        $this->f_intervall = $thema?->intervallMonate();
    }

    public function planen(): void
    {
        abort_unless($this->darfSehen(), 403);
        $data = $this->validate([
            'f_user' => ['required', 'integer', 'exists:users,id'],
            'f_thema' => ['required', 'in:'.implode(',', array_map(fn ($t) => $t->value, FortbildungsThema::cases()))],
            'f_titel' => ['required', 'string', 'max:160'],
            'f_anbieter' => ['nullable', 'string', 'max:120'],
            'f_geplant_am' => ['nullable', 'date'],
            'f_absolviert_am' => ['nullable', 'date'],
            'f_stunden' => ['nullable', 'integer', 'min:1', 'max:999'],
            'f_intervall' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        Fortbildung::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'user_id' => $data['f_user'], 'thema' => $data['f_thema'], 'titel' => $data['f_titel'],
            'anbieter' => $data['f_anbieter'] ?: null, 'geplant_am' => $data['f_geplant_am'] ?: null,
            'absolviert_am' => $data['f_absolviert_am'] ?: null, 'umfang_stunden' => $data['f_stunden'],
            'pflicht' => $this->f_pflicht, 'intervall_monate' => $this->f_pflicht ? $data['f_intervall'] : null,
        ]);
        $this->reset('f_titel', 'f_anbieter', 'f_absolviert_am', 'f_stunden');
        session()->flash('status', 'Fortbildung im Plan erfasst.');
    }

    public function absolviert(int $id): void
    {
        abort_unless($this->darfSehen(), 403);
        Fortbildung::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($id)
            ->update(['absolviert_am' => today()]);
        session()->flash('status', 'Als absolviert markiert.');
    }

    public function loeschen(int $id): void
    {
        abort_unless($this->darfSehen(), 403);
        Fortbildung::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($id)->delete();
        session()->flash('status', 'Eintrag entfernt.');
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $users = User::where('tenant_id', $tenantId)->whereHas('employeeProfile')->orderBy('name')->get();
        $fortbildungen = Fortbildung::where('tenant_id', $tenantId)->with('user')->orderByDesc('id')->get();

        // jüngste absolvierte Fortbildung je (user, pflichtthema) → Auffrischungs-Ampel
        $pflichtThemen = array_filter(FortbildungsThema::cases(), fn ($t) => $t->pflicht());
        $matrix = [];
        foreach ($fortbildungen as $f) {
            if ($f->absolviert_am === null || ! $f->pflicht) {
                continue;
            }
            $key = $f->thema->value;
            $vorhanden = $matrix[$f->user_id][$key] ?? null;
            if ($vorhanden === null || $f->absolviert_am->greaterThan($vorhanden->absolviert_am)) {
                $matrix[$f->user_id][$key] = $f;
            }
        }

        $luecken = 0;
        foreach ($users as $u) {
            foreach ($pflichtThemen as $t) {
                $fb = $matrix[$u->id][$t->value] ?? null;
                if ($fb === null || $fb->ampel() === 'red') {
                    $luecken++;
                }
            }
        }

        return view('livewire.personnel.fortbildungsplan', [
            'users' => $users,
            'fortbildungen' => $fortbildungen,
            'themen' => FortbildungsThema::cases(),
            'pflichtThemen' => $pflichtThemen,
            'matrix' => $matrix,
            'luecken' => $luecken,
        ]);
    }
}
