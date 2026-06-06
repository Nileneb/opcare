<?php

namespace App\Livewire\Masterdata;

use App\Domains\Masterdata\Enums\DokumentKategorie;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Services\AttachmentService;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Dokumente & Fotos eines Bewohners: Upload (Kategorie + Aufbewahrungsfrist + Foto-Einwilligung),
 * Liste mit signiertem Download und protokollierter Freigabe „bei Bedarf". Eingebettet auf der
 * Bewohner-Detailseite (operativer Einstiegspunkt).
 */
class ResidentMedia extends Component
{
    use WithFileUploads;

    public Resident $resident;

    public $datei = null;

    public string $kategorie = 'sonstiges';

    public string $einwilligung = '';

    public ?int $teilenMedia = null;

    public string $teilen_typ = 'physician';

    public string $teilen_empfaenger = '';

    public int $teilen_minuten = 1440;

    public ?string $shareLink = null;

    public function mount(): void
    {
        $this->authorize('view', $this->resident);
    }

    public function speichern(AttachmentService $service): void
    {
        $this->authorize('update', $this->resident);
        $kategorie = DokumentKategorie::from($this->kategorie);

        $this->validate([
            'datei' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,heic,doc,docx'],
            'kategorie' => ['required', 'in:'.implode(',', array_map(fn ($k) => $k->value, DokumentKategorie::cases()))],
            'einwilligung' => [$kategorie->brauchtEinwilligung() ? 'required' : 'nullable', 'string', 'max:160'],
        ], [], ['einwilligung' => 'Einwilligung erteilt von']);

        $service->upload($this->resident, $this->datei, $kategorie, $this->einwilligung ?: null);
        $this->reset('datei', 'einwilligung');
        $this->kategorie = 'sonstiges';
        session()->flash('media_status', 'Dokument hochgeladen.');
    }

    public function teilenStart(int $mediaId): void
    {
        $this->authorize('update', $this->resident);
        $this->reset('shareLink', 'teilen_empfaenger');
        $this->teilenMedia = $mediaId;
    }

    public function teilenSpeichern(AttachmentService $service): void
    {
        $this->authorize('update', $this->resident);
        $this->validate([
            'teilen_typ' => ['required', 'in:physician,relative,authority,internal'],
            'teilen_empfaenger' => ['required', 'string', 'max:160'],
            'teilen_minuten' => ['required', 'integer', 'min:5', 'max:43200'],
        ]);
        $media = $this->resident->getMedia('documents')->firstOrFail(fn ($m) => $m->id === $this->teilenMedia);
        $this->shareLink = $service->shareLink($media, $this->teilen_minuten, $this->teilen_typ, $this->teilen_empfaenger);
    }

    public function loeschen(int $mediaId, AttachmentService $service): void
    {
        $this->authorize('update', $this->resident);
        $media = $this->resident->getMedia('documents')->firstOrFail(fn ($m) => $m->id === $mediaId);
        $service->delete($media);
        $this->reset('teilenMedia', 'shareLink');
    }

    public function render()
    {
        return view('livewire.masterdata.resident-media', [
            'dokumente' => $this->resident->getMedia('documents'),
            'kategorien' => DokumentKategorie::cases(),
        ]);
    }
}
