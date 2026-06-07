<?php

namespace App\Livewire\Accounting;

use App\Domains\Accounting\Actions\BestellungAnlegen;
use App\Domains\Accounting\Actions\BestellungWareneingang;
use App\Domains\Accounting\Enums\BestellStatus;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Bestellposition;
use App\Domains\Accounting\Models\Bestellung;
use App\Domains\Accounting\Models\Lieferant;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Accounting\Support\BedarfsVorschlag;
use App\Domains\Identity\Support\CurrentTenant;
use App\Support\Concerns\ScopesTenantValidation;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Beschaffung extends Component
{
    use ScopesTenantValidation;

    public ?int $b_lieferant = null;

    public ?string $b_datum = null;

    public string $b_notiz = '';

    /** @var array<int, array{artikel_id: string|int, menge: string|float, preis: string|float|null}> */
    public array $b_positionen = [
        ['artikel_id' => '', 'menge' => '', 'preis' => ''],
    ];

    public ?int $lief_pos_id = null;

    public ?float $lief_menge = null;

    public ?string $lief_charge = null;

    public ?string $lief_mhd = null;

    public function mount(): void
    {
        abort_unless($this->darfSehen(), 403);
        AccountingDefaults::ensureFor(app(CurrentTenant::class)->id());
        $this->b_datum = today()->toDateString();
    }

    private function darfSehen(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'buchhaltung']));
    }

    public function positionHinzufuegen(): void
    {
        $this->b_positionen[] = ['artikel_id' => '', 'menge' => '', 'preis' => ''];
    }

    public function positionEntfernen(int $index): void
    {
        unset($this->b_positionen[$index]);
        $this->b_positionen = array_values($this->b_positionen);
    }

    public function bedarfUebernehmen(BedarfsVorschlag $vorschlag): void
    {
        abort_unless($this->darfSehen(), 403);
        $tenantId = app(CurrentTenant::class)->id();
        $bedarfe = $vorschlag->fuer($tenantId);

        if ($bedarfe->isNotEmpty()) {
            $this->b_positionen = $bedarfe->map(fn ($eintrag) => [
                'artikel_id' => $eintrag['artikel']->id,
                'menge' => $eintrag['vorschlag'],
                'preis' => $eintrag['artikel']->einkaufspreis !== null ? (float) $eintrag['artikel']->einkaufspreis : '',
            ])->values()->toArray();
        }
    }

    public function bestellungAnlegen(BestellungAnlegen $action): void
    {
        abort_unless($this->darfSehen(), 403);

        $tenantId = app(CurrentTenant::class)->id();

        $data = $this->validate([
            'b_lieferant' => ['required', 'integer', $this->tenantExists('lieferanten')],
            'b_datum' => ['required', 'date'],
            'b_notiz' => ['nullable', 'string', 'max:500'],
            'b_positionen' => ['required', 'array', 'min:1'],
            'b_positionen.*.artikel_id' => ['required', 'integer', $this->tenantExists('artikel')],
            'b_positionen.*.menge' => ['required', 'numeric', 'gt:0'],
            'b_positionen.*.preis' => ['nullable', 'numeric', 'min:0'],
        ]);

        $positionen = array_map(fn ($p) => [
            'artikel_id' => (int) $p['artikel_id'],
            'menge' => (float) $p['menge'],
            'preis' => $p['preis'] !== '' && $p['preis'] !== null ? (float) $p['preis'] : null,
        ], $data['b_positionen']);

        $action->handle(
            (int) $data['b_lieferant'],
            $positionen,
            auth()->id(),
            $this->b_notiz ?: null,
            $data['b_datum'],
        );

        $this->reset('b_lieferant', 'b_notiz');
        $this->b_datum = today()->toDateString();
        $this->b_positionen = [['artikel_id' => '', 'menge' => '', 'preis' => '']];
        session()->flash('status', 'Bestellung angelegt.');
    }

    public function positionLiefern(BestellungWareneingang $action): void
    {
        abort_unless($this->darfSehen(), 403);

        $data = $this->validate([
            'lief_pos_id' => ['required', 'integer', $this->tenantExists('bestellpositionen')],
            'lief_menge' => ['required', 'numeric', 'gt:0'],
            'lief_charge' => ['nullable', 'string', 'max:120'],
            'lief_mhd' => ['nullable', 'date'],
        ]);

        $pos = Bestellposition::findOrFail($data['lief_pos_id']);

        try {
            $action->handle(
                $pos,
                (float) $data['lief_menge'],
                null,
                today()->toDateString(),
                $data['lief_charge'] ?: null,
                $data['lief_mhd'] ?: null,
            );
        } catch (InvalidArgumentException $e) {
            $this->addError('lief_menge', $e->getMessage());

            return;
        }

        $this->reset('lief_pos_id', 'lief_menge', 'lief_charge', 'lief_mhd');
        session()->flash('status', 'Wareneingang gebucht.');
    }

    public function render(BedarfsVorschlag $vorschlag)
    {
        $tenantId = app(CurrentTenant::class)->id();

        $bestellungen = Bestellung::with(['lieferant', 'positionen.artikel'])
            ->where('tenant_id', $tenantId)
            ->orderByDesc('bestelldatum')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $lieferanten = Lieferant::where('tenant_id', $tenantId)->orderBy('name')->get();
        $artikel = Artikel::where('tenant_id', $tenantId)->orderBy('name')->get();

        $offenePositionen = $bestellungen->flatMap(fn (Bestellung $b) => $b->positionen)
            ->filter(fn (Bestellposition $p) => $p->offen());

        $bedarfe = $vorschlag->fuer($tenantId);

        return view('livewire.accounting.beschaffung', [
            'bestellungen' => $bestellungen,
            'lieferanten' => $lieferanten,
            'artikel' => $artikel,
            'offenePositionen' => $offenePositionen,
            'bedarfe' => $bedarfe,
            'statusCases' => BestellStatus::cases(),
        ]);
    }
}
