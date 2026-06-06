<?php

namespace App\Livewire\Facility;

use App\Domains\Facility\Enums\MpAnlage;
use App\Domains\Facility\Enums\MpVorkommnisArt;
use App\Domains\Facility\Models\Medizinprodukt;
use App\Domains\Facility\Models\MedizinproduktEinweisung;
use App\Domains\Facility\Models\MedizinproduktVorkommnis;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Medizinprodukte (MPBetreibV): Bestandsverzeichnis (§ 14) mit STK/MTK-Prüf-Ampel (§ 12/§ 15), Einweisungen
 * (§ 4/§ 11) und Medizinproduktebuch-Vorkommnissen (§ 13). Verwalten dürfen Leitung/Fachkraft/Haustechnik.
 */
#[Layout('layouts.app')]
class Medizinprodukte extends Component
{
    public ?int $selected = null;

    // Stammdaten-Formular (§ 14)
    public string $p_bezeichnung = '';

    public string $p_typ = '';

    public string $p_hersteller = '';

    public string $p_seriennummer = '';

    public string $p_inventarnummer = '';

    public string $p_anschaffungsjahr = '';

    public string $p_standort = '';

    public string $p_zuordnung = '';

    public string $p_anlage = 'keine';

    public string $p_inbetriebnahme = '';

    // Prüfung dokumentieren
    public string $stk_datum = '';

    public string $mtk_datum = '';

    // Einweisung
    public ?int $e_user = null;

    public string $e_datum = '';

    public string $e_durch = '';

    public string $e_art = 'ersteinweisung';

    // Vorkommnis
    public string $v_art = 'funktionsstoerung';

    public string $v_datum = '';

    public string $v_beschreibung = '';

    public string $v_massnahme = '';

    public function mount(): void
    {
        $this->e_datum = today()->toDateString();
        $this->v_datum = today()->toDateString();
        $this->selected ??= Medizinprodukt::orderBy('bezeichnung')->value('id');
    }

    public function darfVerwalten(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft', 'haustechnik']));
    }

    public function select(int $id): void
    {
        $this->selected = $id;
        $this->reset('stk_datum', 'mtk_datum');
    }

