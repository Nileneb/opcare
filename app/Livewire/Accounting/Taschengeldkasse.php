<?php

namespace App\Livewire\Accounting;

use App\Domains\Accounting\Actions\TreuhandBuchen;
use App\Domains\Accounting\Enums\BarbetragKategorie;
use App\Domains\Accounting\Enums\TreuhandVorgang;
use App\Domains\Accounting\Models\Treuhandbuchung;
use App\Domains\Accounting\Models\Treuhandbudget;
use App\Domains\Accounting\Models\Treuhandkonto;
use App\Domains\Accounting\Models\TreuhandMonatsabschluss;
use App\Domains\Accounting\Support\BudgetMonitor;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Taschengeld-/Barbetragsverwaltung (§ 27b SGB XII). Treuhandkonten je Bewohner, append-only Buchungsjournal
 * mit Saldo, Budget-Setzungen je Kategorie mit Warn-/Sperr-Ampel und monatliche Rechnungslegung. Zugriff nur
 * für Verwaltung/Buchhaltung (Userrole) — treuhänderische Verwaltung getrennt vom Einrichtungsvermögen.
 */
#[Layout('layouts.app')]
class Taschengeldkasse extends Component
{
    public ?int $selected = null;

    // neues Konto
    public ?int $k_resident = null;

    public string $k_iban = '';

    // Buchung
    public string $b_vorgang = 'einzahlung';

    public float $b_betrag = 0;

    public string $b_datum = '';

    public string $b_kategorie = '';

    public string $b_zweck = '';

    public string $b_beleg_nr = '';

    public string $b_grund = '';

    public ?int $b_korrigiert_buchung_id = null;

    // Budget-Setzung
    public string $bg_kategorie = '';

    public float $bg_limit = 0;

    public int $bg_warn = 80;

    public bool $bg_sperre = false;

    // Monatsabschluss
    public string $ab_monat = '';

    public string $ab_erstellt_von = '';

    public function mount(): void
    {
        abort_unless($this->darf(), 403);
        $this->b_datum = today()->toDateString();
        $this->ab_monat = today()->startOfMonth()->toDateString();
        $this->selected ??= Treuhandkonto::where('tenant_id', app(CurrentTenant::class)->id())->orderBy('resident_id')->value('id');
    }

