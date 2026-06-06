<?php

namespace App\Livewire\Facility;

use App\Domains\Facility\Enums\AssetKategorie;
use App\Domains\Facility\Enums\MeldungPrioritaet;
use App\Domains\Facility\Enums\MeldungStatus;
use App\Domains\Facility\Models\FacilityAsset;
use App\Domains\Facility\Models\FacilityMeldung;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Haustechnik (DIN 31051): jede:r Mitarbeitende meldet einen Mangel; die Haustechnik/Leitung arbeitet die
 * Meldungs-Queue ab (offen → in Arbeit → erledigt) und führt den Wartungsplan mit Prüffristen.
 */
#[Layout('layouts.app')]
class Haustechnik extends Component
{
    public string $m_titel = '';

    public string $m_beschreibung = '';

    public string $m_standort = '';

    public string $m_prioritaet = 'mittel';

    public ?int $m_asset = null;

    public ?int $erledigeId = null;

    public string $erledigt_notiz = '';

    public string $a_bezeichnung = '';

    public string $a_kategorie = 'gebaeude';

    public string $a_standort = '';

    public string $a_norm = '';

    public ?int $a_intervall = null;

    public string $a_letzte = '';

    public function darfVerwalten(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft', 'haustechnik']));
    }

    public function melden(): void
    {
        $data = $this->validate([
            'm_titel' => ['required', 'string', 'max:160'],
            'm_beschreibung' => ['nullable', 'string', 'max:1000'],
            'm_standort' => ['nullable', 'string', 'max:120'],
            'm_prioritaet' => ['required', 'in:'.implode(',', array_map(fn ($p) => $p->value, MeldungPrioritaet::cases()))],
            'm_asset' => ['nullable', 'integer', 'exists:facility_assets,id'],
        ]);

        FacilityMeldung::create([
            'titel' => $data['m_titel'], 'beschreibung' => $data['m_beschreibung'] ?: null,
            'standort' => $data['m_standort'] ?: null, 'prioritaet' => $data['m_prioritaet'],
            'asset_id' => $data['m_asset'], 'gemeldet_von' => auth()->id(),
        ]);
        $this->reset('m_titel', 'm_beschreibung', 'm_standort', 'm_prioritaet', 'm_asset');
        $this->m_prioritaet = 'mittel';
        session()->flash('status', 'Mangel gemeldet — die Haustechnik kümmert sich.');
    }

    public function uebernehmen(int $id): void
    {
        abort_unless($this->darfVerwalten(), 403);
        FacilityMeldung::findOrFail($id)->update(['status' => MeldungStatus::InArbeit]);
    }

    public function erledigenStart(int $id): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $this->erledigeId = $id;
        $this->erledigt_notiz = '';
    }

    public function erledigen(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $this->validate(['erledigt_notiz' => ['nullable', 'string', 'max:500']]);
        FacilityMeldung::findOrFail($this->erledigeId)->update([
            'status' => MeldungStatus::Erledigt, 'erledigt_am' => now()->toDateString(),
            'erledigt_notiz' => $this->erledigt_notiz ?: null,
        ]);
        $this->reset('erledigeId', 'erledigt_notiz');
    }

    public function geprueft(int $assetId): void
    {
        abort_unless($this->darfVerwalten(), 403);
        FacilityAsset::findOrFail($assetId)->update(['letzte_pruefung' => now()->toDateString()]);
        session()->flash('status', 'Prüfung dokumentiert.');
    }

    public function assetAnlegen(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $data = $this->validate([
            'a_bezeichnung' => ['required', 'string', 'max:160'],
            'a_kategorie' => ['required', 'in:'.implode(',', array_map(fn ($k) => $k->value, AssetKategorie::cases()))],
            'a_standort' => ['nullable', 'string', 'max:120'],
            'a_norm' => ['nullable', 'string', 'max:60'],
            'a_intervall' => ['nullable', 'integer', 'min:1', 'max:120'],
            'a_letzte' => ['nullable', 'date'],
        ]);
        FacilityAsset::create([
            'bezeichnung' => $data['a_bezeichnung'], 'kategorie' => $data['a_kategorie'],
            'standort' => $data['a_standort'] ?: null, 'norm' => $data['a_norm'] ?: null,
            'pruefintervall_monate' => $data['a_intervall'], 'letzte_pruefung' => $data['a_letzte'] ?: null,
        ]);
        $this->reset('a_bezeichnung', 'a_standort', 'a_norm', 'a_intervall', 'a_letzte');
        session()->flash('status', 'Betriebsmittel angelegt.');
    }

    public function render()
    {
        $offene = FacilityMeldung::with('melder', 'asset')
            ->where('status', '!=', MeldungStatus::Erledigt)->latest()->get();
        $assets = FacilityAsset::where('aktiv', true)->orderBy('bezeichnung')->get()
            ->sortBy(function (FacilityAsset $a) {
                $np = $a->naechstePruefung();

                return $np === null ? PHP_INT_MAX : $np->timestamp;
            })->values();

        return view('livewire.facility.haustechnik', [
            'offene' => $offene,
            'erledigtKuerzlich' => FacilityMeldung::with('melder')->where('status', MeldungStatus::Erledigt)->latest('erledigt_am')->limit(5)->get(),
            'assets' => $assets,
            'ueberfaellig' => $assets->filter(fn (FacilityAsset $a) => $a->ueberfaellig())->count(),
            'prioritaeten' => MeldungPrioritaet::cases(),
            'kategorien' => AssetKategorie::cases(),
            'darfVerwalten' => $this->darfVerwalten(),
        ]);
    }
}
