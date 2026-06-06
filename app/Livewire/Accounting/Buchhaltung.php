<?php

namespace App\Livewire\Accounting;

use App\Domains\Accounting\Actions\Wareneingang;
use App\Domains\Accounting\Actions\Warenverbrauch;
use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Enums\KontoTyp;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Buchung;
use App\Domains\Accounting\Models\Konto;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Identity\Support\CurrentTenant;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Buchhaltung & Warenwirtschaft: Kontensalden (doppelte Buchführung), Journal aller Buchungen,
 * Lagerartikel je Abteilung sowie die operativen Aktionen Wareneingang/Verbrauch — diese buchen
 * automatisch (Soll Warenbestand an Haben Verbindlichkeiten bzw. Soll Abteilungs-Aufwand an Haben
 * Warenbestand), so verknüpft sich die Warenwirtschaft der Abteilungen mit der Buchhaltung.
 */
#[Layout('layouts.app')]
class Buchhaltung extends Component
{
    public string $a_name = '';

    public string $a_einheit = 'Stück';

    public string $a_abteilung = 'kueche';

    public ?float $a_mindestbestand = null;

    public ?float $a_einkaufspreis = null;

    public ?int $beweg_artikel = null;

    public ?float $beweg_menge = null;

    public ?float $beweg_preis = null;

    public string $beweg_notiz = '';

    public function mount(): void
    {
        abort_unless($this->darfSehen(), 403);
        AccountingDefaults::ensureFor(app(CurrentTenant::class)->id());
    }

    private function darfSehen(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'buchhaltung']));
    }

    public function artikelAnlegen(): void
    {
        abort_unless($this->darfSehen(), 403);
        $data = $this->validate([
            'a_name' => ['required', 'string', 'max:160'],
            'a_einheit' => ['required', 'string', 'max:40'],
            'a_abteilung' => ['required', 'in:'.implode(',', array_map(fn ($a) => $a->value, Abteilung::cases()))],
            'a_mindestbestand' => ['nullable', 'numeric', 'min:0'],
            'a_einkaufspreis' => ['nullable', 'numeric', 'min:0'],
        ]);

        Artikel::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'name' => $data['a_name'], 'einheit' => $data['a_einheit'], 'abteilung' => $data['a_abteilung'],
            'bestand' => 0, 'mindestbestand' => $data['a_mindestbestand'], 'einkaufspreis' => $data['a_einkaufspreis'],
        ]);
        $this->reset('a_name', 'a_mindestbestand', 'a_einkaufspreis');
        session()->flash('status', 'Artikel angelegt.');
    }

    public function wareneingang(Wareneingang $action): void
    {
        abort_unless($this->darfSehen(), 403);
        $data = $this->validate([
            'beweg_artikel' => ['required', 'integer', 'exists:artikel,id'],
            'beweg_menge' => ['required', 'numeric', 'gt:0'],
            'beweg_preis' => ['nullable', 'numeric', 'min:0'],
        ]);
        $artikel = Artikel::findOrFail($data['beweg_artikel']);
        $action->handle($artikel, (float) $data['beweg_menge'], $data['beweg_preis'] !== null ? (float) $data['beweg_preis'] : null, today()->toDateString(), $this->beweg_notiz ?: null);
        $this->reset('beweg_menge', 'beweg_preis', 'beweg_notiz');
        session()->flash('status', 'Wareneingang gebucht.');
    }

    public function verbrauch(Warenverbrauch $action): void
    {
        abort_unless($this->darfSehen(), 403);
        $data = $this->validate([
            'beweg_artikel' => ['required', 'integer', 'exists:artikel,id'],
            'beweg_menge' => ['required', 'numeric', 'gt:0'],
        ]);
        $artikel = Artikel::findOrFail($data['beweg_artikel']);
        $action->handle($artikel, (float) $data['beweg_menge'], today()->toDateString(), $this->beweg_notiz ?: null);
        $this->reset('beweg_menge', 'beweg_preis', 'beweg_notiz');
        session()->flash('status', 'Verbrauch gebucht.');
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $konten = Konto::where('tenant_id', $tenantId)->orderBy('nummer')->get();
        $salden = $konten->groupBy(fn (Konto $k) => $k->typ->value);

        return view('livewire.accounting.buchhaltung', [
            'kontenNachTyp' => $salden,
            'kontoTypen' => KontoTyp::cases(),
            'artikel' => Artikel::where('tenant_id', $tenantId)->orderBy('abteilung')->orderBy('name')->get(),
            'buchungen' => Buchung::with(['sollKonto', 'habenKonto'])->orderByDesc('datum')->orderByDesc('id')->limit(40)->get(),
            'abteilungen' => Abteilung::cases(),
        ]);
    }
}
