<?php

namespace App\Livewire\Facility;

use App\Domains\Facility\Enums\AssetKategorie;
use App\Domains\Facility\Models\FacilityAsset;
use App\Domains\Facility\Models\StoerquelleVorsorge;
use App\Domains\Facility\Services\StoerquellenAnalyzer;
use App\Domains\Identity\Support\CurrentTenant;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Störquellen-Notfallvorsorge: wertet die häufigsten Haustechnik-Ausfälle über 6/12 Monate aus und führt je
 * Top-Störquelle eine Vorsorge (Mindest-Ersatzteile, schriftlich fixierte Dienstleister-Reaktionszeit,
 * interne Sofortmaßnahmen-Checkliste). Sichtbar wird so die Lücke: häufige Ausfälle ohne hinterlegte Vorsorge.
 */
#[Layout('layouts.app')]
class Stoerquellen extends Component
{
    public int $monate = 12;

    public ?int $editId = null;

    public bool $formOffen = false;

    public string $v_bezeichnung = '';

    public string $v_kategorie = 'sonstiges';

    public ?int $v_asset = null;

    public string $v_ersatzteile = '';

    public string $v_dienstleister = '';

    public string $v_kontakt = '';

    public string $v_reaktionszeit = '';

    public ?int $v_reaktionszeit_stunden = null;

    /** @var array<int, string> */
    public array $v_sofort = [];

    public string $v_notiz = '';

    public function darfVerwalten(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft', 'haustechnik']));
    }

    public function setFenster(int $monate): void
    {
        $this->monate = in_array($monate, [6, 12], true) ? $monate : 12;
    }

    public function schrittHinzufuegen(): void
    {
        $this->v_sofort[] = '';
    }

    public function schrittEntfernen(int $i): void
    {
        unset($this->v_sofort[$i]);
        $this->v_sofort = array_values($this->v_sofort);
    }

    /** Neues Vorsorge-Profil, vorbefüllt aus einer Störquelle des Rankings. */
    public function neuFuer(?int $assetId, string $bezeichnung, ?string $kategorie): void
    {
        abort_unless($this->darfVerwalten(), 403);

        $this->resetForm();
        $this->v_bezeichnung = $bezeichnung;
        $this->v_asset = $assetId;
        $this->v_kategorie = $kategorie ?: 'sonstiges';
        $this->v_sofort = [''];
        $this->formOffen = true;
    }

    public function neu(): void
    {
        abort_unless($this->darfVerwalten(), 403);

        $this->resetForm();
        $this->v_sofort = [''];
        $this->formOffen = true;
    }

    public function bearbeiten(int $id): void
    {
        abort_unless($this->darfVerwalten(), 403);

        $tid = app(CurrentTenant::class)->id();
        $v = StoerquelleVorsorge::withoutGlobalScopes()->where('tenant_id', $tid)->findOrFail($id);

        $this->editId = $v->id;
        $this->v_bezeichnung = $v->bezeichnung;
        $this->v_kategorie = $v->kategorie->value;
        $this->v_asset = $v->asset_id;
        $this->v_ersatzteile = $v->mindest_ersatzteile ?? '';
        $this->v_dienstleister = $v->dienstleister ?? '';
        $this->v_kontakt = $v->dienstleister_kontakt ?? '';
        $this->v_reaktionszeit = $v->reaktionszeit ?? '';
        $this->v_reaktionszeit_stunden = $v->reaktionszeit_stunden;
        $this->v_sofort = $v->sofortmassnahmenListe() ?: [''];
        $this->v_notiz = $v->notiz ?? '';
        $this->formOffen = true;
    }

