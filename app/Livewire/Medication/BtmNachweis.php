<?php

namespace App\Livewire\Medication;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\BtmBuchen;
use App\Domains\Medication\Enums\BtmVorgang;
use App\Domains\Medication\Models\BtmKonto;
use App\Domains\Medication\Models\BtmMonatsabschluss;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * BtM-Nachweisführung (§ 13 BtMVV): Konten je Bewohner+Substanz, append-only Buchungsjournal mit Bestand,
 * Vernichtung im Zwei-Zeugen-Prinzip und monatlicher Abschluss mit Arzt-Prüfung (gesperrt = read-only).
 */
#[Layout('layouts.app')]
class BtmNachweis extends Component
{
    public ?int $selected = null;

    // neues Konto
    public ?int $k_resident = null;

    public string $k_substanz = '';

    public string $k_staerke = '';

    public string $k_einheit = 'Stück';

    public string $k_arzt = '';

    // Buchung
    public string $b_vorgang = 'lieferung';

    public float $b_menge = 1;

    public string $b_datum = '';

    public string $b_lieferant = '';

    public string $b_empfaenger = '';

    public string $b_arzt = '';

    public string $b_zeuge_1 = '';

    public string $b_zeuge_2 = '';

    public string $b_vernichtungsmethode = '';

    public string $b_grund = '';

    // Monatsabschluss
    public string $ab_monat = '';

    public float $ab_ist = 0;

    public string $ab_geprueft_von = '';

    public string $ab_notiz = '';

    public function mount(): void
    {
        abort_unless($this->darf(), 403);
        $this->b_datum = today()->toDateString();
        $this->ab_monat = today()->startOfMonth()->toDateString();
    }

    private function darf(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function kontoAnlegen(): void
    {
        abort_unless($this->darf(), 403);
        $data = $this->validate([
            'k_resident' => ['required', 'integer', 'exists:residents,id'],
            'k_substanz' => ['required', 'string', 'max:160'],
            'k_staerke' => ['nullable', 'string', 'max:60'],
            'k_einheit' => ['required', 'string', 'max:20'],
            'k_arzt' => ['required', 'string', 'max:120'],
        ]);
        $konto = BtmKonto::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'resident_id' => $data['k_resident'], 'substanz' => $data['k_substanz'], 'staerke' => $data['k_staerke'] ?: null,
            'einheit' => $data['k_einheit'], 'arzt_name' => $data['k_arzt'], 'eroeffnet_am' => today()->toDateString(),
        ]);
        $this->reset('k_substanz', 'k_staerke', 'k_arzt');
        $this->selected = $konto->id;
        session()->flash('status', 'BtM-Konto angelegt.');
    }

    public function buchen(BtmBuchen $action): void
    {
        abort_unless($this->darf(), 403);
        $konto = BtmKonto::findOrFail($this->selected);
        $vorgang = BtmVorgang::from($this->b_vorgang);

        $this->validate([
            'b_vorgang' => ['required', 'in:'.implode(',', array_map(fn ($v) => $v->value, BtmVorgang::cases()))],
            'b_menge' => ['required', 'numeric', $vorgang === BtmVorgang::Korrektur ? 'not_in:0' : 'gt:0'],
            'b_datum' => ['required', 'date'],
            'b_zeuge_1' => [$vorgang->brauchtZeugen() ? 'required' : 'nullable', 'string', 'max:120'],
            'b_zeuge_2' => [$vorgang->brauchtZeugen() ? 'required' : 'nullable', 'string', 'max:120'],
            'b_grund' => [$vorgang === BtmVorgang::Korrektur ? 'required' : 'nullable', 'string', 'max:200'],
        ]);

        try {
            $action->handle($konto, $vorgang, (float) $this->b_menge, $this->b_datum, [
                'lieferant' => $this->b_lieferant ?: null, 'empfaenger' => $this->b_empfaenger ?: null,
                'arzt_name' => $this->b_arzt ?: null, 'zeuge_1' => $this->b_zeuge_1 ?: null, 'zeuge_2' => $this->b_zeuge_2 ?: null,
                'vernichtungsmethode' => $this->b_vernichtungsmethode ?: null, 'grund' => $this->b_grund ?: null,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->addError('b_menge', $e->getMessage());

            return;
        }
        $this->reset('b_menge', 'b_lieferant', 'b_empfaenger', 'b_arzt', 'b_zeuge_1', 'b_zeuge_2', 'b_vernichtungsmethode', 'b_grund');
        $this->b_menge = 1;
        session()->flash('status', 'Buchung erfasst.');
    }

    public function monatsabschluss(): void
    {
        abort_unless($this->darf(), 403);
        $konto = BtmKonto::findOrFail($this->selected);
        $this->validate([
            'ab_monat' => ['required', 'date'],
            'ab_ist' => ['required', 'numeric', 'min:0'],
            'ab_geprueft_von' => ['required', 'string', 'max:120'],
            'ab_notiz' => ['nullable', 'string', 'max:200'],
        ]);
        $soll = $konto->bestand();
        if (abs($soll - (float) $this->ab_ist) > 0.0001 && $this->ab_notiz === '') {
            $this->addError('ab_notiz', 'Bei einer Differenz ist eine Begründung Pflicht.');

            return;
        }
        BtmMonatsabschluss::updateOrCreate(
            ['btm_konto_id' => $konto->id, 'monat' => $this->ab_monat],
            [
                'tenant_id' => $konto->tenant_id, 'soll_bestand' => $soll, 'ist_bestand' => $this->ab_ist,
                'differenz_notiz' => $this->ab_notiz ?: null, 'geprueft_von' => $this->ab_geprueft_von,
                'pruef_datum' => today()->toDateString(), 'gesperrt_am' => now(),
            ],
        );
        $this->reset('ab_notiz');
        session()->flash('status', 'Monatsabschluss gespeichert und gesperrt.');
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $konten = BtmKonto::with('resident')->where('tenant_id', $tenantId)->orderBy('resident_id')->get();
        $konto = $this->selected ? $konten->firstWhere('id', $this->selected) : null;

        return view('livewire.medication.btm-nachweis', [
            'konten' => $konten,
            'konto' => $konto,
            'buchungen' => $konto ? $konto->buchungen()->with('durchfuehrer')->orderByDesc('lfd_nr')->get() : collect(),
            'abschluesse' => $konto ? $konto->abschluesse()->orderByDesc('monat')->get() : collect(),
            'bestand' => $konto?->bestand() ?? 0,
            'vorgaenge' => BtmVorgang::cases(),
            'bewohner' => Resident::where('tenant_id', $tenantId)->where('status', 'aktiv')->orderBy('name')->get(),
        ]);
    }
}
