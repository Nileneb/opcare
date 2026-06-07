<?php

namespace App\Livewire\Import;

use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lieferant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Import\Enums\ImportAktion;
use App\Domains\Import\Enums\ImportZeileStatus;
use App\Domains\Import\Models\ImportBatch;
use App\Domains\Import\Models\ImportZeile;
use App\Domains\Import\Services\ImportCommit;
use App\Domains\Import\Services\ImportMatching;
use App\Domains\Import\Support\SpaltenAlias;
use App\Domains\Import\Support\StammdatenParser;
use App\Support\Concerns\ScopesTenantValidation;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Datenimport extends Component
{
    use ScopesTenantValidation, WithFileUploads;

    public $datei;

    public string $ziel_typ = 'artikel';

    public string $anfangsbestand_modus = 'ebk';

    public ?int $batchId = null;

    /** @var array<string, string|null> Zielfeld → Original-Spalte */
    public array $mapping = [];

    /** @var array<int, array<string, mixed>> zeileId → editierbare Felder + aktion + matched_* */
    public array $ist = [];

    public function mount(): void
    {
        abort_unless($this->darf(), 403);
    }

    private function darf(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'buchhaltung']));
    }

    public function parsen(StammdatenParser $parser, ImportMatching $matching): void
    {
        abort_unless($this->darf(), 403);

        $this->validate([
            'datei' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $inhalt = file_get_contents($this->datei->getRealPath());
        $p = $parser::parseCsv((string) $inhalt);

        $tenantId = app(CurrentTenant::class)->id();

        $batch = ImportBatch::create([
            'tenant_id' => $tenantId,
            'dateiname' => $this->datei->getClientOriginalName(),
            'anfangsbestand_modus' => $this->anfangsbestand_modus,
            'mapping' => $p['mapping'],
            'status' => 'offen',
            'erstellt_von' => auth()->id(),
        ]);

        $batch->addMedia($this->datei->getRealPath())
            ->usingFileName($this->datei->getClientOriginalName())
            ->toMediaCollection('quelle');

        foreach ($p['zeilen'] as $roh) {
            $zeile = ImportZeile::create([
                'tenant_id' => $tenantId,
                'batch_id' => $batch->id,
                'roh' => $roh,
                'ziel_typ' => $this->ziel_typ,
                'name' => $this->feld($roh, $p['mapping'], 'name'),
                'einheit' => $this->feld($roh, $p['mapping'], 'einheit'),
                'abteilung' => $this->feld($roh, $p['mapping'], 'abteilung'),
                'einkaufspreis' => $this->feldDecimal($roh, $p['mapping'], 'einkaufspreis'),
                'mindestbestand' => $this->feldDecimal($roh, $p['mapping'], 'mindestbestand'),
                'bestand' => $this->feldDecimal($roh, $p['mapping'], 'bestand'),
                'einstandspreis' => $this->feldDecimal($roh, $p['mapping'], 'einstandspreis'),
                'pg_nummer' => $this->feld($roh, $p['mapping'], 'pg_nummer'),
                'lieferant_text' => $this->feld($roh, $p['mapping'], 'lieferant'),
                'charge_nr' => $this->feld($roh, $p['mapping'], 'charge_nr'),
                'mhd' => $this->feld($roh, $p['mapping'], 'mhd') ?: null,
                'aktion' => ImportAktion::Anlegen,
                'status' => ImportZeileStatus::Vorgeschlagen,
            ]);

            $matching->fuerZeile($zeile, $tenantId);
        }

        $this->batchId = $batch->id;
        $this->mapping = $p['mapping'];
        $this->ist = [];
        $this->reset('datei');
    }

    public function mappingAnwenden(ImportMatching $matching): void
    {
        abort_unless($this->darf(), 403);

        if ($this->batchId === null) {
            return;
        }

        $tenantId = app(CurrentTenant::class)->id();
        $zeilen = ImportZeile::where('batch_id', $this->batchId)->get();

        foreach ($zeilen as $zeile) {
            if (! $zeile->offen()) {
                continue;
            }

            $roh = $zeile->roh ?? [];

            $zeile->name = $this->feld($roh, $this->mapping, 'name');
            $zeile->einheit = $this->feld($roh, $this->mapping, 'einheit');
            $zeile->abteilung = $this->feld($roh, $this->mapping, 'abteilung');
            $zeile->einkaufspreis = $this->feldDecimal($roh, $this->mapping, 'einkaufspreis');
            $zeile->mindestbestand = $this->feldDecimal($roh, $this->mapping, 'mindestbestand');
            $zeile->bestand = $this->feldDecimal($roh, $this->mapping, 'bestand');
            $zeile->einstandspreis = $this->feldDecimal($roh, $this->mapping, 'einstandspreis');
            $zeile->pg_nummer = $this->feld($roh, $this->mapping, 'pg_nummer');
            $zeile->lieferant_text = $this->feld($roh, $this->mapping, 'lieferant');
            $zeile->charge_nr = $this->feld($roh, $this->mapping, 'charge_nr');
            $mhdWert = $this->feld($roh, $this->mapping, 'mhd');
            $zeile->mhd = $mhdWert ? Carbon::parse($mhdWert) : null;
            $zeile->save();

            $matching->fuerZeile($zeile, $tenantId);
        }

        $this->ist = [];

        $batch = ImportBatch::find($this->batchId);
        if ($batch) {
            $batch->mapping = $this->mapping;
            $batch->save();
        }

        session()->flash('status', 'Spalten-Mapping angewendet und Matching neu berechnet.');
    }

    public function bestaetigeZeile(int $zeileId, ImportCommit $commit): void
    {
        abort_unless($this->darf(), 403);

        $tenantId = app(CurrentTenant::class)->id();

        $aktion = $this->ist[$zeileId]['aktion'] ?? null;

        $data = $this->validate([
            "ist.{$zeileId}.aktion" => ['required', 'in:anlegen,mergen,ueberspringen'],
            "ist.{$zeileId}.matched_artikel_id" => ['nullable', 'integer', $this->tenantExists('artikel')],
            "ist.{$zeileId}.matched_lieferant_id" => ['nullable', 'integer', $this->tenantExists('lieferanten')],
            "ist.{$zeileId}.bestand" => ['nullable', 'numeric', 'min:0'],
            "ist.{$zeileId}.einstandspreis" => ['nullable', 'numeric', 'min:0'],
        ]);

        $zeile = ImportZeile::findOrFail($zeileId);
        $row = $data['ist'][$zeileId];

        $zeile->aktion = ImportAktion::from($row['aktion']);

        if (isset($row['matched_artikel_id']) && $row['matched_artikel_id'] !== '') {
            $zeile->matched_artikel_id = (int) $row['matched_artikel_id'];
        }

        if (isset($row['matched_lieferant_id']) && $row['matched_lieferant_id'] !== '') {
            $zeile->matched_lieferant_id = (int) $row['matched_lieferant_id'];
        }

        if (isset($this->ist[$zeileId]['name'])) {
            $zeile->name = $this->ist[$zeileId]['name'];
        }
        if (isset($this->ist[$zeileId]['einheit'])) {
            $zeile->einheit = $this->ist[$zeileId]['einheit'];
        }
        if (isset($this->ist[$zeileId]['abteilung'])) {
            $zeile->abteilung = $this->ist[$zeileId]['abteilung'];
        }

        if (isset($row['bestand']) && $row['bestand'] !== '') {
            $zeile->bestand = (float) $row['bestand'];
        }
        if (isset($row['einstandspreis']) && $row['einstandspreis'] !== '') {
            $zeile->einstandspreis = (float) $row['einstandspreis'];
        }

        $zeile->save();

        try {
            $commit->commit($zeile, $tenantId, auth()->id());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->addError("ist.{$zeileId}.bestand", $e->getMessage());

            return;
        }

        session()->flash('status', 'Zeile importiert.');
    }

    public function bestaetigeAlle(ImportCommit $commit): void
    {
        abort_unless($this->darf(), 403);

        if ($this->batchId === null) {
            return;
        }

        $tenantId = app(CurrentTenant::class)->id();

        $zeilen = ImportZeile::where('batch_id', $this->batchId)
            ->where('status', ImportZeileStatus::Vorgeschlagen->value)
            ->get();

        // Lieferanten-Zeilen zuerst, dann Artikel
        $lieferanten = $zeilen->where('ziel_typ', 'lieferant');
        $artikel = $zeilen->where('ziel_typ', 'artikel');

        foreach ($lieferanten->concat($artikel) as $zeile) {
            if (isset($this->ist[$zeile->id])) {
                $row = $this->ist[$zeile->id];
                if (isset($row['aktion'])) {
                    $zeile->aktion = ImportAktion::from($row['aktion']);
                }
                if (isset($row['matched_artikel_id']) && $row['matched_artikel_id'] !== '') {
                    $zeile->matched_artikel_id = (int) $row['matched_artikel_id'];
                }
                if (isset($row['matched_lieferant_id']) && $row['matched_lieferant_id'] !== '') {
                    $zeile->matched_lieferant_id = (int) $row['matched_lieferant_id'];
                }
                if (isset($row['bestand']) && $row['bestand'] !== '') {
                    $zeile->bestand = (float) $row['bestand'];
                }
                if (isset($row['einstandspreis']) && $row['einstandspreis'] !== '') {
                    $zeile->einstandspreis = (float) $row['einstandspreis'];
                }
                $zeile->save();
            }

            try {
                $commit->commit($zeile, $tenantId, auth()->id());
            } catch (\InvalidArgumentException|\RuntimeException $e) {
                $this->addError("ist.{$zeile->id}.bestand", $e->getMessage());
            }
        }

        session()->flash('status', 'Alle offenen Zeilen verarbeitet.');
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();

        $batch = $this->batchId ? ImportBatch::find($this->batchId) : null;
        $zeilen = $batch
            ? $batch->zeilen()->orderBy('id')->get()
            : collect();

        $artikel = Artikel::where('tenant_id', $tenantId)->orderBy('name')->get();
        $lieferanten = Lieferant::where('tenant_id', $tenantId)->orderBy('name')->get();

        $headerSpalten = [];
        if ($batch) {
            $ersteZeile = $zeilen->first();
            if ($ersteZeile && $ersteZeile->roh) {
                $headerSpalten = array_keys($ersteZeile->roh);
            } elseif ($batch->mapping) {
                $headerSpalten = array_values(array_filter($batch->mapping));
            }
        }

        $zielFelder = array_keys(SpaltenAlias::ALIASSE);

        $statusZaehler = [
            'vorgeschlagen' => $zeilen->where('status', ImportZeileStatus::Vorgeschlagen)->count(),
            'importiert' => $zeilen->where('status', ImportZeileStatus::Importiert)->count(),
            'uebersprungen' => $zeilen->where('status', ImportZeileStatus::Uebersprungen)->count(),
        ];

        // Default-Vorbelegung $ist je Zeile
        foreach ($zeilen as $z) {
            if (! isset($this->ist[$z->id])) {
                $bestKandidat = null;
                if ($z->ziel_typ === 'artikel') {
                    $bestKandidat = $z->kandidaten[0]['artikel_id'] ?? $z->matched_artikel_id;
                } elseif ($z->ziel_typ === 'lieferant') {
                    $bestKandidat = $z->matched_lieferant_id;
                }

                $this->ist[$z->id] = [
                    'name' => $z->name,
                    'einheit' => $z->einheit,
                    'abteilung' => $z->abteilung,
                    'bestand' => $z->bestand !== null ? (float) $z->bestand : null,
                    'einstandspreis' => $z->einstandspreis !== null ? (float) $z->einstandspreis : null,
                    'aktion' => $z->aktion->value,
                    'matched_artikel_id' => $z->ziel_typ === 'artikel' ? $bestKandidat : null,
                    'matched_lieferant_id' => $z->ziel_typ === 'lieferant' ? $bestKandidat : null,
                ];
            }
        }

        return view('livewire.import.datenimport', [
            'batch' => $batch,
            'zeilen' => $zeilen,
            'artikel' => $artikel,
            'lieferanten' => $lieferanten,
            'headerSpalten' => $headerSpalten,
            'zielFelder' => $zielFelder,
            'statusZaehler' => $statusZaehler,
        ]);
    }

    private function feld(array $roh, array $mapping, string $ziel): ?string
    {
        $spalte = $mapping[$ziel] ?? null;

        if ($spalte === null) {
            return null;
        }

        return isset($roh[$spalte]) && $roh[$spalte] !== '' ? (string) $roh[$spalte] : null;
    }

    private function feldDecimal(array $roh, array $mapping, string $ziel): ?string
    {
        $wert = $this->feld($roh, $mapping, $ziel);

        if ($wert === null) {
            return null;
        }

        return str_replace(',', '.', $wert);
    }
}
