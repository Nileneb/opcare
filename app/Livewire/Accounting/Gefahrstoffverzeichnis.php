<?php

namespace App\Livewire\Accounting;

use App\Domains\Accounting\Enums\GhsPiktogramm;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Gefahrstoff;
use App\Domains\Identity\Support\CurrentTenant;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Gefahrstoffverzeichnis extends Component
{
    use WithFileUploads;

    public ?int $artikelId = null;

    public string $signalwort = '';

    public string $hSaetzeInput = '';

    public string $pSaetzeInput = '';

    public array $ghsPiktogramme = [];

    public string $mengenbereich = '';

    public string $arbeitsbereiche = '';

    public string $lagerort = '';

    public string $betriebsanweisung = '';

    public string $sdbVersionDatum = '';

    /** @var TemporaryUploadedFile|null */
    public $sdbFile = null;

    public function mount(): void
    {
        $u = auth()->user();
        abort_unless(
            $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik', 'kueche', 'buchhaltung'])),
            403,
        );
    }

    public function editEintrag(int $artikelId): void
    {
        $this->artikelId = $artikelId;
        $this->resetFormFields();

        $gefahrstoff = Gefahrstoff::where('artikel_id', $artikelId)->first();
        if ($gefahrstoff === null) {
            return;
        }

        $this->signalwort = $gefahrstoff->signalwort ?? '';
        $this->hSaetzeInput = implode(', ', $gefahrstoff->h_saetze ?? []);
        $this->pSaetzeInput = implode(', ', $gefahrstoff->p_saetze ?? []);
        $this->ghsPiktogramme = $gefahrstoff->ghs_piktogramme ?? [];
        $this->mengenbereich = $gefahrstoff->mengenbereich ?? '';
        $this->arbeitsbereiche = $gefahrstoff->arbeitsbereiche ?? '';
        $this->lagerort = $gefahrstoff->lagerort ?? '';
        $this->betriebsanweisung = $gefahrstoff->betriebsanweisung ?? '';
        $this->sdbVersionDatum = $gefahrstoff->sdb_version_datum?->format('Y-m-d') ?? '';
    }

    public function eintragSpeichern(): void
    {
        $u = auth()->user();
        abort_unless(
            $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'haustechnik', 'kueche', 'buchhaltung'])),
            403,
        );

        $this->validate([
            'artikelId' => ['required', 'integer', 'exists:artikel,id'],
            'sdbFile' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $tenantId = app(CurrentTenant::class)->id();

        $hSaetze = $this->parseKommaliste($this->hSaetzeInput);
        $pSaetze = $this->parseKommaliste($this->pSaetzeInput);

        $gefahrstoff = Gefahrstoff::updateOrCreate(
            ['artikel_id' => $this->artikelId],
            [
                'tenant_id' => $tenantId,
                'signalwort' => $this->signalwort ?: null,
                'h_saetze' => $hSaetze ?: null,
                'p_saetze' => $pSaetze ?: null,
                'ghs_piktogramme' => $this->ghsPiktogramme ?: null,
                'mengenbereich' => $this->mengenbereich ?: null,
                'arbeitsbereiche' => $this->arbeitsbereiche ?: null,
                'lagerort' => $this->lagerort ?: null,
                'betriebsanweisung' => $this->betriebsanweisung ?: null,
                'sdb_version_datum' => $this->sdbVersionDatum ? Carbon::parse($this->sdbVersionDatum) : null,
            ],
        );

        Artikel::where('id', $this->artikelId)->update(['gefahrstoff' => true]);

        if ($this->sdbFile !== null) {
            $sdbFile = $this->sdbFile;
            $gefahrstoff->addMedia($sdbFile->getRealPath())
                ->usingFileName('sdb_'.$gefahrstoff->artikel_id.'_'.now()->format('Ymd').'.pdf')
                ->usingName('Sicherheitsdatenblatt')
                ->toMediaCollection('sdb');
        }

        $this->artikelId = null;
        $this->resetFormFields();
        session()->flash('status', 'Gefahrstoff-Eintrag gespeichert.');
    }

    public function render()
    {
        $eintraege = Artikel::where('gefahrstoff', true)
            ->with('gefahrstoffDaten.media')
            ->orderBy('name')
            ->get();

        $alleArtikel = Artikel::orderBy('name')->get();
        $piktogramme = GhsPiktogramm::cases();

        return view('livewire.accounting.gefahrstoffverzeichnis', compact('eintraege', 'alleArtikel', 'piktogramme'));
    }

    /** @return list<string> */
    private function parseKommaliste(string $input): array
    {
        if (trim($input) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $input))));
    }

    private function resetFormFields(): void
    {
        $this->signalwort = '';
        $this->hSaetzeInput = '';
        $this->pSaetzeInput = '';
        $this->ghsPiktogramme = [];
        $this->mengenbereich = '';
        $this->arbeitsbereiche = '';
        $this->lagerort = '';
        $this->betriebsanweisung = '';
        $this->sdbVersionDatum = '';
        $this->sdbFile = null;
    }
}
