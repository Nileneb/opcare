<?php

namespace App\Livewire\Brandschutz;

use App\Domains\Brandschutz\Enums\BrandschutzordnungTeil;
use App\Domains\Brandschutz\Enums\MangelSchwere;
use App\Domains\Brandschutz\Models\Brandschutzbegehung;
use App\Domains\Brandschutz\Models\Brandschutzmangel;
use App\Domains\Brandschutz\Models\Brandschutzordnung;
use App\Domains\Brandschutz\Models\Raeumungsuebung;
use App\Domains\Brandschutz\Services\BrandschutzMonitor;
use App\Domains\Identity\Support\CurrentTenant;
use App\Support\Concerns\ScopesTenantValidation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Brandschutz extends Component
{
    use ScopesTenantValidation;

    // Brandschutzordnung anlegen
    public string $ordnung_titel = '';

    public string $ordnung_teil = '';

    public string $ordnung_version = '';

    public int $ordnung_revision_intervall_monate = 24;

    // Begehung erfassen
    public string $begehung_bereich = '';

    public string $begehung_begangen_am = '';

    public int $begehung_intervall_monate = 12;

    public string $begehung_bemerkung = '';

    // Mangel hinzufügen
    public string $mangel_beschreibung = '';

    public string $mangel_schwere = '';

    public string $mangel_frist = '';

    // Mangel behoben
    public string $behoben_am = '';

    public string $behoben_notiz = '';

    // Räumungsübung dokumentieren
    public string $uebung_durchgefuehrt_am = '';

    public int $uebung_intervall_monate = 12;

    public string $uebung_bereich = '';

    public string $uebung_szenario = '';

    public ?int $uebung_teilnehmer_anzahl = null;

    public ?int $uebung_dauer_minuten = null;

    public string $uebung_erkenntnisse = '';

    public function mount(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik'])), 403);

        $this->begehung_begangen_am = today()->toDateString();
        $this->uebung_durchgefuehrt_am = today()->toDateString();
        $this->behoben_am = today()->toDateString();
    }

    public function ordnungAnlegen(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik'])), 403);

        $data = $this->validate([
            'ordnung_titel' => ['required', 'string', 'max:160'],
            'ordnung_teil' => ['required', 'string', 'in:'.implode(',', array_column(BrandschutzordnungTeil::cases(), 'value'))],
            'ordnung_version' => ['required', 'string', 'max:40'],
            'ordnung_revision_intervall_monate' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        Brandschutzordnung::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'titel' => $data['ordnung_titel'],
            'teil' => BrandschutzordnungTeil::from($data['ordnung_teil']),
            'version' => $data['ordnung_version'],
            'revision_intervall_monate' => $data['ordnung_revision_intervall_monate'],
            'aktiv' => true,
        ]);

        $this->reset('ordnung_titel', 'ordnung_teil', 'ordnung_version');
        $this->ordnung_revision_intervall_monate = 24;
        session()->flash('status', 'Brandschutzordnung angelegt.');
    }

    public function ordnungFreigeben(int $id): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik'])), 403);

        // WHY(IDOR): id kommt als Parameter — tenant-scope manuell prüfen.
        $ordnung = Brandschutzordnung::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($id);

        $ordnung->freigegeben_am = today();
        $ordnung->freigegeben_von = auth()->id();
        $ordnung->save();

        session()->flash('status', 'Brandschutzordnung freigegeben.');
    }

    public function begehungErfassen(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik'])), 403);

        $data = $this->validate([
            'begehung_bereich' => ['required', 'string', 'max:120'],
            'begehung_begangen_am' => ['required', 'date', 'before_or_equal:today'],
            'begehung_intervall_monate' => ['required', 'integer', 'min:1', 'max:120'],
            'begehung_bemerkung' => ['nullable', 'string'],
        ]);

        Brandschutzbegehung::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'bereich' => $data['begehung_bereich'],
            'begangen_am' => $data['begehung_begangen_am'],
            'begangen_von' => auth()->id(),
            'intervall_monate' => $data['begehung_intervall_monate'],
            'bemerkung' => $data['begehung_bemerkung'] ?: null,
        ]);

        $this->reset('begehung_bereich', 'begehung_bemerkung');
        $this->begehung_begangen_am = today()->toDateString();
        $this->begehung_intervall_monate = 12;
        session()->flash('status', 'Begehung erfasst.');
    }

    public function mangelHinzufuegen(int $begehungId): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik'])), 403);

        $tenantId = app(CurrentTenant::class)->id();

        // WHY(IDOR): begehungId tenant-scoped prüfen bevor Mangel zugeordnet wird.
        $begehung = Brandschutzbegehung::where('tenant_id', $tenantId)->findOrFail($begehungId);

        $data = $this->validate([
            'mangel_beschreibung' => ['required', 'string'],
            'mangel_schwere' => ['required', 'string', 'in:'.implode(',', array_column(MangelSchwere::cases(), 'value'))],
            'mangel_frist' => ['nullable', 'date'],
        ]);

        Brandschutzmangel::create([
            'tenant_id' => $tenantId,
            'brandschutzbegehung_id' => $begehung->id,
            'beschreibung' => $data['mangel_beschreibung'],
            'schwere' => MangelSchwere::from($data['mangel_schwere']),
            'frist' => $data['mangel_frist'] ?: null,
        ]);

        $this->reset('mangel_beschreibung', 'mangel_schwere', 'mangel_frist');
        session()->flash('status', 'Mangel hinzugefügt.');
    }

    public function mangelBehoben(int $mangelId): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik'])), 403);

        $data = $this->validate([
            'behoben_am' => ['required', 'date', 'before_or_equal:today'],
            'behoben_notiz' => ['nullable', 'string'],
        ]);

        // WHY(IDOR): Mangel über Begehung→Tenant gesichert.
        $mangel = Brandschutzmangel::whereHas(
            'begehung',
            fn ($q) => $q->where('tenant_id', app(CurrentTenant::class)->id())
        )->findOrFail($mangelId);

        $mangel->behoben_am = $data['behoben_am'];
        $mangel->behoben_notiz = $data['behoben_notiz'] ?: null;
        $mangel->save();

        $this->behoben_am = today()->toDateString();
        $this->reset('behoben_notiz');
        session()->flash('status', 'Mangel als behoben markiert.');
    }

    public function uebungDokumentieren(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik'])), 403);

        $data = $this->validate([
            'uebung_durchgefuehrt_am' => ['required', 'date', 'before_or_equal:today'],
            'uebung_intervall_monate' => ['required', 'integer', 'min:1', 'max:120'],
            'uebung_bereich' => ['nullable', 'string'],
            'uebung_szenario' => ['nullable', 'string'],
            'uebung_teilnehmer_anzahl' => ['nullable', 'integer', 'min:0'],
            'uebung_dauer_minuten' => ['nullable', 'integer', 'min:0'],
            'uebung_erkenntnisse' => ['nullable', 'string'],
        ]);

        Raeumungsuebung::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'durchgefuehrt_am' => $data['uebung_durchgefuehrt_am'],
            'durchgefuehrt_von' => auth()->id(),
            'intervall_monate' => $data['uebung_intervall_monate'],
            'bereich' => $data['uebung_bereich'] ?: null,
            'szenario' => $data['uebung_szenario'] ?: null,
            'teilnehmer_anzahl' => $data['uebung_teilnehmer_anzahl'],
            'dauer_minuten' => $data['uebung_dauer_minuten'],
            'erkenntnisse' => $data['uebung_erkenntnisse'] ?: null,
        ]);

        $this->reset('uebung_bereich', 'uebung_szenario', 'uebung_erkenntnisse');
        $this->uebung_durchgefuehrt_am = today()->toDateString();
        $this->uebung_intervall_monate = 12;
        $this->uebung_teilnehmer_anzahl = null;
        $this->uebung_dauer_minuten = null;
        session()->flash('status', 'Räumungsübung dokumentiert.');
    }

    public function render(BrandschutzMonitor $monitor)
    {
        $tenantId = app(CurrentTenant::class)->id();

        $ordnungen = Brandschutzordnung::where('tenant_id', $tenantId)
            ->with('freigeber')
            ->orderBy('teil')
            ->orderByDesc('created_at')
            ->get();

        $begehungen = $monitor->aktuelleBegehungen();

        $uebungen = Raeumungsuebung::where('tenant_id', $tenantId)
            ->orderByDesc('durchgefuehrt_am')
            ->get();

        return view('livewire.brandschutz.brandschutz', [
            'ordnungen' => $ordnungen,
            'begehungen' => $begehungen,
            'uebungen' => $uebungen,
            'ueberfaellig' => $monitor->ueberfaelligeAnzahl(),
            'offeneMaengel' => $monitor->offeneMaengelAnzahl(),
            'teile' => BrandschutzordnungTeil::cases(),
            'schweren' => MangelSchwere::cases(),
        ]);
    }
}
