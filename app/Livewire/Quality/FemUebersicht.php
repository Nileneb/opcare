<?php

namespace App\Livewire\Quality;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Quality\Enums\FemArt;
use App\Domains\Quality\Enums\FemEinwilligung;
use App\Domains\Quality\Models\FemFall;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * FEM-Übersicht (§ 1831 BGB): Fälle mit Genehmigungs-Ampel an der Befristung, Anlegen mit Pflicht zur
 * Dokumentation milderer Mittel, laufendes Überwachungsprotokoll, Beendigung und Dokument-Upload
 * (ärztliches Attest, Gerichtsbeschluss).
 */
#[Layout('layouts.app')]
class FemUebersicht extends Component
{
    use WithFileUploads;

    public const MILDERE = ['Niederflurbett', 'Sensormatte', 'Hüftschutz', 'Begleitung/1:1', 'Tagesstruktur/Aktivierung', 'Medikation der Grunderkrankung'];

    public ?int $selected = null;

    // neuer Fall
    public ?int $f_resident = null;

    public string $f_art = 'bettgitter';

    public string $f_detail = '';

    public string $f_anlass = '';

    /** @var array<int, string> */
    public array $f_mildere = [];

    public string $f_mildere_begruendung = '';

    public string $f_arzt = '';

    public string $f_einwilligung = 'beantragt';

    public string $f_aktenzeichen = '';

    public string $f_gericht = '';

    public ?string $f_beschluss_am = null;

    public ?string $f_gueltig_bis = null;

    // Protokoll
    public string $p_typ = 'kontrolle';

    public string $p_befund = '';

    public bool $p_indikation = true;

    // Beendigung
    public string $beend_grund = '';

    public $dokument = null;

    public function mount(): void
    {
        abort_unless($this->darf(), 403);
    }

    private function darf(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function anlegen(): void
    {
        abort_unless($this->darf(), 403);
        $genehmigt = $this->f_einwilligung === FemEinwilligung::GenehmigungErteilt->value;
        $data = $this->validate([
            'f_resident' => ['required', 'integer', 'exists:residents,id'],
            'f_art' => ['required', 'in:'.implode(',', array_map(fn ($a) => $a->value, FemArt::cases()))],
            'f_anlass' => ['required', 'string', 'max:300'],
            'f_mildere_begruendung' => ['required', 'string', 'max:300'],
            'f_arzt' => ['required', 'string', 'max:120'],
            'f_einwilligung' => ['required', 'in:'.implode(',', array_map(fn ($e) => $e->value, FemEinwilligung::cases()))],
            'f_aktenzeichen' => [$genehmigt ? 'required' : 'nullable', 'string', 'max:80'],
            'f_beschluss_am' => [$genehmigt ? 'required' : 'nullable', 'date'],
            'f_gueltig_bis' => [$genehmigt ? 'required' : 'nullable', 'date', 'after:today'],
        ]);

        $fall = FemFall::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'resident_id' => $data['f_resident'], 'art' => $data['f_art'], 'detail' => $this->f_detail ?: null,
            'anlass' => $data['f_anlass'], 'mildere_mittel' => array_values($this->f_mildere), 'mildere_begruendung' => $data['f_mildere_begruendung'],
            'anordnung_pflegekraft' => auth()->id(), 'anordnung_arzt' => $data['f_arzt'], 'anordnung_am' => now(),
            'einwilligungsstatus' => $data['f_einwilligung'],
            'antrag_am' => $this->f_einwilligung === FemEinwilligung::GenehmigungBeantragt->value ? today()->toDateString() : null,
            'aktenzeichen' => $this->f_aktenzeichen ?: null, 'gericht' => $this->f_gericht ?: null,
            'beschluss_am' => $this->f_beschluss_am ?: null, 'gueltig_bis' => $this->f_gueltig_bis ?: null,
        ]);
        $this->reset('f_anlass', 'f_detail', 'f_mildere', 'f_mildere_begruendung', 'f_arzt', 'f_aktenzeichen', 'f_gericht', 'f_beschluss_am', 'f_gueltig_bis');
        $this->selected = $fall->id;
        session()->flash('status', 'FEM-Fall angelegt.');
    }

    public function protokollieren(): void
    {
        abort_unless($this->darf(), 403);
        $fall = FemFall::findOrFail($this->selected);
        $this->validate(['p_typ' => ['required', 'in:kontrolle,vitalzeichen,sonstiges'], 'p_befund' => ['nullable', 'string', 'max:300']]);
        $fall->protokolle()->create([
            'tenant_id' => $fall->tenant_id, 'zeitpunkt' => now(), 'typ' => $this->p_typ,
            'befund' => $this->p_befund ?: null, 'indikation_gegeben' => $this->p_indikation, 'dokumentiert_von' => auth()->id(),
        ]);
        $this->reset('p_befund');
        session()->flash('status', 'Protokolleintrag gespeichert.');
    }

    public function beenden(): void
    {
        abort_unless($this->darf(), 403);
        $fall = FemFall::findOrFail($this->selected);
        $this->validate(['beend_grund' => ['required', 'string', 'max:200']]);
        $fall->update(['beendet_am' => now(), 'beendigungsgrund' => $this->beend_grund]);
        $fall->protokolle()->create(['tenant_id' => $fall->tenant_id, 'zeitpunkt' => now(), 'typ' => 'beendigung',
            'befund' => $this->beend_grund, 'dokumentiert_von' => auth()->id()]);
        $this->reset('beend_grund');
        session()->flash('status', 'FEM beendet und dokumentiert.');
    }

    public function dokumentHochladen(): void
    {
        abort_unless($this->darf(), 403);
        $this->validate(['dokument' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png']]);
        $fall = FemFall::findOrFail($this->selected);
        $fall->addMedia($this->dokument->getRealPath())->usingName($this->dokument->getClientOriginalName())
            ->usingFileName($this->dokument->hashName())->toMediaCollection('fem_dokumente');
        $this->reset('dokument');
        session()->flash('status', 'Dokument angehängt.');
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $faelle = FemFall::with('resident')->where('tenant_id', $tenantId)->orderByDesc('id')->get();
        $fall = $this->selected ? $faelle->firstWhere('id', $this->selected) : null;
        $handlungsbedarf = $faelle->filter(fn (FemFall $f) => in_array($f->ampel(), ['red', 'amber'], true) && $f->aktiv())->count();

        return view('livewire.quality.fem-uebersicht', [
            'faelle' => $faelle,
            'fall' => $fall,
            'protokolle' => $fall ? $fall->protokolle()->with('dokumentierer')->get() : collect(),
            'dokumente' => $fall ? $fall->getMedia('fem_dokumente') : collect(),
            'handlungsbedarf' => $handlungsbedarf,
            'arten' => FemArt::cases(),
            'einwilligungen' => FemEinwilligung::cases(),
            'milderOptionen' => self::MILDERE,
            'bewohner' => Resident::where('tenant_id', $tenantId)->where('status', 'aktiv')->orderBy('name')->get(),
        ]);
    }
}
