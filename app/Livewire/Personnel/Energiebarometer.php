<?php

namespace App\Livewire\Personnel;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Enums\Energiestufe;
use App\Domains\Personnel\Models\Energielevel;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Freiwilliges Team-Energiebarometer. Jede:r Mitarbeitende setzt nur den EIGENEN aktuellen Wert (überschreibend,
 * kein Verlauf) und kann ihn jederzeit zurücknehmen (§ 26 BDSG: Freiwilligkeit). Sichtbar ist ausschließlich der
 * anonyme Hausschnitt — und erst ab einer Mindest-Rückmeldezahl (k-Anonymität), damit aus dem Aggregat keine
 * Einzelperson rückschließbar ist. Einführung ist nach § 87 Abs. 1 Nr. 6 BetrVG mitbestimmungspflichtig.
 */
#[Layout('layouts.app')]
class Energiebarometer extends Component
{
    /** Mindestzahl an Rückmeldungen, ab der das Aggregat angezeigt wird (k-Anonymität). */
    public const MIN_AUSWERTBAR = 3;

    public ?int $meine = null;

    public function mount(): void
    {
        abort_unless($this->darfTeilnehmen(), 403);
        $this->meine = $this->eigenes()?->stufe->value;
    }

    private function darfTeilnehmen(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole([
            'admin', 'pflegefachkraft', 'pflegehilfskraft', 'betreuungskraft', 'kueche', 'haustechnik', 'buchhaltung',
        ]));
    }

    private function eigenes(): ?Energielevel
    {
        return Energielevel::where('user_id', auth()->id())->first();
    }

    public function setzen(int $stufe): void
    {
        abort_unless($this->darfTeilnehmen(), 403);
        $energiestufe = Energiestufe::tryFrom($stufe);
        abort_if($energiestufe === null, 422);

        Energielevel::updateOrCreate(
            ['tenant_id' => app(CurrentTenant::class)->id(), 'user_id' => auth()->id()],
            ['stufe' => $energiestufe],
        );
        $this->meine = $energiestufe->value;
        session()->flash('status', 'Danke — dein Energie-Level ist gespeichert.');
    }

    public function zuruecknehmen(): void
    {
        abort_unless($this->darfTeilnehmen(), 403);
        $this->eigenes()?->delete();
        $this->meine = null;
        session()->flash('status', 'Deine Rückmeldung wurde entfernt.');
    }

    public function render()
    {
        $levels = Energielevel::all();
        $gesamt = $levels->count();
        $auswertbar = $gesamt >= self::MIN_AUSWERTBAR;

        $verteilung = [];
        foreach (Energiestufe::cases() as $stufe) {
            $verteilung[$stufe->value] = $levels->where('stufe', $stufe)->count();
        }

        $schnitt = $gesamt > 0 ? $levels->avg(fn (Energielevel $l) => $l->stufe->value) : null;
        $hausAmpel = match (true) {
            $schnitt === null => 'gray',
            $schnitt < 1.7 => 'red',
            $schnitt < 2.4 => 'amber',
            default => 'green',
        };

        return view('livewire.personnel.energiebarometer', [
            'stufen' => Energiestufe::cases(),
            'gesamt' => $gesamt,
            'auswertbar' => $auswertbar,
            'verteilung' => $verteilung,
            'hausAmpel' => $hausAmpel,
            'minAuswertbar' => self::MIN_AUSWERTBAR,
        ]);
    }
}
