<?php

namespace App\Livewire\Catering;

use App\Domains\Catering\Enums\GefahrenanalyseStatus;
use App\Domains\Catering\Enums\Gefahrenart;
use App\Domains\Catering\Enums\Lenkungsart;
use App\Domains\Catering\Models\Gefahrenanalyse as Analyse;
use App\Domains\Catering\Models\HaccpMesspunkt;
use App\Domains\Catering\Models\LebensmittelGefahr;
use App\Domains\Catering\Models\Lenkungsmassnahme;
use App\Domains\Catering\Services\GefahrenanalyseMonitor;
use App\Domains\Catering\Services\GefahrenanalyseVerifizieren;
use App\Domains\Identity\Support\CurrentTenant;
use App\Support\Concerns\ScopesTenantValidation;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * HACCP-Gefahrenanalyse-Register (HACCP-Prinzip 1–3, 6): Prozessschritt → Gefahren (B/C/P/A) →
 * CCP-Entscheidung + Verknüpfung zum Überwachungs-Messpunkt → Lenkungsmaßnahmen → Verifizierung.
 * Norm-Anker: Codex Alimentarius CAC/RCP 1-1969, VO (EG) 852/2004 Art. 5.
 */
#[Layout('layouts.app')]
class Gefahrenanalyse extends Component
{
    use ScopesTenantValidation;

    private const ROLES = ['admin', 'pflegefachkraft', 'kueche'];

    // Analyse anlegen
    public string $prozessschritt = '';

    public string $bereich = '';

    public int $verifizierungsintervall_monate = 12;

    public string $verantwortlich = '';

    // Gefahr hinzufügen
    public string $gefahr_art = '';

    public string $gefahr_beschreibung = '';

    public int $gefahr_wahrscheinlichkeit = 1;

    public int $gefahr_schwere = 1;

    public bool $gefahr_ist_ccp = false;

    public ?int $gefahr_messpunkt_id = null;

    public string $gefahr_ccp_begruendung = '';

    // Lenkung hinzufügen
    public string $lenkung_art = '';

    public string $lenkung_beschreibung = '';

    public string $lenkung_verantwortlich = '';

    public string $lenkung_frist = '';

    // Umsetzungs-/Verifizierungsdatum
    public string $umgesetzt_am = '';

    public string $verifiziert_am = '';

    public string $verifizierung_datum = '';