    public function speichern(): void
    {
        abort_unless($this->darfVerwalten(), 403);

        $data = $this->validate([
            'v_bezeichnung' => ['required', 'string', 'max:160'],
            'v_kategorie' => ['required', 'in:'.implode(',', array_map(fn ($k) => $k->value, AssetKategorie::cases()))],
            'v_asset' => ['nullable', 'integer', 'exists:facility_assets,id'],
            'v_ersatzteile' => ['nullable', 'string', 'max:2000'],
            'v_dienstleister' => ['nullable', 'string', 'max:160'],
            'v_kontakt' => ['nullable', 'string', 'max:160'],
            'v_reaktionszeit' => ['nullable', 'string', 'max:120'],
            'v_reaktionszeit_stunden' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'v_sofort' => ['array'],
            'v_sofort.*' => ['nullable', 'string', 'max:300'],
            'v_notiz' => ['nullable', 'string', 'max:1000'],
        ]);

        $tid = app(CurrentTenant::class)->id();

        $sofort = array_values(array_filter(
            array_map(fn ($s) => trim((string) $s), $data['v_sofort'] ?? []),
            fn ($s) => $s !== ''
        ));

        $attrs = [
            'bezeichnung' => $data['v_bezeichnung'],
            'kategorie' => $data['v_kategorie'],
            'asset_id' => $data['v_asset'],
            'mindest_ersatzteile' => $data['v_ersatzteile'] ?: null,
            'dienstleister' => $data['v_dienstleister'] ?: null,
            'dienstleister_kontakt' => $data['v_kontakt'] ?: null,
            'reaktionszeit' => $data['v_reaktionszeit'] ?: null,
            'reaktionszeit_stunden' => $data['v_reaktionszeit_stunden'],
            'sofortmassnahmen' => $sofort,
            'notiz' => $data['v_notiz'] ?: null,
        ];

        if ($this->editId !== null) {
            $v = StoerquelleVorsorge::withoutGlobalScopes()->where('tenant_id', $tid)->findOrFail($this->editId);
            $v->update($attrs);
            session()->flash('status', 'Vorsorge aktualisiert.');
        } else {
            StoerquelleVorsorge::create($attrs);
            session()->flash('status', 'Vorsorge hinterlegt.');
        }

        $this->resetForm();
        $this->formOffen = false;
    }

    public function loeschen(int $id): void
    {
        abort_unless($this->darfVerwalten(), 403);

        $tid = app(CurrentTenant::class)->id();
        StoerquelleVorsorge::withoutGlobalScopes()->where('tenant_id', $tid)->findOrFail($id)->delete();
        session()->flash('status', 'Vorsorge entfernt.');
    }

    public function abbrechen(): void
    {
        $this->resetForm();
        $this->formOffen = false;
    }

    private function resetForm(): void
    {
        $this->reset([
            'editId', 'v_bezeichnung', 'v_kategorie', 'v_asset', 'v_ersatzteile', 'v_dienstleister',
            'v_kontakt', 'v_reaktionszeit', 'v_reaktionszeit_stunden', 'v_sofort', 'v_notiz',
        ]);
        $this->v_kategorie = 'sonstiges';
    }

    public function render()
    {
        $tid = app(CurrentTenant::class)->id();

        $ranking = app(StoerquellenAnalyzer::class)->analysiere($tid, $this->monate);
        $top = $ranking->take(10);

        $vorsorgen = StoerquelleVorsorge::withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->with('asset')
            ->orderBy('bezeichnung')
            ->get();

        $luecken = $top->filter(fn ($b) => $b->kategorie !== null && ! $b->hatVorsorge)->count();

        return view('livewire.facility.stoerquellen', [
            'top' => $top,
            'quellenGesamt' => $ranking->count(),
            'luecken' => $luecken,
            'vorsorgen' => $vorsorgen,
            'assets' => FacilityAsset::withoutGlobalScopes()->where('tenant_id', $tid)->where('aktiv', true)->orderBy('bezeichnung')->get(),
            'kategorien' => AssetKategorie::cases(),
            'darfVerwalten' => $this->darfVerwalten(),
        ]);
    }
}
