<?php

namespace App\Livewire\Facility;

use App\Domains\Facility\Models\Legionellenbefund;
use App\Domains\Facility\Models\Probenahmestelle;
use App\Domains\Facility\Models\Trinkwasseranlage;
use App\Domains\Facility\Services\BefundErfassen;
use App\Domains\Identity\Support\CurrentTenant;
use App\Support\Concerns\ScopesTenantValidation;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Trinkwasser-Überwachung (TrinkwV 2023 § 31): Anlagen-Register, Probenahmestellen,
 * Legionellen-Befunde mit Frist-Ampel. § 51-Pflicht-Workflow bei Überschreitung des
 * technischen Maßnahmenwerts (100 KbE/100 ml, Anlage 3 Teil II TrinkwV 2023).
 */
#[Layout('layouts.app')]
class Trinkwasser extends Component
{
    use ScopesTenantValidation;
    use WithFileUploads;

    // Anlage anlegen
    public string $bezeichnung = '';

    public string $gebaeude = '';

    public int $intervall = 12;

    // Probenahmestelle anlegen
    public string $stelle_bezeichnung = '';

    public string $stelle_ort = '';

    // Befund erfassen
    public ?int $stelle_id = null;

    public string $untersucht_am = '';

    public int $kbe = 0;

    public string $labor = '';

    // Laborbefund-Upload
    public $laborbefund_datei = null;

    // Überschreitungs-Workflow
    public string $meldung_massnahme = '';

    public function mount(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik'])), 403);
        $this->untersucht_am = today()->toDateString();
    }

    public function anlageSpeichern(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik'])), 403);

        $data = $this->validate([
            'bezeichnung' => ['required', 'string', 'max:160'],
            'gebaeude' => ['nullable', 'string', 'max:120'],
            'intervall' => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        Trinkwasseranlage::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'bezeichnung' => $data['bezeichnung'],
            'gebaeude' => $data['gebaeude'] ?: null,
            'ist_grossanlage' => true,
            'untersuchungsintervall_monate' => $data['intervall'],
        ]);

        $this->reset('bezeichnung', 'gebaeude');
        $this->intervall = 12;
        session()->flash('status', 'Trinkwasseranlage angelegt.');
    }

    public function stelleSpeichern(int $anlageId): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik'])), 403);

        // WHY(IDOR): anlageId kommt als Methodenparameter, nicht als Property — tenant-scope manuell prüfen.
        $anlage = Trinkwasseranlage::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($anlageId);

        $data = $this->validate([
            'stelle_bezeichnung' => ['required', 'string', 'max:160'],
            'stelle_ort' => ['nullable', 'string', 'max:120'],
        ]);

        Probenahmestelle::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'trinkwasseranlage_id' => $anlage->id,
            'bezeichnung' => $data['stelle_bezeichnung'],
            'ort' => $data['stelle_ort'] ?: null,
        ]);

        $this->reset('stelle_bezeichnung', 'stelle_ort');
        session()->flash('status', 'Probenahmestelle angelegt.');
    }

    public function befundErfassen(int $anlageId, BefundErfassen $svc): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik'])), 403);

        $rules = [
            'stelle_id' => ['nullable', 'integer', $this->tenantExists('probenahmestellen')],
            'untersucht_am' => ['required', 'date', 'before_or_equal:today'],
            'kbe' => ['required', 'integer', 'min:0'],
            'labor' => ['nullable', 'string', 'max:160'],
            'laborbefund_datei' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ];

        try {
            $data = $this->validate($rules);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        $anlage = Trinkwasseranlage::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($anlageId);

        $befund = $svc->handle(
            $anlage,
            $data['stelle_id'] ?? null,
            $data['untersucht_am'],
            $data['kbe'],
            $data['labor'] ?: null,
        );

        if ($this->laborbefund_datei !== null) {
            $datei = $this->laborbefund_datei;
            $befund->addMedia($datei->getRealPath())
                ->usingFileName($datei->getClientOriginalName())
                ->toMediaCollection('laborbefund');
        }

        $this->reset('stelle_id', 'untersucht_am', 'kbe', 'labor', 'laborbefund_datei');
        $this->untersucht_am = today()->toDateString();
        session()->flash('status', 'Befund erfasst.');
    }

    public function meldungSetzen(int $befundId): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik'])), 403);

        $data = $this->validate([
            'meldung_massnahme' => ['required', 'string', 'max:1000'],
        ]);

        $befund = Legionellenbefund::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($befundId);

        $befund->massnahme = $data['meldung_massnahme'];
        if ($befund->gesundheitsamt_gemeldet_am === null) {
            $befund->gesundheitsamt_gemeldet_am = today();
        }
        $befund->save();

        $this->reset('meldung_massnahme');
        session()->flash('status', '§ 51 TrinkwV: Meldung an Gesundheitsamt dokumentiert.');
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();

        $anlagen = Trinkwasseranlage::where('tenant_id', $tenantId)
            ->with(['probenahmestellen', 'befunde' => fn ($q) => $q->with(['probenahmestelle', 'media'])->latest('untersucht_am')->limit(10)])
            ->orderBy('bezeichnung')
            ->get();

        return view('livewire.facility.trinkwasser', [
            'anlagen' => $anlagen,
            'ueberfaellig' => $anlagen->filter(fn (Trinkwasseranlage $a) => $a->istUeberfaellig())->count(),
        ]);
    }
}
