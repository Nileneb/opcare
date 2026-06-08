<?php

namespace App\Livewire\Arbeitsschutz;

use App\Domains\Arbeitsschutz\Enums\GbuStatus;
use App\Domains\Arbeitsschutz\Enums\Gefaehrdungsfaktor;
use App\Domains\Arbeitsschutz\Enums\Massnahmentyp;
use App\Domains\Arbeitsschutz\Models\Gefaehrdung;
use App\Domains\Arbeitsschutz\Models\Gefaehrdungsbeurteilung as GbuModel;
use App\Domains\Arbeitsschutz\Models\Schutzmassnahme;
use App\Domains\Arbeitsschutz\Services\GbuFortschreiben;
use App\Domains\Arbeitsschutz\Services\GbuMonitor;
use App\Domains\Identity\Support\CurrentTenant;
use App\Support\Concerns\ScopesTenantValidation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Gefaehrdungsbeurteilung extends Component
{
    use ScopesTenantValidation;

    // GBU anlegen
    public string $arbeitsbereich = '';

    public string $taetigkeit = '';

    public int $ueberpruefungsintervall_monate = 12;

    public string $verantwortlich = '';

    // Gefährdung hinzufügen
    public string $gefaehrdung_faktor = '';

    public string $gefaehrdung_beschreibung = '';

    public int $gefaehrdung_wahrscheinlichkeit = 1;

    public int $gefaehrdung_schwere = 1;

    // Maßnahme hinzufügen
    public string $massnahme_typ = '';

    public string $massnahme_beschreibung = '';

    public string $massnahme_verantwortlich = '';

    public string $massnahme_frist = '';

    // Umsetzungs- / Wirksamkeitsdatum
    public string $umgesetzt_am = '';

    public string $wirksam_geprueft_am = '';

    // Fortschreibungsdatum
    public string $fortschreibung_datum = '';

    public function mount(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft'])), 403);

        $this->umgesetzt_am = today()->toDateString();
        $this->wirksam_geprueft_am = today()->toDateString();
        $this->fortschreibung_datum = today()->toDateString();
    }

    public function gbuAnlegen(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft'])), 403);

        $data = $this->validate([
            'arbeitsbereich' => ['required', 'string', 'max:160'],
            'taetigkeit' => ['nullable', 'string', 'max:160'],
            'ueberpruefungsintervall_monate' => ['required', 'integer', 'min:1', 'max:120'],
            'verantwortlich' => ['nullable', 'string', 'max:120'],
        ]);

        GbuModel::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'arbeitsbereich' => $data['arbeitsbereich'],
            'taetigkeit' => $data['taetigkeit'] ?: null,
            'ueberpruefungsintervall_monate' => $data['ueberpruefungsintervall_monate'],
            'verantwortlich' => $data['verantwortlich'] ?: null,
            'erstellt_am' => today(),
            'letzte_ueberpruefung_am' => today(),
            'status' => GbuStatus::Entwurf,
        ]);

        $this->reset('arbeitsbereich', 'taetigkeit', 'verantwortlich');
        $this->ueberpruefungsintervall_monate = 12;
        session()->flash('status', 'Gefährdungsbeurteilung angelegt.');
    }

    public function gefaehrdungHinzufuegen(int $gbuId): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft'])), 403);

        $tenantId = app(CurrentTenant::class)->id();

        // WHY(IDOR): gbuId kommt als Methodenparameter — tenant-scope manuell prüfen.
        GbuModel::where('tenant_id', $tenantId)->findOrFail($gbuId);

        $data = $this->validate([
            'gefaehrdung_faktor' => ['required', 'string', 'in:'.implode(',', array_column(Gefaehrdungsfaktor::cases(), 'value'))],
            'gefaehrdung_beschreibung' => ['required', 'string'],
            'gefaehrdung_wahrscheinlichkeit' => ['required', 'integer', 'min:1', 'max:3'],
            'gefaehrdung_schwere' => ['required', 'integer', 'min:1', 'max:3'],
        ]);

        Gefaehrdung::create([
            'tenant_id' => $tenantId,
            'gefaehrdungsbeurteilung_id' => $gbuId,
            'faktor' => Gefaehrdungsfaktor::from($data['gefaehrdung_faktor']),
            'beschreibung' => $data['gefaehrdung_beschreibung'],
            'wahrscheinlichkeit' => $data['gefaehrdung_wahrscheinlichkeit'],
            'schwere' => $data['gefaehrdung_schwere'],
        ]);

        $this->reset('gefaehrdung_faktor', 'gefaehrdung_beschreibung');
        $this->gefaehrdung_wahrscheinlichkeit = 1;
        $this->gefaehrdung_schwere = 1;
        session()->flash('status', 'Gefährdung hinzugefügt.');
    }

    public function massnahmeHinzufuegen(int $gefaehrdungId): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft'])), 403);

        $tenantId = app(CurrentTenant::class)->id();

        // WHY(IDOR): gefaehrdungId über GBU-Relation tenant-scoped prüfen.
        $gefaehrdung = Gefaehrdung::where('tenant_id', $tenantId)->findOrFail($gefaehrdungId);

        $data = $this->validate([
            'massnahme_typ' => ['required', 'string', 'in:'.implode(',', array_column(Massnahmentyp::cases(), 'value'))],
            'massnahme_beschreibung' => ['required', 'string'],
            'massnahme_verantwortlich' => ['nullable', 'string', 'max:120'],
            'massnahme_frist' => ['nullable', 'date'],
        ]);

        Schutzmassnahme::create([
            'tenant_id' => $tenantId,
            'gefaehrdung_id' => $gefaehrdung->id,
            'typ' => Massnahmentyp::from($data['massnahme_typ']),
            'beschreibung' => $data['massnahme_beschreibung'],
            'verantwortlich' => $data['massnahme_verantwortlich'] ?: null,
            'frist' => $data['massnahme_frist'] ?: null,
        ]);

        $this->reset('massnahme_typ', 'massnahme_beschreibung', 'massnahme_verantwortlich', 'massnahme_frist');
        session()->flash('status', 'Maßnahme hinzugefügt.');
    }

    public function massnahmeUmgesetzt(int $massnahmeId): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft'])), 403);

        $data = $this->validate([
            'umgesetzt_am' => ['required', 'date', 'before_or_equal:today'],
        ]);

        // WHY(IDOR): massnahmeId tenant-scoped laden.
        $massnahme = Schutzmassnahme::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($massnahmeId);

        $massnahme->umgesetzt_am = $data['umgesetzt_am'];
        $massnahme->save();

        $this->umgesetzt_am = today()->toDateString();
        session()->flash('status', 'Maßnahme als umgesetzt markiert.');
    }

    public function wirksamkeitPruefen(int $massnahmeId): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft'])), 403);

        $data = $this->validate([
            'wirksam_geprueft_am' => ['required', 'date', 'before_or_equal:today'],
        ]);

        // WHY(IDOR): massnahmeId tenant-scoped laden.
        $massnahme = Schutzmassnahme::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($massnahmeId);

        $massnahme->wirksam_geprueft_am = $data['wirksam_geprueft_am'];
        $massnahme->save();

        $this->wirksam_geprueft_am = today()->toDateString();
        session()->flash('status', 'Wirksamkeit geprüft und dokumentiert.');
    }

    public function gbuFreigeben(int $gbuId): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft'])), 403);

        // WHY(IDOR): gbuId tenant-scoped laden.
        $gbu = GbuModel::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($gbuId);

        $gbu->status = GbuStatus::Freigegeben;
        $gbu->freigegeben_am = today();
        $gbu->save();

        session()->flash('status', 'GBU freigegeben.');
    }

    public function gbuFortschreiben(int $gbuId): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft'])), 403);

        $data = $this->validate([
            'fortschreibung_datum' => ['required', 'date', 'before_or_equal:today'],
        ]);

        // WHY(IDOR): gbuId tenant-scoped laden.
        $gbu = GbuModel::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($gbuId);

        app(GbuFortschreiben::class)->handle($gbu, $data['fortschreibung_datum']);

        $this->fortschreibung_datum = today()->toDateString();
        session()->flash('status', 'GBU fortgeschrieben.');
    }

    public function render(GbuMonitor $monitor)
    {
        return view('livewire.arbeitsschutz.gefaehrdungsbeurteilung', [
            'beurteilungen' => $monitor->status(),
            'faktoren' => Gefaehrdungsfaktor::cases(),
            'massnahmentypen' => Massnahmentyp::cases(),
            'ueberfaellig' => $monitor->ueberfaelligeAnzahl(),
        ]);
    }
}