    private function darf(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'buchhaltung']));
    }

    public function kontoAnlegen(): void
    {
        abort_unless($this->darf(), 403);
        $data = $this->validate([
            'k_resident' => ['required', 'integer', 'exists:residents,id'],
            'k_iban' => ['nullable', 'string', 'max:34'],
        ]);
        $tenantId = app(CurrentTenant::class)->id();
        if (Treuhandkonto::where('tenant_id', $tenantId)->where('resident_id', $data['k_resident'])->exists()) {
            $this->addError('k_resident', 'Für diese:n Bewohner:in besteht bereits ein Treuhandkonto.');

            return;
        }
        $konto = Treuhandkonto::create([
            'tenant_id' => $tenantId,
            'resident_id' => $data['k_resident'],
            'iban' => $data['k_iban'] ?: null,
            'eroeffnet_am' => today()->toDateString(),
        ]);
        $this->reset('k_resident', 'k_iban');
        $this->selected = $konto->id;
        session()->flash('status', 'Treuhandkonto angelegt.');
    }

    public function buchen(TreuhandBuchen $action): void
    {
        abort_unless($this->darf(), 403);
        $konto = $this->aktuellesKonto();
        $vorgang = TreuhandVorgang::from($this->b_vorgang);

        $this->validate([
            'b_vorgang' => ['required', 'in:'.implode(',', array_map(fn ($v) => $v->value, TreuhandVorgang::cases()))],
            'b_betrag' => ['required', 'numeric', $vorgang === TreuhandVorgang::Korrektur ? 'not_in:0' : 'gt:0'],
            'b_datum' => ['required', 'date'],
            'b_kategorie' => [$vorgang === TreuhandVorgang::Auszahlung ? 'required' : 'nullable', 'in:'.implode(',', array_map(fn ($k) => $k->value, BarbetragKategorie::cases()))],
            'b_zweck' => ['required', 'string', 'max:200'],
            'b_beleg_nr' => ['nullable', 'string', 'max:60'],
            'b_grund' => [$vorgang === TreuhandVorgang::Korrektur ? 'required' : 'nullable', 'string', 'max:200'],
            'b_korrigiert_buchung_id' => [$vorgang === TreuhandVorgang::Korrektur ? 'required' : 'nullable', 'integer'],
        ]);

        try {
            $action->handle($konto, $vorgang, (float) $this->b_betrag, $this->b_datum, [
                'kategorie' => $this->b_kategorie ?: null,
                'zweck' => $this->b_zweck,
                'beleg_nr' => $this->b_beleg_nr ?: null,
                'grund' => $this->b_grund ?: null,
                'korrigiert_buchung_id' => $this->b_korrigiert_buchung_id,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->addError('b_betrag', $e->getMessage());

            return;
        }
        $this->reset('b_betrag', 'b_kategorie', 'b_zweck', 'b_beleg_nr', 'b_grund', 'b_korrigiert_buchung_id');
        session()->flash('status', 'Buchung erfasst.');
    }

    public function budgetSetzen(): void
    {
        abort_unless($this->darf(), 403);
        $konto = $this->aktuellesKonto();
        $this->validate([
            'bg_kategorie' => ['nullable', 'in:'.implode(',', array_map(fn ($k) => $k->value, BarbetragKategorie::cases()))],
            'bg_limit' => ['required', 'numeric', 'gt:0'],
            'bg_warn' => ['required', 'integer', 'min:1', 'max:100'],
        ]);
        Treuhandbudget::updateOrCreate(
            ['treuhand_konto_id' => $konto->id, 'kategorie' => $this->bg_kategorie ?: null],
            ['tenant_id' => $konto->tenant_id, 'limit_betrag' => $this->bg_limit, 'warn_prozent' => $this->bg_warn, 'sperre' => $this->bg_sperre],
        );
        $this->reset('bg_kategorie', 'bg_limit', 'bg_sperre');
        $this->bg_warn = 80;
        session()->flash('status', 'Budget gespeichert.');
    }

    public function budgetLoeschen(int $id): void
    {
        abort_unless($this->darf(), 403);
        $konto = $this->aktuellesKonto();
        Treuhandbudget::where('treuhand_konto_id', $konto->id)->where('id', $id)->delete();
        session()->flash('status', 'Budget entfernt.');
    }

    public function monatsabschluss(): void
    {
        abort_unless($this->darf(), 403);
        $konto = $this->aktuellesKonto();
        $this->validate([
            'ab_monat' => ['required', 'date'],
            'ab_erstellt_von' => ['required', 'string', 'max:120'],
        ]);
        $start = Carbon::parse($this->ab_monat)->startOfMonth()->toDateString();
        $ende = Carbon::parse($this->ab_monat)->endOfMonth()->toDateString();

        $anfang = (float) (Treuhandbuchung::where('treuhand_konto_id', $konto->id)->where('datum', '<', $start)->orderByDesc('lfd_nr')->value('saldo_nach') ?? 0.0);
        $imMonat = Treuhandbuchung::where('treuhand_konto_id', $konto->id)->whereBetween('datum', [$start, $ende]);
        $ein = (float) (clone $imMonat)->where('betrag', '>', 0)->sum('betrag');
        $aus = (float) (clone $imMonat)->where('betrag', '<', 0)->sum('betrag');

        TreuhandMonatsabschluss::updateOrCreate(
            ['treuhand_konto_id' => $konto->id, 'monat' => $start],
            [
                'tenant_id' => $konto->tenant_id,
                'anfangsbestand' => round($anfang, 2),
                'summe_einzahlungen' => round($ein, 2),
                'summe_auszahlungen' => round(-$aus, 2),
                'endbestand' => round($anfang + $ein + $aus, 2),
                'erstellt_von' => $this->ab_erstellt_von,
                'gesperrt_am' => now(),
            ],
        );
        session()->flash('status', 'Monatsabschluss erstellt und gesperrt.');
    }

    private function aktuellesKonto(): Treuhandkonto
    {
        return Treuhandkonto::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($this->selected);
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $konten = Treuhandkonto::with('resident')->where('tenant_id', $tenantId)->orderBy('resident_id')->get();
        $konto = $this->selected ? $konten->firstWhere('id', $this->selected) : null;

        $monitor = app(BudgetMonitor::class);
        $budgetStatus = [];
        if ($konto) {
            foreach ($konto->budgets as $budget) {
                $budgetStatus[$budget->id] = $monitor->status($konto, $budget->kategorie, today()->toDateString());
            }
        }

        return view('livewire.accounting.taschengeldkasse', [
            'konten' => $konten,
            'konto' => $konto,
            'saldo' => $konto?->saldo() ?? 0.0,
            'buchungen' => $konto ? $konto->buchungen()->with('erfasser')->orderByDesc('lfd_nr')->get() : collect(),
            'budgets' => $konto ? $konto->budgets()->orderBy('kategorie')->get() : collect(),
            'budgetStatus' => $budgetStatus,
            'abschluesse' => $konto ? $konto->abschluesse()->orderByDesc('monat')->get() : collect(),
            'vorgaenge' => TreuhandVorgang::cases(),
            'kategorien' => BarbetragKategorie::cases(),
            'bewohner' => Resident::where('tenant_id', $tenantId)->where('status', 'aktiv')->orderBy('name')->get(),
        ]);
    }
}
