<?php

namespace App\Livewire\Quality;

use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\BeschwerdeBereich;
use App\Domains\Quality\Enums\BeschwerdeKategorie;
use App\Domains\Quality\Enums\BeschwerdeQuelle;
use App\Domains\Quality\Enums\BeschwerdeStatus;
use App\Domains\Quality\Enums\VorgangArt;
use App\Domains\Quality\Models\Beschwerde;
use App\Domains\Quality\Notifications\BeschwerdeWeitergeleitet;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Beschwerde- & Gewaltschutz-Management (§ 113 SGB XI, Landes-WTG, Gewaltschutz § 5 SGB XI). Eingang
 * erfassen, vom QM bearbeiten und an die betroffene Abteilung weiterleiten — anonym oder namentlich,
 * je nach Wahl des Melders. Weiterleitung benachrichtigt die Bereichsrolle in-app. Gewaltvorfälle bleiben
 * bis zur dokumentierten Sofortmaßnahme rot.
 */
#[Layout('layouts.app')]
class Beschwerden extends Component
{
    #[Url]
    public ?int $fokus = null;

    public ?int $selected = null;

    // Eingang erfassen
    public string $b_titel = '';

    public string $b_beschreibung = '';

    public string $b_kategorie = 'beschwerde';

    public string $b_bereich = 'leitung';

    public string $b_quelle = 'bewohner';

    public string $b_sichtbarkeit = 'namentlich';

    public string $b_melder_name = '';

    public ?int $b_resident = null;

    public ?string $b_frist = null;

    public ?string $b_schweregrad = null;

    // Weiterleitung
    public string $w_bereich = 'kueche';

    public bool $w_anonym = false;

    public string $w_text = '';

    // Stellungnahme / Notiz / Maßnahme
    public string $v_text = '';

    public string $v_art = 'stellungnahme';

    // Sofortmaßnahme (Gewaltschutz)
    public string $sofort_text = '';

    // Abschluss
    public string $erg_text = '';

    public function mount(): void
    {
        abort_unless($this->darfErfassen() || $this->darfVerwalten(), 403);
        if ($this->fokus !== null) {
            $this->selected = $this->fokus;
        }
    }

    private function darfVerwalten(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    private function darfErfassen(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole([
            'admin', 'pflegefachkraft', 'pflegehilfskraft', 'betreuungskraft', 'kueche', 'haustechnik', 'buchhaltung',
        ]));
    }

    public function select(int $id): void
    {
        $this->selected = $id;
    }

