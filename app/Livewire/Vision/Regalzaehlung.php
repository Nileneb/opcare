<?php

namespace App\Livewire\Vision;

use App\Domains\Accounting\Enums\InventurStatus;
use App\Domains\Accounting\Models\Inventur;
use App\Domains\Accounting\Models\Inventurposition;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Vision\Contracts\VisionClient;
use App\Domains\Vision\Models\ProductLabel;
use App\Domains\Vision\Models\RegalAufnahme;
use App\Domains\Vision\Models\RegalDetection;
use App\Domains\Vision\Models\YoloModell;
use App\Support\Concerns\ScopesTenantValidation;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Regalzaehlung extends Component
{
    use ScopesTenantValidation, WithFileUploads;

    public $foto;

    /** @var array<int, float|string|null> editierbare Zähl-Korrektur je Detektions-ID */
    public array $ist = [];

    public ?int $inventurId = null;

    public ?string $keinModellHinweis = null;

    public ?string $trainingJobId = null;

    public ?array $trainingStatus = null;

    public ?array $annotierVorschlaege = null;

    public function mount(): void
    {
        abort_unless($this->darf(), 403);
    }

    private function darf(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'buchhaltung', 'pflegefachkraft']));
    }

    public function zaehlen(VisionClient $vision): void
    {
        abort_unless($this->darf(), 403);
        $this->validate(['foto' => ['required', 'image', 'max:8192']]);

        $tenantId = app(CurrentTenant::class)->id();

        $modell = YoloModell::aktivesFuer($tenantId)->first();
        if ($modell !== null) {
            $modelPath = $modell->model_path;
            $this->keinModellHinweis = null;
        } else {
            $modelPath = config('vision.default_model', '/models/base/yolo11n.pt');
            $this->keinModellHinweis = 'Kein trainiertes Modell vorhanden — Basis-Erkennung wird verwendet.';
        }

        // WHY(GC-FALLE): getRealPath()-Ergebnis sofort in Variable speichern, bevor File-Handle GC-gesammelt werden kann.
        $realPath = $this->foto->getRealPath();
        $b64 = base64_encode(file_get_contents($realPath));

        $ergebnis = $vision->detect($b64, $modelPath);

        $aufnahme = RegalAufnahme::create([
            'tenant_id' => $tenantId,
            'erstellt_von' => auth()->id(),
        ]);

        // WHY(GC-FALLE): Foto-File vor dem addMedia-Aufruf im Scope halten.
        $fotoFile = $this->foto;
        $aufnahme->addMedia($fotoFile->getRealPath())
            ->usingFileName('regal-'.now()->format('YmdHis').'.jpg')
            ->toMediaCollection('foto');

        $labels = ProductLabel::where('tenant_id', $tenantId)->get()->keyBy('yolo_label');

        foreach ($ergebnis['counts'] as $yoloLabel => $anzahl) {
            $pl = $labels->get($yoloLabel);
            $artikelId = $pl?->artikel_id;
            $mengeVorschlag = $pl !== null ? $pl->mengeFuer($anzahl) : (float) $anzahl;

            $labelDetections = array_filter(
                $ergebnis['detections'] ?? [],
                fn (array $d) => ($d['label'] ?? '') === $yoloLabel
            );
            $confidence = count($labelDetections) > 0
                ? max(array_column($labelDetections, 'confidence'))
                : 0.0;

            $det = RegalDetection::create([
                'tenant_id' => $tenantId,
                'aufnahme_id' => $aufnahme->id,
                'label' => $yoloLabel,
                'confidence' => $confidence,
                'artikel_id' => $artikelId,
                'menge_vorschlag' => $mengeVorschlag,
            ]);

            if (! isset($this->ist[$det->id])) {
                $this->ist[$det->id] = $mengeVorschlag;
            }
        }

        $this->reset('foto');
        session()->flash('status', 'Regal gescannt — Erkennungen zur Prüfung bereit.');
    }

    public function buchen(int $detektionId): void
    {
        abort_unless($this->darf(), 403);

        $data = $this->validate([
            "ist.{$detektionId}" => ['required', 'numeric', 'min:0'],
            'inventurId' => ['required', 'integer', $this->tenantExists('inventuren')],
        ]);

        $menge = (float) $data['ist'][$detektionId];

        $det = RegalDetection::findOrFail($detektionId);
        $tenantId = app(CurrentTenant::class)->id();

        abort_unless((int) $det->tenant_id === $tenantId, 403);

        if ($det->artikel_id === null) {
            $this->addError("ist.{$detektionId}", 'Detektion ist keinem Artikel zugeordnet — Buchen nicht möglich.');

            return;
        }

        $inventur = Inventur::findOrFail($this->inventurId);

        if (! $inventur->offen()) {
            $this->addError('inventurId', 'Die gewählte Inventur ist bereits abgeschlossen.');

            return;
        }

        $position = Inventurposition::where('inventur_id', $inventur->id)
            ->where('artikel_id', $det->artikel_id)
            ->first();

        if ($position === null) {
            $this->addError("ist.{$detektionId}", 'Artikel ist in der gewählten Inventur nicht als Position erfasst.');

            return;
        }

        $position->update([
            'ist_menge' => $menge,
            'gezaehlt_von' => auth()->id(),
            'gezaehlt_am' => now(),
        ]);

        session()->flash('status', "Menge {$menge} für Artikel '{$position->artikel->name}' in Inventur gebucht.");
    }

    public function annotieren(VisionClient $vision): void
    {
        abort_unless($this->darf(), 403);
        $this->validate(['foto' => ['required', 'image', 'max:8192']]);

        $tenantId = app(CurrentTenant::class)->id();

        $realPath = $this->foto->getRealPath();
        $b64 = base64_encode(file_get_contents($realPath));

        $ergebnis = $vision->autoAnnotate($b64);

        $aufnahme = RegalAufnahme::create([
            'tenant_id' => $tenantId,
            'erstellt_von' => auth()->id(),
            'notiz' => 'labeling:'.json_encode($ergebnis['suggestions']),
        ]);

        $fotoFile = $this->foto;
        $aufnahme->addMedia($fotoFile->getRealPath())
            ->usingFileName('label-'.now()->format('YmdHis').'.jpg')
            ->toMediaCollection('foto');

        $this->annotierVorschlaege = $ergebnis['suggestions'];
        $this->reset('foto');
        session()->flash('status', count($ergebnis['suggestions']).' Labeling-Vorschläge gespeichert.');
    }

    public function trainingStarten(): void
    {
        abort_unless($this->darf(), 403);
        abort_unless(config('vision.training_aktiv', false), 403);

        // WHY(Dataset-Pipeline offen): Es gibt noch keine Pipeline, die gelabelte
        // Aufnahmen in ein ZIP-Dataset exportiert. Ein leerer ZIP-Call würde vom
        // MCP-Server mit „dataset_zip_base64 required" abgewiesen. Die Pipeline
        // ist als Folge-Inkrement geplant (docs/INBETRIEBNAHME.md §5).
        $this->addError('training', 'Noch keine gelabelten Trainingsdaten gesammelt — die Dataset-/ZIP-Pipeline ist ein Folge-Inkrement.');
    }

    public function trainingStatusAktualisieren(VisionClient $vision): void
    {
        abort_unless($this->darf(), 403);

        if ($this->trainingJobId === null) {
            return;
        }

        $this->trainingStatus = $vision->trainStatus($this->trainingJobId);
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();

        $aufnahmen = RegalAufnahme::where('tenant_id', $tenantId)
            ->with(['detektionen.artikel'])
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $offeneInventuren = Inventur::where('tenant_id', $tenantId)
            ->where('status', InventurStatus::Offen->value)
            ->orderByDesc('id')
            ->get();

        $aktivesModell = YoloModell::aktivesFuer($tenantId)->first();

        return view('livewire.vision.regalzaehlung', [
            'aufnahmen' => $aufnahmen,
            'offeneInventuren' => $offeneInventuren,
            'aktivesModell' => $aktivesModell,
            'trainingAktiv' => (bool) config('vision.training_aktiv', false),
        ]);
    }
}
