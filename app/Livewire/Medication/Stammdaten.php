<?php

namespace App\Livewire\Medication;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\TradeForm;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Stammdaten extends Component
{
    public string $name = '';

    public string $wirkstoff = '';

    public string $staerke = '';

    public ?int $tradeFormId = null;

    public ?string $atcCode = null;

    public ?string $pzn = null;

    public bool $btm = false;

    public function mount(): void
    {
        // WHY: Stamm-Pflege ist Fachkraft/Leitung — Guard in mount UND Action (Nav-Verstecken genügt nicht).
        abort_unless($this->darfPflegen(), 403);
    }

    private function darfPflegen(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->isSuperAdmin() || $u?->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function speichern(): void
    {
        abort_unless($this->darfPflegen(), 403);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'wirkstoff' => ['nullable', 'string', 'max:255'],
            'staerke' => ['nullable', 'string', 'max:120'],
            // WHY(IDOR-Prevention): exists: auf trade_forms muss mandantengebunden sein, sonst Cross-Tenant-Referenz möglich.
            'tradeFormId' => ['nullable', Rule::exists('trade_forms', 'id')->where('tenant_id', app(CurrentTenant::class)->id())],
            'atcCode' => ['nullable', 'string', 'max:16'],
            'pzn' => ['nullable', 'string', 'max:16'],
            'btm' => ['boolean'],
        ]);

        MedProduct::create([
            'name' => $data['name'],
            'wirkstoff' => $data['wirkstoff'] ?: null,
            'staerke' => $data['staerke'] ?: null,
            'trade_form_id' => $data['tradeFormId'] ?? null,
            'atc_code' => $data['atcCode'] ?? null,
            'pzn' => $data['pzn'] ?? null,
            'btm' => $data['btm'],
        ]);

        $this->reset('name', 'wirkstoff', 'staerke', 'atcCode', 'pzn', 'btm');
        session()->flash('status', 'Produkt angelegt.');
    }

    public function render()
    {
        return view('livewire.medication.stammdaten', [
            'produkte' => MedProduct::with('tradeForm')->orderBy('name')->get(),
            'tradeForms' => TradeForm::orderBy('name')->get(),
        ]);
    }
}
