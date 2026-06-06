<?php

namespace App\Livewire\Capture;

use App\Domains\Accounting\Actions\Buchen;
use App\Domains\Accounting\Models\Konto;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Accounting\Support\BudgetGuard;
use App\Domains\Capture\Models\BelegAnalyse;
use App\Domains\Capture\Models\EinsortierungsVorschlag;
use App\Domains\Capture\Services\BelegCapture;
use App\Domains\Identity\Support\CurrentTenant;
use App\Support\Concerns\ScopesTenantValidation;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * VLM-Beleg-Capture (Foto → Analyse → Vorschlag → berechtigte Bestätigung bucht). Die VLM-Ausgabe ist nur ein
 * Vorschlag; geschrieben (= gebucht) wird erst nach menschlicher Bestätigung — und nur durch die Finanzrolle
 * (admin/buchhaltung). Analysieren/Vorschlag-Sehen ist für die Finanzrolle ebenfalls gegated (Belegdaten).
 */
#[Layout('layouts.app')]
class Belegerfassung extends Component
{
    use ScopesTenantValidation, WithFileUploads;

    public $bild;

    public ?int $confirmId = null;

    public ?int $c_soll = null;

    public ?int $c_haben = null;

    public string $c_text = '';

    public ?string $c_datum = null;

    public function mount(): void
    {
        abort_unless($this->darf(), 403);
        AccountingDefaults::ensureFor(app(CurrentTenant::class)->id());
    }

    private function darf(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'buchhaltung']));
    }

    public function analysieren(BelegCapture $capture): void
    {
        abort_unless($this->darf(), 403);
        $this->validate(['bild' => ['required', 'image', 'max:8192']]);

        $capture->erfasse(
            $this->bild->getRealPath(),
            $this->bild->getClientOriginalName(),
            (string) $this->bild->getMimeType(),
            (int) auth()->id(),
        );
        $this->reset('bild');
        session()->flash('status', 'Beleg analysiert — Vorschlag zur Bestätigung erstellt.');
    }

    public function bestaetigenStart(int $id): void
    {
        abort_unless($this->darf(), 403);
        $v = EinsortierungsVorschlag::findOrFail($id);
        $this->confirmId = $v->id;
        $this->c_text = 'Beleg: '.($v->ziel_felder['lieferant'] ?? $v->ziel_felder['belegtyp'] ?? 'Capture');
        $this->c_datum = $v->ziel_felder['datum'] ?? today()->toDateString();
        $this->c_soll = null;
        $this->c_haben = null;
    }

    public function bestaetigen(BelegCapture $capture, Buchen $buchen, BudgetGuard $guard): void
    {
        abort_unless($this->darf(), 403);
        $v = EinsortierungsVorschlag::findOrFail((int) $this->confirmId);
        $data = $this->validate([
            'c_soll' => ['required', 'integer', $this->tenantExists('konten')],
            'c_haben' => ['required', 'integer', 'different:c_soll', $this->tenantExists('konten')],
            'c_text' => ['required', 'string', 'max:200'],
            'c_datum' => ['required', 'date'],
        ]);

        // Budget-Gate des Soll-Kontos — dasselbe wie bei der freien Buchung.
        $betrag = (float) ($v->ziel_felder['betrag'] ?? 0);
        $check = $guard->pruefe((int) $data['c_soll'], $betrag, $data['c_datum']);
        if ($check['block'] !== null) {
            $this->addError('c_soll', $check['block']);

            return;
        }

        $capture->bestaetige($v, (int) auth()->id(), $data['c_soll'], $data['c_haben'], $data['c_text'], $data['c_datum'], $buchen);
        $this->reset('confirmId', 'c_soll', 'c_haben', 'c_text', 'c_datum');
        session()->flash('status', $check['warn'] ?? 'Beleg gebucht.');
    }

    public function verwerfen(int $id, BelegCapture $capture): void
    {
        abort_unless($this->darf(), 403);
        $capture->verwerfe(EinsortierungsVorschlag::findOrFail($id), (int) auth()->id());
        session()->flash('status', 'Vorschlag verworfen.');
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();

        return view('livewire.capture.belegerfassung', [
            'analysen' => BelegAnalyse::with(['vorschlaege.buchung', 'erfasser'])->orderByDesc('id')->limit(25)->get(),
            'konten' => Konto::where('tenant_id', $tenantId)->orderBy('nummer')->get(),
        ]);
    }
}