    public function anlegen(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $data = $this->validate([
            'p_bezeichnung' => ['required', 'string', 'max:160'],
            'p_typ' => ['nullable', 'string', 'max:120'],
            'p_hersteller' => ['nullable', 'string', 'max:160'],
            'p_seriennummer' => ['nullable', 'string', 'max:120'],
            'p_inventarnummer' => ['nullable', 'string', 'max:120'],
            'p_anschaffungsjahr' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'p_standort' => ['nullable', 'string', 'max:120'],
            'p_zuordnung' => ['nullable', 'string', 'max:120'],
            'p_anlage' => ['required', 'in:'.implode(',', array_map(fn ($a) => $a->value, MpAnlage::cases()))],
            'p_inbetriebnahme' => ['nullable', 'date'],
        ]);

        $anlage = MpAnlage::from($data['p_anlage']);
        $produkt = Medizinprodukt::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'bezeichnung' => $data['p_bezeichnung'],
            'typ' => $data['p_typ'] ?: null,
            'hersteller' => $data['p_hersteller'] ?: null,
            'seriennummer' => $data['p_seriennummer'] ?: null,
            'inventarnummer' => $data['p_inventarnummer'] ?: null,
            'anschaffungsjahr' => $data['p_anschaffungsjahr'] ?: null,
            'standort' => $data['p_standort'] ?: null,
            'zuordnung' => $data['p_zuordnung'] ?: null,
            'anlage' => $anlage,
            'inbetriebnahme_am' => $data['p_inbetriebnahme'] ?: null,
            'stk_intervall_monate' => $anlage->standardStkIntervall(),
        ]);

        $this->reset('p_bezeichnung', 'p_typ', 'p_hersteller', 'p_seriennummer', 'p_inventarnummer',
            'p_anschaffungsjahr', 'p_standort', 'p_zuordnung', 'p_inbetriebnahme');
        $this->p_anlage = 'keine';
        $this->selected = $produkt->id;
        session()->flash('status', 'Medizinprodukt im Bestandsverzeichnis angelegt.');
    }

    public function stkDokumentieren(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $this->validate(['stk_datum' => ['required', 'date']]);
        $this->current()->update(['letzte_stk' => $this->stk_datum]);
        $this->reset('stk_datum');
        session()->flash('status', 'Sicherheitstechnische Kontrolle (STK) dokumentiert.');
    }

    public function mtkDokumentieren(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $this->validate(['mtk_datum' => ['required', 'date']]);
        $this->current()->update(['letzte_mtk' => $this->mtk_datum]);
        $this->reset('mtk_datum');
        session()->flash('status', 'Messtechnische Kontrolle (MTK) dokumentiert.');
    }

    public function ausserBetrieb(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $this->current()->update(['ausser_betrieb_am' => today()->toDateString()]);
        session()->flash('status', 'Medizinprodukt außer Betrieb genommen (Aufbewahrung 5 Jahre).');
    }

    public function wiederInBetrieb(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $this->current()->update(['ausser_betrieb_am' => null]);
        session()->flash('status', 'Medizinprodukt wieder in Betrieb.');
    }

    public function einweisen(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $data = $this->validate([
            'e_user' => ['required', 'integer', 'exists:users,id'],
            'e_datum' => ['required', 'date'],
            'e_durch' => ['nullable', 'string', 'max:160'],
            'e_art' => ['required', 'in:ersteinweisung,folgeeinweisung'],
        ]);

        MedizinproduktEinweisung::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'medizinprodukt_id' => $this->current()->id,
            'user_id' => $data['e_user'],
            'eingewiesen_am' => $data['e_datum'],
            'eingewiesen_durch' => $data['e_durch'] ?: null,
            'art' => $data['e_art'],
        ]);
        $this->reset('e_user', 'e_durch');
        $this->e_art = 'ersteinweisung';
        session()->flash('status', 'Einweisung dokumentiert.');
    }

    public function vorkommnisMelden(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $data = $this->validate([
            'v_art' => ['required', 'in:'.implode(',', array_map(fn ($a) => $a->value, MpVorkommnisArt::cases()))],
            'v_datum' => ['required', 'date'],
            'v_beschreibung' => ['required', 'string', 'max:1000'],
            'v_massnahme' => ['nullable', 'string', 'max:1000'],
        ]);

        MedizinproduktVorkommnis::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'medizinprodukt_id' => $this->current()->id,
            'datum' => $data['v_datum'],
            'art' => $data['v_art'],
            'beschreibung' => $data['v_beschreibung'],
            'massnahme' => $data['v_massnahme'] ?: null,
            'gemeldet_von' => auth()->id(),
        ]);
        $this->reset('v_beschreibung', 'v_massnahme');
        $this->v_art = 'funktionsstoerung';
        session()->flash('status', 'Vorkommnis im Medizinproduktebuch erfasst.');
    }

    public function bfarmGemeldet(int $id): void
    {
        abort_unless($this->darfVerwalten(), 403);
        MedizinproduktVorkommnis::where('medizinprodukt_id', $this->current()->id)
            ->findOrFail($id)->update(['bfarm_gemeldet' => true]);
    }

    public function vorkommnisBehoben(int $id): void
    {
        abort_unless($this->darfVerwalten(), 403);
        MedizinproduktVorkommnis::where('medizinprodukt_id', $this->current()->id)
            ->findOrFail($id)->update(['behoben_am' => today()->toDateString()]);
    }

    private function current(): Medizinprodukt
    {
        return Medizinprodukt::findOrFail($this->selected);
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $produkte = Medizinprodukt::orderByRaw('ausser_betrieb_am is null desc')
            ->orderBy('bezeichnung')->get();

        $aktiv = $produkte->where('ausser_betrieb_am', null);
        $produkt = $this->selected ? $produkte->firstWhere('id', $this->selected) : null;

        return view('livewire.facility.medizinprodukte', [
            'produkte' => $produkte,
            'produkt' => $produkt,
            'einweisungen' => $produkt
                ? $produkt->einweisungen()->with('user')->orderByDesc('eingewiesen_am')->get()
                : collect(),
            'vorkommnisse' => $produkt
                ? $produkt->vorkommnisse()->with('melder')->orderByDesc('datum')->get()
                : collect(),
            'users' => User::where('tenant_id', $tenantId)->whereHas('employeeProfile')->orderBy('name')->get(),
            'anlagen' => MpAnlage::cases(),
            'vorkommnisArten' => MpVorkommnisArt::cases(),
            'ueberfaellig' => $aktiv->filter(fn (Medizinprodukt $m) => $m->pruefungUeberfaellig())->count(),
            'darfVerwalten' => $this->darfVerwalten(),
        ]);
    }
}
