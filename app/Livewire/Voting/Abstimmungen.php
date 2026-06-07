<?php

namespace App\Livewire\Voting;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Quality\Models\Gremium;
use App\Domains\Voting\Enums\Abstimmungsart;
use App\Domains\Voting\Enums\AbstimmungStatus;
use App\Domains\Voting\Enums\Elektorat;
use App\Domains\Voting\Enums\Stimmodus;
use App\Domains\Voting\Models\Abstimmung;
use App\Domains\Voting\Services\AbstimmungStarten;
use App\Domains\Voting\Services\Auszaehlung;
use App\Domains\Voting\Services\StimmeAbgeben;
use App\Support\Concerns\ScopesTenantValidation;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Abstimmungen extends Component
{
    use ScopesTenantValidation;

    public string $titel = '';

    public string $beschreibung = '';

    public string $elektorat = '';

    public ?int $gremium_id = null;

    public string $modus = '';

    public string $art = '';

    public bool $mehrfachauswahl = false;

    public string $ende_am = '';

    /** @var array<int, string> */
    public array $optionen = ['', ''];

    /** @var array<int, array<int, int>> */
    public array $auswahl = [];

    public ?string $belegToken = null;

    public ?int $belegFuerAbstimmungId = null;

    public function mount(): void {}

    private function darfAnlegen(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function optionHinzufuegen(): void
    {
        abort_unless($this->darfAnlegen(), 403);
        $this->optionen[] = '';
    }

    public function optionEntfernen(int $index): void
    {
        abort_unless($this->darfAnlegen(), 403);
        if (count($this->optionen) > 2) {
            array_splice($this->optionen, $index, 1);
            $this->optionen = array_values($this->optionen);
        }
    }

    public function anlegen(AbstimmungStarten $svc): void
    {
        abort_unless($this->darfAnlegen(), 403);

        $rules = [
            'titel' => ['required', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string', 'max:2000'],
            'elektorat' => ['required', 'in:'.implode(',', array_column(Elektorat::cases(), 'value'))],
            'gremium_id' => ['nullable', 'integer', $this->tenantExists('gremien')],
            'modus' => ['required', 'in:'.implode(',', array_column(Stimmodus::cases(), 'value'))],
            'art' => ['required', 'in:'.implode(',', array_column(Abstimmungsart::cases(), 'value'))],
            'mehrfachauswahl' => ['boolean'],
            'ende_am' => ['nullable', 'date', 'after:now'],
            'optionen' => ['required', 'array', 'min:2'],
            'optionen.*' => ['required', 'string', 'max:255'],
        ];

        $this->validate($rules);

        $daten = [
            'titel' => $this->titel,
            'beschreibung' => $this->beschreibung ?: null,
            'elektorat' => $this->elektorat,
            'gremium_id' => $this->gremium_id,
            'modus' => $this->modus,
            'art' => $this->art,
            'mehrfachauswahl' => $this->mehrfachauswahl,
            'ende_am' => $this->ende_am ?: null,
            'status' => AbstimmungStatus::Entwurf,
            'ergebnis_sichtbar' => false,
        ];

        $userId = auth()->id();

        try {
            $abstimmung = $svc->handle($daten, $this->optionen, $userId !== null ? (int) $userId : null);
            $svc->eroeffne($abstimmung);
        } catch (\InvalidArgumentException $e) {
            $this->addError('modus', $e->getMessage());

            return;
        }

        $this->reset('titel', 'beschreibung', 'elektorat', 'gremium_id', 'modus', 'art', 'mehrfachauswahl', 'ende_am');
        $this->optionen = ['', ''];
        session()->flash('status', 'Abstimmung eröffnet.');
    }

    public function abstimmen(int $abstimmungId, StimmeAbgeben $svc): void
    {
        $tenantId = app(CurrentTenant::class)->id();

        $a = Abstimmung::where('tenant_id', $tenantId)->findOrFail($abstimmungId);

        $optionIds = array_values(array_filter((array) ($this->auswahl[$abstimmungId] ?? []), fn ($v) => $v !== null && $v !== false && $v !== ''));

        if (empty($optionIds)) {
            $this->addError("auswahl.{$abstimmungId}", 'Bitte eine Option auswählen.');

            return;
        }

        $optionIds = array_map('intval', $optionIds);

        $currentUserId = (int) auth()->id();

        try {
            $token = $svc->handle($a, 'user', $currentUserId, $optionIds);
            $this->belegToken = $token;
            $this->belegFuerAbstimmungId = $abstimmungId;
        } catch (\InvalidArgumentException $e) {
            $this->addError("auswahl.{$abstimmungId}", $e->getMessage());
        }
    }

    public function render(Auszaehlung $auszaehlung): View
    {
        $tenantId = app(CurrentTenant::class)->id();
        $userId = auth()->id();

        $abstimmbar = Abstimmung::where('tenant_id', $tenantId)
            ->where('status', AbstimmungStatus::Offen)
            ->whereHas('wahlteilnahmen', fn ($q) => $q->where('user_id', $userId)->where('hat_abgestimmt', false))
            ->with('optionen')
            ->orderByDesc('id')
            ->get();

        $abgeschlossen = Abstimmung::where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('status', AbstimmungStatus::Geschlossen)
                ->orWhere('status', AbstimmungStatus::Offen))
            ->where(fn ($q) => $q
                ->where('ergebnis_sichtbar', true)
                ->orWhere('erstellt_von', $userId)
            )
            ->with('optionen', 'wahlteilnahmen')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Abstimmung $a) => ! $abstimmbar->contains('id', $a->id));

        if ($this->darfAnlegen()) {
            $abgeschlossen = Abstimmung::where('tenant_id', $tenantId)
                ->whereNotIn('id', $abstimmbar->pluck('id'))
                ->with('optionen', 'wahlteilnahmen')
                ->orderByDesc('id')
                ->get();
        }

        $ergebnisse = [];
        foreach ($abgeschlossen as $a) {
            $ergebnisse[$a->id] = $auszaehlung->ergebnis($a);
        }

        $gremien = Gremium::where('tenant_id', $tenantId)->orderBy('name')->get();

        return view('livewire.voting.abstimmungen', [
            'abstimmbar' => $abstimmbar,
            'abgeschlossen' => $abgeschlossen,
            'ergebnisse' => $ergebnisse,
            'gremien' => $gremien,
            'darfAnlegen' => $this->darfAnlegen(),
            'elektoratOptionen' => Elektorat::cases(),
            'modusOptionen' => Stimmodus::cases(),
            'artOptionen' => Abstimmungsart::cases(),
        ]);
    }
}
