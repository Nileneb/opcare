<?php

namespace App\Livewire\Catering;

use App\Domains\Catering\Enums\ReinigungsIntervall;
use App\Domains\Catering\Models\Reinigungsaufgabe;
use App\Domains\Catering\Services\ReinigungErledigen;
use App\Domains\Identity\Support\CurrentTenant;
use App\Support\Concerns\ScopesTenantValidation;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reinigungs- und Desinfektionsplan Küche: Aufgabenverwaltung + Fälligkeits-Ampel + Erledigungsnachweis.
 * Norm-Anker: VO (EG) 852/2004 Anhang II Kap. I Nr. 1 (Reinigung/Desinfektion), LMHV §§ 3/4.
 * Dokumentationspflicht: Was/Womit/Wie/Wann/Wer (Hygienefachgesellschaft-Standard).
 */
#[Layout('layouts.app')]
class Reinigungsplan extends Component
{
    use ScopesTenantValidation;

    // Aufgabe anlegen
    public string $bezeichnung = '';

    public string $bereich = '';

    public string $intervall = '';

    public string $verantwortlich = '';

    // Erledigung melden
    public string $erledigt_am = '';

    public string $bemerkung = '';

    public function mount(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft', 'kueche'])), 403);

        $this->erledigt_am = now()->format('Y-m-d');
    }

    public function aufgabeSpeichern(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft', 'kueche'])), 403);

        $data = $this->validate([
            'bezeichnung' => ['required', 'string', 'max:160'],
            'bereich' => ['nullable', 'string', 'max:160'],
            'intervall' => ['required', 'string', 'in:'.implode(',', array_column(ReinigungsIntervall::cases(), 'value'))],
            'verantwortlich' => ['nullable', 'string', 'max:160'],
        ]);

        Reinigungsaufgabe::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'bezeichnung' => $data['bezeichnung'],
            'bereich' => $data['bereich'] ?: null,
            'intervall' => ReinigungsIntervall::from($data['intervall']),
            'verantwortlich' => $data['verantwortlich'] ?: null,
            'aktiv' => true,
        ]);

        $this->reset('bezeichnung', 'bereich', 'intervall', 'verantwortlich');
        session()->flash('status', 'Aufgabe angelegt.');
    }

    public function erledigen(int $aufgabeId, ReinigungErledigen $svc): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft', 'kueche'])), 403);

        try {
            $data = $this->validate([
                'erledigt_am' => ['required', 'date', 'before_or_equal:today'],
                'bemerkung' => ['nullable', 'string', 'max:1000'],
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        // WHY(IDOR): aufgabeId kommt als Methodenparameter — tenant-scope manuell prüfen.
        $aufgabe = Reinigungsaufgabe::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($aufgabeId);

        $svc->handle(
            $aufgabe,
            $data['erledigt_am'],
            auth()->id(),
            ($data['bemerkung'] ?? '') !== '' ? $data['bemerkung'] : null,
        );

        $this->reset('bemerkung');
        $this->erledigt_am = now()->format('Y-m-d');
        session()->flash('status', 'Erledigung dokumentiert.');
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();

        $aufgaben = Reinigungsaufgabe::where('tenant_id', $tenantId)
            ->where('aktiv', true)
            ->orderBy('bezeichnung')
            ->get()
            ->each(function (Reinigungsaufgabe $a): void {
                $a->setRelation(
                    'nachweise',
                    $a->nachweise()->with('erlediger')->latest('erledigt_am')->limit(5)->get()
                );
            });

        return view('livewire.catering.reinigungsplan', [
            'aufgaben' => $aufgaben,
            'intervalle' => ReinigungsIntervall::cases(),
        ]);
    }
}