    public function erfassen(): void
    {
        abort_unless($this->darfErfassen(), 403);
        $gewalt = $this->b_kategorie === BeschwerdeKategorie::Gewaltvorfall->value;
        $data = $this->validate([
            'b_titel' => ['required', 'string', 'max:160'],
            'b_beschreibung' => ['required', 'string', 'max:2000'],
            'b_kategorie' => ['required', 'in:'.$this->werte(BeschwerdeKategorie::cases())],
            'b_bereich' => ['required', 'in:'.$this->werte(BeschwerdeBereich::cases())],
            'b_quelle' => ['required', 'in:'.$this->werte(BeschwerdeQuelle::cases())],
            'b_sichtbarkeit' => ['required', 'in:anonym,namentlich'],
            'b_melder_name' => ['nullable', 'string', 'max:120'],
            'b_resident' => ['nullable', 'integer', 'exists:residents,id'],
            'b_frist' => ['nullable', 'date'],
            'b_schweregrad' => [$gewalt ? 'required' : 'nullable', 'in:niedrig,mittel,hoch'],
        ]);

        $anonym = $data['b_sichtbarkeit'] === 'anonym';
        Beschwerde::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'titel' => $data['b_titel'],
            'beschreibung' => $data['b_beschreibung'],
            'kategorie' => $data['b_kategorie'],
            'bereich' => $data['b_bereich'],
            'quelle' => $data['b_quelle'],
            'melder_sichtbarkeit' => $data['b_sichtbarkeit'],
            // Bei Anonymität keine Identität speichern (Datensparsamkeit, nicht nur Ausblenden).
            'melder_user_id' => $anonym ? null : auth()->id(),
            'melder_name' => $anonym ? null : ($data['b_melder_name'] ?: null),
            'betroffener_resident_id' => $data['b_resident'] ?: null,
            'eingang_am' => today()->toDateString(),
            'frist' => $data['b_frist'] ?: null,
            'status' => BeschwerdeStatus::Eingegangen,
            'schweregrad' => $gewalt ? $data['b_schweregrad'] : null,
        ]);
        $this->reset('b_titel', 'b_beschreibung', 'b_melder_name', 'b_resident', 'b_frist', 'b_schweregrad');
        session()->flash('status', 'Eingang erfasst.');
    }

    public function inBearbeitung(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $b = $this->current();
        $b->update(['status' => BeschwerdeStatus::InBearbeitung, 'bearbeiter_user_id' => auth()->id()]);
        $this->protokoll($b, VorgangArt::Statuswechsel, 'In Bearbeitung genommen.');
        session()->flash('status', 'In Bearbeitung.');
    }

    public function weiterleiten(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $data = $this->validate([
            'w_bereich' => ['required', 'in:'.$this->werte(BeschwerdeBereich::cases())],
            'w_text' => ['nullable', 'string', 'max:1000'],
        ]);
        $b = $this->current();
        $bereich = BeschwerdeBereich::from($data['w_bereich']);
        // Die Anonymitätswahl des Melders bindet: 'anonym' kann durch die Weiterleitung NICHT aufgehoben werden.
        $anonym = $b->anonym() || $this->w_anonym;

        $b->update(['status' => BeschwerdeStatus::Weitergeleitet, 'bereich' => $bereich]);
        $b->vorgaenge()->create([
            'tenant_id' => $b->tenant_id,
            'art' => VorgangArt::Weiterleitung,
            'an_bereich' => $bereich->value,
            'anonym' => $anonym,
            'text' => $data['w_text'] ?: null,
            'von_user_id' => auth()->id(),
        ]);

        $empfaenger = User::where('tenant_id', $b->tenant_id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $bereich->rollen()))
            ->where('id', '!=', auth()->id())
            ->get();
        Notification::send($empfaenger, new BeschwerdeWeitergeleitet(
            $b->id, $b->titel, $bereich->label(), $anonym, $anonym ? null : $b->melderAnzeige(),
        ));

        $this->reset('w_text', 'w_anonym');
        session()->flash('status', 'An '.$bereich->label().' weitergeleitet'.($anonym ? ' (anonym).' : '.').' '.$empfaenger->count().' Person(en) benachrichtigt.');
    }

    public function vorgangHinzufuegen(): void
    {
        $b = $this->current();
        abort_unless($this->darfVerwalten() || $this->darfBereich($b), 403);
        $data = $this->validate([
            'v_art' => ['required', 'in:notiz,stellungnahme,massnahme'],
            'v_text' => ['required', 'string', 'max:1000'],
        ]);
        $this->protokoll($b, VorgangArt::from($data['v_art']), $data['v_text']);
        if ($data['v_art'] === VorgangArt::Massnahme->value && $b->status === BeschwerdeStatus::Weitergeleitet) {
            $b->update(['status' => BeschwerdeStatus::InBearbeitung]);
        }
        $this->reset('v_text');
        session()->flash('status', 'Vorgang protokolliert.');
    }

    public function sofortmassnahmeSetzen(): void
    {
        abort_unless($this->darfVerwalten() || $this->darfBereich($this->current()), 403);
        $this->validate(['sofort_text' => ['required', 'string', 'max:1000']]);
        $b = $this->current();
        $b->update(['sofortmassnahme' => $this->sofort_text]);
        $this->protokoll($b, VorgangArt::Massnahme, 'Sofortmaßnahme (Gewaltschutz): '.$this->sofort_text);
        $this->reset('sofort_text');
        session()->flash('status', 'Sofortmaßnahme dokumentiert.');
    }

    public function erledigen(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $this->validate(['erg_text' => ['required', 'string', 'max:1000']]);
        $b = $this->current();
        $b->update(['status' => BeschwerdeStatus::Erledigt, 'erledigt_am' => today()->toDateString(), 'ergebnis' => $this->erg_text]);
        $this->protokoll($b, VorgangArt::Statuswechsel, 'Erledigt: '.$this->erg_text);
        $this->reset('erg_text');
        session()->flash('status', 'Beschwerde erledigt.');
    }

    public function ablehnen(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $this->validate(['erg_text' => ['required', 'string', 'max:1000']]);
        $b = $this->current();
        $b->update(['status' => BeschwerdeStatus::Abgelehnt, 'erledigt_am' => today()->toDateString(), 'ergebnis' => $this->erg_text]);
        $this->protokoll($b, VorgangArt::Statuswechsel, 'Abgelehnt: '.$this->erg_text);
        $this->reset('erg_text');
        session()->flash('status', 'Beschwerde abgelehnt.');
    }

    private function protokoll(Beschwerde $b, VorgangArt $art, string $text): void
    {
        $b->vorgaenge()->create([
            'tenant_id' => $b->tenant_id, 'art' => $art, 'text' => $text, 'von_user_id' => auth()->id(),
        ]);
    }

    private function current(): Beschwerde
    {
        return Beschwerde::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($this->selected);
    }

    /** @return array<int, string> */
    private function meineBereiche(): array
    {
        $rollen = auth()->user()?->getRoleNames()->all() ?? [];

        return collect(BeschwerdeBereich::cases())
            ->filter(fn (BeschwerdeBereich $b) => array_intersect($b->rollen(), $rollen) !== [])
            ->map(fn (BeschwerdeBereich $b) => $b->value)
            ->values()->all();
    }

    private function darfBereich(Beschwerde $b): bool
    {
        return in_array($b->bereich->value, $this->meineBereiche(), true)
            && $b->status === BeschwerdeStatus::Weitergeleitet;
    }

    /** @param array<int, \BackedEnum> $cases */
    private function werte(array $cases): string
    {
        return implode(',', array_map(fn ($c) => $c->value, $cases));
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $query = Beschwerde::with(['melder', 'resident', 'bearbeiter'])->where('tenant_id', $tenantId);

        if (! $this->darfVerwalten()) {
            $bereiche = $this->meineBereiche();
            $uid = auth()->id();
            $query->where(function ($q) use ($uid, $bereiche) {
                $q->where('melder_user_id', $uid)
                    ->orWhere(fn ($q2) => $q2->where('status', BeschwerdeStatus::Weitergeleitet)->whereIn('bereich', $bereiche));
            });
        }

        $beschwerden = $query->orderByDesc('id')->get();
        $b = $this->selected ? $beschwerden->firstWhere('id', $this->selected) : null;
        $offen = $beschwerden->filter(fn (Beschwerde $x) => in_array($x->ampel(), ['red', 'amber'], true))->count();

        return view('livewire.quality.beschwerden', [
            'beschwerden' => $beschwerden,
            'beschwerde' => $b,
            'vorgaenge' => $b ? $b->vorgaenge()->with('autor')->get() : collect(),
            'offen' => $offen,
            'darfVerwalten' => $this->darfVerwalten(),
            'darfBereich' => $b ? $this->darfBereich($b) : false,
            'kategorien' => BeschwerdeKategorie::cases(),
            'bereiche' => BeschwerdeBereich::cases(),
            'quellen' => BeschwerdeQuelle::cases(),
            'bewohner' => Resident::where('tenant_id', $tenantId)->where('status', 'aktiv')->orderBy('name')->get(),
        ]);
    }
}