    private function darfVerwalten(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(self::ROLES));
    }

    public function mount(): void
    {
        abort_unless($this->darfVerwalten(), 403);

        $this->umgesetzt_am = today()->toDateString();
        $this->verifiziert_am = today()->toDateString();
        $this->verifizierung_datum = today()->toDateString();
    }

    public function analyseAnlegen(): void
    {
        abort_unless($this->darfVerwalten(), 403);

        $data = $this->validate([
            'prozessschritt' => ['required', 'string', 'max:160'],
            'bereich' => ['nullable', 'string', 'max:120'],
            'verifizierungsintervall_monate' => ['required', 'integer', 'min:1', 'max:120'],
            'verantwortlich' => ['nullable', 'string', 'max:120'],
        ]);

        Analyse::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'prozessschritt' => $data['prozessschritt'],
            'bereich' => $data['bereich'] ?: null,
            'verifizierungsintervall_monate' => $data['verifizierungsintervall_monate'],
            'verantwortlich' => $data['verantwortlich'] ?: null,
            'erstellt_am' => today(),
            'letzte_verifizierung_am' => today(),
            'status' => GefahrenanalyseStatus::Entwurf,
        ]);

        $this->reset('prozessschritt', 'bereich', 'verantwortlich');
        $this->verifizierungsintervall_monate = 12;
        session()->flash('status', 'Gefahrenanalyse angelegt.');
    }

    public function gefahrHinzufuegen(int $analyseId): void
    {
        abort_unless($this->darfVerwalten(), 403);

        $tenantId = app(CurrentTenant::class)->id();

        // WHY(IDOR): analyseId kommt als Methodenparameter — tenant-scoped prüfen.
        Analyse::where('tenant_id', $tenantId)->findOrFail($analyseId);

        $data = $this->validate([
            'gefahr_art' => ['required', 'string', 'in:'.implode(',', array_column(Gefahrenart::cases(), 'value'))],
            'gefahr_beschreibung' => ['required', 'string'],
            'gefahr_wahrscheinlichkeit' => ['required', 'integer', 'min:1', 'max:3'],
            'gefahr_schwere' => ['required', 'integer', 'min:1', 'max:3'],
            'gefahr_ist_ccp' => ['boolean'],
            'gefahr_messpunkt_id' => ['nullable', 'integer', $this->tenantExists('haccp_messpunkte')],
            'gefahr_ccp_begruendung' => ['nullable', 'string', 'max:1000'],
        ]);

        LebensmittelGefahr::create([
            'tenant_id' => $tenantId,
            'gefahrenanalyse_id' => $analyseId,
            'gefahrenart' => Gefahrenart::from($data['gefahr_art']),
            'beschreibung' => $data['gefahr_beschreibung'],
            'wahrscheinlichkeit' => $data['gefahr_wahrscheinlichkeit'],
            'schwere' => $data['gefahr_schwere'],
            'ist_ccp' => $data['gefahr_ist_ccp'] ?? false,
            'haccp_messpunkt_id' => $data['gefahr_messpunkt_id'] ?: null,
            'ccp_begruendung' => $data['gefahr_ccp_begruendung'] ?: null,
        ]);

        $this->reset('gefahr_art', 'gefahr_beschreibung', 'gefahr_ist_ccp', 'gefahr_messpunkt_id', 'gefahr_ccp_begruendung');
        $this->gefahr_wahrscheinlichkeit = 1;
        $this->gefahr_schwere = 1;
        session()->flash('status', 'Gefahr hinzugefügt.');
    }

    public function lenkungHinzufuegen(int $gefahrId): void
    {
        abort_unless($this->darfVerwalten(), 403);

        $tenantId = app(CurrentTenant::class)->id();

        // WHY(IDOR): gefahrId tenant-scoped prüfen.
        $gefahr = LebensmittelGefahr::where('tenant_id', $tenantId)->findOrFail($gefahrId);

        $data = $this->validate([
            'lenkung_art' => ['required', 'string', 'in:'.implode(',', array_column(Lenkungsart::cases(), 'value'))],
            'lenkung_beschreibung' => ['required', 'string'],
            'lenkung_verantwortlich' => ['nullable', 'string', 'max:120'],
            'lenkung_frist' => ['nullable', 'date'],
        ]);

        Lenkungsmassnahme::create([
            'tenant_id' => $tenantId,
            'lebensmittel_gefahr_id' => $gefahr->id,
            'art' => Lenkungsart::from($data['lenkung_art']),
            'beschreibung' => $data['lenkung_beschreibung'],
            'verantwortlich' => $data['lenkung_verantwortlich'] ?: null,
            'frist' => $data['lenkung_frist'] ?: null,
        ]);

        $this->reset('lenkung_art', 'lenkung_beschreibung', 'lenkung_verantwortlich', 'lenkung_frist');
        session()->flash('status', 'Lenkungsmaßnahme hinzugefügt.');
    }

    public function lenkungUmgesetzt(int $lenkungId): void
    {
        abort_unless($this->darfVerwalten(), 403);

        $data = $this->validate([
            'umgesetzt_am' => ['required', 'date', 'before_or_equal:today'],
        ]);

        // WHY(IDOR): lenkungId tenant-scoped laden.
        $lenkung = Lenkungsmassnahme::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($lenkungId);

        $lenkung->umgesetzt_am = $data['umgesetzt_am'];
        $lenkung->save();

        $this->umgesetzt_am = today()->toDateString();
        session()->flash('status', 'Lenkungsmaßnahme als umgesetzt markiert.');
    }

    public function lenkungVerifizieren(int $lenkungId): void
    {
        abort_unless($this->darfVerwalten(), 403);

        $data = $this->validate([
            'verifiziert_am' => ['required', 'date', 'before_or_equal:today'],
        ]);

        // WHY(IDOR): lenkungId tenant-scoped laden.
        $lenkung = Lenkungsmassnahme::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($lenkungId);

        $lenkung->verifiziert_am = $data['verifiziert_am'];
        $lenkung->save();

        $this->verifiziert_am = today()->toDateString();
        session()->flash('status', 'Wirksamkeit verifiziert und dokumentiert.');
    }

    public function analyseFreigeben(int $analyseId): void
    {
        abort_unless($this->darfVerwalten(), 403);

        // WHY(IDOR): analyseId tenant-scoped laden.
        $analyse = Analyse::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($analyseId);

        $analyse->status = GefahrenanalyseStatus::Freigegeben;
        $analyse->freigegeben_am = today();
        $analyse->save();

        session()->flash('status', 'Gefahrenanalyse freigegeben.');
    }

    public function analyseVerifizieren(int $analyseId): void
    {
        abort_unless($this->darfVerwalten(), 403);

        $data = $this->validate([
            'verifizierung_datum' => ['required', 'date', 'before_or_equal:today'],
        ]);

        // WHY(IDOR): analyseId tenant-scoped laden.
        $analyse = Analyse::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($analyseId);

        app(GefahrenanalyseVerifizieren::class)->handle($analyse, $data['verifizierung_datum']);

        $this->verifizierung_datum = today()->toDateString();
        session()->flash('status', 'Gefahrenanalyse verifiziert.');
    }

    public function render(GefahrenanalyseMonitor $monitor)
    {
        $tenantId = app(CurrentTenant::class)->id();

        return view('livewire.catering.gefahrenanalyse', [
            'analysen' => $monitor->status(),
            'gefahrenarten' => Gefahrenart::cases(),
            'lenkungsarten' => Lenkungsart::cases(),
            'messpunkte' => HaccpMesspunkt::where('tenant_id', $tenantId)->orderBy('bezeichnung')->get(),
            'ueberfaellig' => $monitor->ueberfaelligeAnzahl(),
            'mitLuecken' => $monitor->mitLueckenAnzahl(),
        ]);
    }
}
