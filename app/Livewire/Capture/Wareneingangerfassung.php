<?php

namespace App\Livewire\Capture;

use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Bestellposition;
use App\Domains\Capture\Models\LieferscheinAnalyse;
use App\Domains\Capture\Models\LieferscheinPositionVorschlag;
use App\Domains\Capture\Services\CaptureWareneingang;
use App\Domains\Identity\Support\CurrentTenant;
use App\Support\Concerns\ScopesTenantValidation;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Wareneingangerfassung extends Component
{
    use ScopesTenantValidation, WithFileUploads;

    public $foto;

    /** @var array<int, array{artikel_id: mixed, menge: mixed, preis: mixed, charge: mixed, mhd: mixed, bestellposition_id: mixed}> */
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

    public function analysieren(CaptureWareneingang $svc): void
    {
        abort_unless($this->darf(), 403);
        $this->validate(['foto' => ['required', 'image', 'max:8192']]);

        $b64 = base64_encode(file_get_contents($this->foto->getRealPath()));
        $svc->erfasse($b64, $this->foto->getMimeType(), app(CurrentTenant::class)->id(), auth()->id());

        $this->reset('foto');
        session()->flash('status', 'Lieferschein analysiert — Positionen zur Bestätigung bereit.');
    }

    public function bestaetige(int $positionId, CaptureWareneingang $svc): void
    {
        abort_unless($this->darf(), 403);

        $data = $this->validate([
            "ist.{$positionId}.artikel_id" => ['required', 'integer', $this->tenantExists('artikel')],
            "ist.{$positionId}.menge" => ['required', 'numeric', 'gt:0'],
            "ist.{$positionId}.preis" => ['nullable', 'numeric', 'min:0'],
            "ist.{$positionId}.charge" => ['nullable', 'string', 'max:120'],
            "ist.{$positionId}.mhd" => ['nullable', 'date'],
            "ist.{$positionId}.bestellposition_id" => ['nullable', 'integer', $this->tenantExists('bestellpositionen')],
        ]);

        $row = $data['ist'][$positionId];
        $p = LieferscheinPositionVorschlag::findOrFail($positionId);

        try {
            $svc->bestaetige(
                $p,
                (int) $row['artikel_id'],
                (float) $row['menge'],
                isset($row['preis']) && $row['preis'] !== '' ? (float) $row['preis'] : null,
                $row['charge'] ?: null,
                $row['mhd'] ?: null,
                isset($row['bestellposition_id']) && $row['bestellposition_id'] ? (int) $row['bestellposition_id'] : null,
                app(CurrentTenant::class)->id(),
                auth()->id(),
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError("ist.{$positionId}.menge", $e->getMessage());

            return;
        }

        session()->flash('status', 'Position gebucht.');
    }

    public function verwerfe(int $positionId, CaptureWareneingang $svc): void
    {
        abort_unless($this->darf(), 403);
        $p = LieferscheinPositionVorschlag::findOrFail($positionId);
        $svc->verwerfe($p, auth()->id());
        session()->flash('status', 'Position verworfen.');
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();

        $analysen = LieferscheinAnalyse::with(['positionen', 'lieferant'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $artikel = Artikel::where('tenant_id', $tenantId)->orderBy('name')->get();

        // Bestellpositionen je Lieferant der neuesten Analyse (offen = teillieferbar).
        $bestellpositionen = collect();
        $neuste = $analysen->first();
        if ($neuste?->lieferant_id) {
            $bestellpositionen = Bestellposition::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereHas('bestellung', fn ($q) => $q->where('lieferant_id', $neuste->lieferant_id))
                ->with('artikel')
                ->get()
                ->filter(fn (Bestellposition $bp) => $bp->offen());
        }

        // Default-Vorbelegung: bester Kandidat als artikel_id, menge/charge/mhd aus der Position.
        foreach ($analysen as $analyse) {
            foreach ($analyse->positionen as $pos) {
                if (! isset($this->ist[$pos->id])) {
                    $bestKandidat = $pos->kandidaten[0]['artikel_id'] ?? $pos->matched_artikel_id;
                    $this->ist[$pos->id] = [
                        'artikel_id' => $bestKandidat,
                        'menge' => $pos->menge ? (float) $pos->menge : null,
                        'preis' => $pos->einzelpreis ? (float) $pos->einzelpreis : null,
                        'charge' => $pos->charge_nr,
                        'mhd' => $pos->mhd?->toDateString(),
                        'bestellposition_id' => $pos->matched_bestellposition_id,
                    ];
                }
            }
        }

        // Foto-Vorschau: signierte URL des Lieferschein-Mediums der neuesten Analyse.
        $fotoUrl = null;
        if ($neuste) {
            $media = $neuste->getFirstMedia('lieferschein');
            if ($media) {
                $fotoUrl = URL::temporarySignedRoute('media.download', now()->addMinutes(5), ['media' => $media->id]);
            }
        }

        return view('livewire.capture.wareneingangerfassung', [
            'analysen' => $analysen,
            'artikel' => $artikel,
            'bestellpositionen' => $bestellpositionen,
            'fotoUrl' => $fotoUrl,
        ]);
    }
}
