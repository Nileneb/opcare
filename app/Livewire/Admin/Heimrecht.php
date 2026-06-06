<?php

namespace App\Livewire\Admin;

use App\Domains\Identity\Enums\Bundesland;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Compliance\HeimrechtRegelwerk;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Übersicht des für die Einrichtung geltenden Landesheimrechts. Das Bundesland wird automatisch aus der
 * Einrichtungs-Adresse (PLZ) abgeleitet und kann manuell korrigiert werden (Leitregionen überschreiten teils
 * Landesgrenzen). Zeigt das einschlägige Landesheimgesetz mit amtlichem Link und die daraus abgeleiteten
 * Personalbemessungs-Defaults (Bundes-Default → Landes-Override → Träger-Override im Betreuungsschlüssel).
 */
#[Layout('layouts.app')]
class Heimrecht extends Component
{
    public ?string $bundesland = null;

    public function mount(): void
    {
        abort_unless($this->darfSehen(), 403);
        $this->bundesland = app(CurrentTenant::class)->get()?->bundesland?->value;
    }

    private function darfSehen(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    private function darfBearbeiten(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin']));
    }

    public function speichern(): void
    {
        abort_unless($this->darfBearbeiten(), 403);
        $this->validate([
            'bundesland' => ['nullable', 'in:'.implode(',', array_map(fn ($b) => $b->value, Bundesland::cases()))],
        ]);

        $tenant = app(CurrentTenant::class)->get();
        $tenant->update(['bundesland' => $this->bundesland ?: null]);

        session()->flash('status', 'Bundesland gespeichert.');
    }

    public function render()
    {
        $tenant = app(CurrentTenant::class)->get();
        $land = $tenant->landesrecht();

        return view('livewire.admin.heimrecht', [
            'tenant' => $tenant,
            'land' => $land,
            'ausPlz' => $tenant->bundesland === null,
            'heimrecht' => HeimrechtRegelwerk::fuer($land),
            'laender' => Bundesland::cases(),
            'darfBearbeiten' => $this->darfBearbeiten(),
        ]);
    }
}
