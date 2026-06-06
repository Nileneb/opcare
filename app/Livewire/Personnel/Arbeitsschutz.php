<?php

namespace App\Livewire\Personnel;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Personnel\Enums\BetriebsbetreuungArt;
use App\Domains\Personnel\Enums\NachweisTyp;
use App\Domains\Personnel\Models\Betriebsbetreuung;
use App\Domains\Personnel\Models\Schutznachweis;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Arbeitsschutz-Nachweise (Matrix Mitarbeiter:innen × Nachweis-Typ) mit Fälligkeits-Ampel — der generische
 * „Nachweis-mit-Frist"-Mechanismus für Unterweisung, arbeitsmedizinische Vorsorge, Erste Hilfe,
 * Brandschutzhelfer und BEM. Zeigt je Zelle den jüngsten Nachweis + Status; neue Nachweise direkt erfassbar.
 * Zweite Sektion: betriebsärztliche & sicherheitstechnische Betreuung (ASiG/DGUV V2) mit Begehungs-Ampel.
 */
#[Layout('layouts.app')]
class Arbeitsschutz extends Component
{
    public ?int $erf_user = null;

    public string $erf_typ = 'unterweisung';

    public string $erf_datum = '';

    public ?int $erf_intervall = null;

    public string $erf_notiz = '';

    // Betriebsärztliche/sicherheitstechnische Betreuung (ASiG/DGUV V2)
    public string $bb_art = 'betriebsarzt';

    public string $bb_name = '';

    public string $bb_firma = '';

    public bool $bb_extern = true;

    public string $bb_telefon = '';

    public string $bb_email = '';

    public ?string $bb_bestellt_am = null;

    public ?int $bb_einsatzzeit = null;

    public ?int $bb_intervall = 12;

    public ?string $beg_datum = null;

    public function mount(): void
    {
        abort_unless($this->darfSehen(), 403);
        $this->erf_datum = today()->toDateString();
        $this->beg_datum = today()->toDateString();
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

    public function betreuungAnlegen(): void
    {
        abort_unless($this->darfSehen(), 403);
        $data = $this->validate([
            'bb_art' => ['required', 'in:'.implode(',', array_map(fn ($a) => $a->value, BetriebsbetreuungArt::cases()))],
            'bb_name' => ['required', 'string', 'max:120'],
            'bb_firma' => ['nullable', 'string', 'max:120'],
            'bb_telefon' => ['nullable', 'string', 'max:60'],
            'bb_email' => ['nullable', 'email', 'max:120'],
            'bb_bestellt_am' => ['nullable', 'date'],
            'bb_einsatzzeit' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'bb_intervall' => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);
        Betriebsbetreuung::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'art' => $data['bb_art'], 'name' => $data['bb_name'], 'firma' => $data['bb_firma'] ?: null,
            'extern' => $this->bb_extern, 'telefon' => $data['bb_telefon'] ?: null, 'email' => $data['bb_email'] ?: null,
            'bestellt_am' => $data['bb_bestellt_am'] ?: null, 'einsatzzeit_stunden' => $data['bb_einsatzzeit'],
            'begehung_intervall_monate' => $data['bb_intervall'],
        ]);
        $this->reset('bb_name', 'bb_firma', 'bb_telefon', 'bb_email', 'bb_bestellt_am', 'bb_einsatzzeit');
        session()->flash('status', 'Betreuung angelegt.');
    }

    public function begehungDokumentieren(int $id): void
    {
        abort_unless($this->darfSehen(), 403);
        $this->validate(['beg_datum' => ['required', 'date']]);
        Betriebsbetreuung::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($id)
            ->update(['letzte_begehung' => $this->beg_datum]);
        session()->flash('status', 'Begehung dokumentiert.');
    }

    public function betreuungLoeschen(int $id): void
    {
        abort_unless($this->darfSehen(), 403);
        Betriebsbetreuung::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($id)->delete();
        session()->flash('status', 'Betreuung entfernt.');
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
            'betreuungen' => Betriebsbetreuung::where('tenant_id', $tenantId)->orderBy('art')->get(),
            'betreuungsArten' => BetriebsbetreuungArt::cases(),
        ]);
    }
}
