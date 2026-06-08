<?php

namespace App\Livewire\Catering;

use App\Domains\Catering\Enums\HaccpArt;
use App\Domains\Catering\Models\HaccpMesspunkt;
use App\Domains\Catering\Models\Temperaturmessung;
use App\Domains\Catering\Services\HaccpMonitor;
use App\Domains\Catering\Services\MessungErfassen;
use App\Domains\Identity\Support\CurrentTenant;
use App\Support\Concerns\ScopesTenantValidation;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * HACCP-Tagesblatt: CCP-Messpunkte + Temperaturmessungen + Korrekturmaßnahmen-Workflow.
 * Norm-Anker: VO (EG) 852/2004 Art. 5 (HACCP-System), DIN 10508 (Temperatur-Grenzwerte),
 * LMHV (Lebensmittelhygiene-Verordnung).
 */
#[Layout('layouts.app')]
class Haccp extends Component
{
    use ScopesTenantValidation;

    // Messpunkt anlegen
    public string $bezeichnung = '';

    public string $art = '';

    public string $grenzwert = '';

    // Messung erfassen
    public string $wert = '';

    public string $gemessen_am = '';

    public string $korrektur = '';

    // Korrekturmaßnahme nachtragen
    public string $korrektur_text = '';

    public function mount(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft', 'kueche'])), 403);

        $this->gemessen_am = now()->format('Y-m-d\TH:i');
    }

    public function messpunktSpeichern(): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft', 'kueche'])), 403);

        $data = $this->validate([
            'bezeichnung' => ['required', 'string', 'max:160'],
            'art' => ['required', 'string', 'in:'.implode(',', array_column(HaccpArt::cases(), 'value'))],
            'grenzwert' => ['nullable', 'numeric', 'between:-100,300'],
        ]);

        $artEnum = HaccpArt::from($data['art']);
        $gw = (is_string($data['grenzwert']) || is_numeric($data['grenzwert'])) && $data['grenzwert'] !== ''
            ? (float) $data['grenzwert']
            : $artEnum->grenzwertDefault();

        HaccpMesspunkt::create([
            'tenant_id' => app(CurrentTenant::class)->id(),
            'bezeichnung' => $data['bezeichnung'],
            'art' => $artEnum,
            'grenzwert' => $gw,
            'aktiv' => true,
        ]);

        $this->reset('bezeichnung', 'art', 'grenzwert');
        session()->flash('status', 'Messpunkt angelegt.');
    }

    public function messungErfassen(int $messpunktId, MessungErfassen $svc): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft', 'kueche'])), 403);

        $rules = [
            'wert' => ['required', 'numeric', 'between:-100,300'],
            'gemessen_am' => ['required', 'date', 'before_or_equal:now'],
            'korrektur' => ['nullable', 'string', 'max:1000'],
        ];

        try {
            $data = $this->validate($rules);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field, $messages[0]);
            }

            return;
        }

        // WHY(IDOR): messpunktId kommt als Methodenparameter, nicht als Property — tenant-scope manuell prüfen.
        $mp = HaccpMesspunkt::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($messpunktId);

        $svc->handle(
            $mp,
            (float) $data['wert'],
            $data['gemessen_am'],
            auth()->id(),
            ($data['korrektur'] ?? '') !== '' ? $data['korrektur'] : null,
        );

        $this->reset('wert', 'korrektur');
        $this->gemessen_am = now()->format('Y-m-d\TH:i');
        session()->flash('status', 'Messung erfasst.');
    }

    public function korrekturSetzen(int $messungId): void
    {
        $u = auth()->user();
        abort_unless($u && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft', 'kueche'])), 403);

        $data = $this->validate([
            'korrektur_text' => ['required', 'string', 'max:1000'],
        ]);

        // WHY(IDOR): messungId kommt als Methodenparameter — tenant-scope manuell prüfen.
        $messung = Temperaturmessung::where('tenant_id', app(CurrentTenant::class)->id())
            ->findOrFail($messungId);

        $messung->korrekturmassnahme = $data['korrektur_text'];
        $messung->save();

        $this->reset('korrektur_text');
        session()->flash('status', 'Korrekturmaßnahme dokumentiert.');
    }

    public function render(HaccpMonitor $monitor)
    {
        $tenantId = app(CurrentTenant::class)->id();

        $tagesblatt = $monitor->tagesblatt();

        $alleMesspunkte = HaccpMesspunkt::where('tenant_id', $tenantId)
            ->orderBy('bezeichnung')
            ->get();

        $artEnum = $this->art !== '' ? HaccpArt::tryFrom($this->art) : null;
        $artDefault = $artEnum?->grenzwertDefault();
        $artIstMax = $artEnum?->istMax();

        return view('livewire.catering.haccp', [
            'tagesblatt' => $tagesblatt,
            'alleMesspunkte' => $alleMesspunkte,
            'haccpArten' => HaccpArt::cases(),
            'artDefault' => $artDefault,
            'artIstMax' => $artIstMax,
        ]);
    }
}
