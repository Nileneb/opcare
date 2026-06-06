<?php

namespace App\Livewire\Compliance;

use App\Domains\Compliance\Enums\Rechtsgrundlage;
use App\Domains\Compliance\Models\Auftragsverarbeitung;
use App\Domains\Compliance\Models\Verarbeitungstaetigkeit;
use App\Domains\Compliance\Services\Art30Export;
use App\Domains\Compliance\Support\VvtDefaults;
use App\Domains\Identity\Support\CurrentTenant;
use App\Support\Concerns\ScopesTenantValidation;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Datenschutz-Register: Verzeichnis von Verarbeitungstätigkeiten (Art. 30 DSGVO) + Auftragsverarbeitungen
 * (Art. 28 DSGVO) als editierbarer Katalog mit Prüf-Frist-Ampel. Der Art-30-Export erzeugt das
 * vorlagefähige Verzeichnis für die Aufsichtsbehörde.
 */
#[Layout('layouts.app')]
class Datenschutz extends Component
{
    use ScopesTenantValidation;

    // Verarbeitungstätigkeit (VVT)
    public string $v_name = '';

    public string $v_zweck = '';

    public string $v_rechtsgrundlage = 'gesundheitsdaten';

    public string $v_betroffene = '';

    public string $v_daten = '';

    public string $v_empfaenger = '';

    public string $v_drittland = '';

    public string $v_loeschfrist = '';

    public string $v_tom = '';

    public ?int $v_intervall = 12;

    // Auftragsverarbeitung (AVV)
    public string $a_dienstleister = '';

    public string $a_zweck = '';

    public string $a_daten = '';

    public string $a_drittland = '';

    public bool $a_unterauftrag = false;

    public ?string $a_vertrag_am = null;

    public ?int $a_vt = null;

    public ?int $a_intervall = 24;

    public function mount(): void
    {
        abort_unless($this->darfSehen(), 403);
        VvtDefaults::ensureFor(app(CurrentTenant::class)->id());
    }

    private function darfSehen(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function verarbeitungAnlegen(): void
    {
        abort_unless($this->darfSehen(), 403);
        $data = $this->validate([
            'v_name' => ['required', 'string', 'max:160'],
            'v_zweck' => ['required', 'string', 'max:600'],
            'v_rechtsgrundlage' => ['required', 'in:'.implode(',', array_map(fn ($r) => $r->value, Rechtsgrundlage::cases()))],
            'v_betroffene' => ['required', 'string', 'max:200'],
            'v_daten' => ['required', 'string', 'max:200'],
            'v_empfaenger' => ['nullable', 'string', 'max:200'],
            'v_drittland' => ['nullable', 'string', 'max:200'],
            'v_loeschfrist' => ['required', 'string', 'max:200'],
            'v_tom' => ['nullable', 'string', 'max:400'],
            'v_intervall' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        Verarbeitungstaetigkeit::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'name' => $data['v_name'], 'zweck' => $data['v_zweck'], 'rechtsgrundlage' => $data['v_rechtsgrundlage'],
            'kategorien_betroffene' => $data['v_betroffene'], 'kategorien_daten' => $data['v_daten'],
            'empfaenger' => $data['v_empfaenger'] ?: null, 'drittland' => $data['v_drittland'] ?: null,
            'loeschfrist' => $data['v_loeschfrist'], 'tom' => $data['v_tom'] ?: null,
            'pruef_intervall_monate' => $data['v_intervall'], 'geprueft_am' => today(),
        ]);
        $this->reset('v_name', 'v_zweck', 'v_betroffene', 'v_daten', 'v_empfaenger', 'v_drittland', 'v_loeschfrist', 'v_tom');
        session()->flash('status', 'Verarbeitungstätigkeit erfasst.');
    }

    public function verarbeitungGeprueft(int $id): void
    {
        abort_unless($this->darfSehen(), 403);
        Verarbeitungstaetigkeit::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($id)
            ->update(['geprueft_am' => today()]);
        session()->flash('status', 'Als geprüft markiert.');
    }

    public function verarbeitungLoeschen(int $id): void
    {
        abort_unless($this->darfSehen(), 403);
        Verarbeitungstaetigkeit::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($id)->delete();
        session()->flash('status', 'Eintrag entfernt.');
    }

    public function avvAnlegen(): void
    {
        abort_unless($this->darfSehen(), 403);
        $data = $this->validate([
            'a_dienstleister' => ['required', 'string', 'max:160'],
            'a_zweck' => ['required', 'string', 'max:600'],
            'a_daten' => ['required', 'string', 'max:200'],
            'a_drittland' => ['nullable', 'string', 'max:200'],
            'a_vertrag_am' => ['nullable', 'date'],
            'a_vt' => ['nullable', 'integer', $this->tenantExists('verarbeitungstaetigkeiten')],
            'a_intervall' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        Auftragsverarbeitung::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'verarbeitungstaetigkeit_id' => $data['a_vt'], 'dienstleister' => $data['a_dienstleister'],
            'zweck' => $data['a_zweck'], 'kategorien_daten' => $data['a_daten'], 'drittland' => $data['a_drittland'] ?: null,
            'unterauftragnehmer' => $this->a_unterauftrag, 'vertrag_geschlossen_am' => $data['a_vertrag_am'] ?: null,
            'pruef_intervall_monate' => $data['a_intervall'],
        ]);
        $this->reset('a_dienstleister', 'a_zweck', 'a_daten', 'a_drittland', 'a_unterauftrag', 'a_vertrag_am', 'a_vt');
        session()->flash('status', 'Auftragsverarbeitung erfasst.');
    }

    public function avvGeprueft(int $id): void
    {
        abort_unless($this->darfSehen(), 403);
        Auftragsverarbeitung::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($id)
            ->update(['geprueft_am' => today()]);
        session()->flash('status', 'Als geprüft markiert.');
    }

    public function avvLoeschen(int $id): void
    {
        abort_unless($this->darfSehen(), 403);
        Auftragsverarbeitung::where('tenant_id', app(CurrentTenant::class)->id())->findOrFail($id)->delete();
        session()->flash('status', 'Eintrag entfernt.');
    }

    /** Vorlagefähiges Verzeichnis nach Art. 30 DSGVO als Textdatei für die Aufsichtsbehörde. */
    public function exportArt30(Art30Export $export): StreamedResponse
    {
        abort_unless($this->darfSehen(), 403);
        $text = $export->render(
            app(CurrentTenant::class)->id(),
            app(CurrentTenant::class)->get()->name ?? '',
        );

        return response()->streamDownload(
            fn () => print ($text),
            'vvt-art30-'.today()->format('Y-m-d').'.txt',
            ['Content-Type' => 'text/plain; charset=utf-8'],
        );
    }

    public function render()
    {
        $tenantId = app(CurrentTenant::class)->id();
        $vts = Verarbeitungstaetigkeit::where('tenant_id', $tenantId)->orderBy('name')->get();
        $avvs = Auftragsverarbeitung::where('tenant_id', $tenantId)->orderBy('dienstleister')->get();

        return view('livewire.compliance.datenschutz', [
            'vts' => $vts,
            'avvs' => $avvs,
            'rechtsgrundlagen' => Rechtsgrundlage::cases(),
            'offeneAvv' => $avvs->filter(fn (Auftragsverarbeitung $a) => $a->status() === 'kein_avv')->count(),
            'ueberfaellig' => $vts->filter(fn (Verarbeitungstaetigkeit $v) => $v->ampel() === 'red')->count(),
        ]);
    }
}
