<?php

namespace App\Livewire\Hygiene;

use App\Domains\Hygiene\Enums\BefundArt;
use App\Domains\Hygiene\Enums\Erreger;
use App\Domains\Hygiene\Models\Hygieneplan;
use App\Domains\Hygiene\Models\InfektionsBefund;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Support\Concerns\ScopesTenantValidation;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Hygiene & Infektionsschutz (§ 23 IfSG): versionierter Hygieneplan mit Revisions-Ampel und die fortlaufende
 * MRE-/Infektions-Surveillance je Bewohner:in (Aufzeichnung resistenter Erreger und nosokomialer Infektionen,
 * § 23 Abs. 4) inkl. Meldepflicht-Verfolgung (§§ 6/7 IfSG).
 */
#[Layout('layouts.app')]
class Hygiene extends Component
{
    use ScopesTenantValidation;

    // Hygieneplan
    public string $p_titel = '';

    public string $p_version = '1.0';

    public string $p_inhalt = '';

    public ?int $p_intervall = 12;

    // Infektions-/MRE-Befund
    public ?int $b_resident = null;

    public string $b_erreger = 'mrsa';

    public string $b_art = 'besiedlung';

    public ?string $b_festgestellt = null;

    public string $b_massnahmen = '';

    public bool $b_meldepflichtig = false;

    public function mount(): void
    {
        abort_unless($this->darfSehen(), 403);
        $this->b_festgestellt = today()->toDateString();
    }

    private function darfSehen(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    /** Schlägt die Meldepflicht aus dem Erreger vor (je Fall durch die Fachkraft korrigierbar). */
    public function updatedBErreger(string $value): void
    {
        $erreger = Erreger::tryFrom($value);
        $this->b_meldepflichtig = $erreger?->meldeRelevant() ?? false;
    }

    public function planAnlegen(): void
    {
        abort_unless($this->darfSehen(), 403);
        $data = $this->validate([
            'p_titel' => ['required', 'string', 'max:160'],
            'p_version' => ['required', 'string', 'max:20'],
            'p_inhalt' => ['nullable', 'string', 'max:5000'],
            'p_intervall' => ['required', 'integer', 'min:1', 'max:60'],
        ]);
        Hygieneplan::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'titel' => $data['p_titel'], 'version' => $data['p_version'], 'inhalt' => $data['p_inhalt'] ?: null,
            'revision_intervall_monate' => $data['p_intervall'],
        ]);
        $this->reset('p_titel', 'p_inhalt');
        $this->p_version = '1.0';
        session()->flash('status', 'Hygieneplan als Entwurf angelegt.');
    }

    public function planFreigeben(int $id): void
    {
        abort_unless($this->darfSehen(), 403);
        Hygieneplan::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($id)
            ->update(['freigegeben_am' => today(), 'freigegeben_von' => auth()->id()]);
        session()->flash('status', 'Hygieneplan freigegeben.');
    }

    public function planLoeschen(int $id): void
    {
        abort_unless($this->darfSehen(), 403);
        Hygieneplan::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($id)->delete();
        session()->flash('status', 'Hygieneplan entfernt.');
    }

    public function befundErfassen(): void
    {
        abort_unless($this->darfSehen(), 403);
        $data = $this->validate([
            'b_resident' => ['required', 'integer', $this->tenantExists('residents')],
            'b_erreger' => ['required', 'in:'.implode(',', array_map(fn ($e) => $e->value, Erreger::cases()))],
            'b_art' => ['required', 'in:'.implode(',', array_map(fn ($a) => $a->value, BefundArt::cases()))],
            'b_festgestellt' => ['required', 'date'],
            'b_massnahmen' => ['nullable', 'string', 'max:400'],
        ]);
        InfektionsBefund::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'resident_id' => $data['b_resident'], 'erreger' => $data['b_erreger'], 'art' => $data['b_art'],
            'festgestellt_am' => $data['b_festgestellt'], 'massnahmen' => $data['b_massnahmen'] ?: null,
            'meldepflichtig' => $this->b_meldepflichtig, 'erfasst_von_user_id' => auth()->id(),
        ]);
        $this->reset('b_massnahmen');
        session()->flash('status', 'Befund in die Surveillance aufgenommen.');
    }

    public function befundAufheben(int $id): void
    {
        abort_unless($this->darfSehen(), 403);
        InfektionsBefund::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($id)
            ->update(['aufgehoben_am' => today()]);
        session()->flash('status', 'Befund aufgehoben (saniert/genesen).');
    }

    public function befundGemeldet(int $id): void
    {
        abort_unless($this->darfSehen(), 403);
        InfektionsBefund::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($id)
            ->update(['gemeldet_am' => today()]);
        session()->flash('status', 'Meldung ans Gesundheitsamt dokumentiert.');
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $befunde = InfektionsBefund::where('tenant_id', $tenantId)->with('resident')
            ->orderByRaw('aufgehoben_am is null desc')->orderByDesc('festgestellt_am')->get();

        return view('livewire.hygiene.hygiene', [
            'plaene' => Hygieneplan::where('tenant_id', $tenantId)->orderByDesc('id')->get(),
            'befunde' => $befunde,
            'residents' => Resident::where('tenant_id', $tenantId)->orderBy('name')->get(),
            'erregerCases' => Erreger::cases(),
            'artCases' => BefundArt::cases(),
            'offeneMeldungen' => $befunde->filter(fn (InfektionsBefund $b) => $b->meldungOffen())->count(),
            'aktiveBefunde' => $befunde->filter(fn (InfektionsBefund $b) => $b->aktiv())->count(),
        ]);
    }
}
