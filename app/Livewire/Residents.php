<?php

namespace App\Livewire;

use App\Domains\Masterdata\Actions\CreateResident;
use App\Domains\Masterdata\Data\ResidentData;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\Room;
use App\Support\Concerns\ScopesTenantValidation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Residents extends Component
{
    use ScopesTenantValidation;

    public bool $showForm = false;

    public string $name = '';

    public string $geburtsdatum = '';

    public string $geschlecht = 'w';

    public ?int $pflegegrad = null;

    public string $aufnahme_am = '';

    public ?int $room_id = null;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'geburtsdatum' => ['required', 'date'],
            'geschlecht' => ['required', 'in:m,w,d'],
            'pflegegrad' => ['nullable', 'integer', 'between:1,5'],
            'aufnahme_am' => ['required', 'date'],
            'room_id' => ['nullable', 'integer', $this->tenantExists('rooms')],
        ];
    }

    public function save(CreateResident $createResident): void
    {
        $data = $this->validate();

        $createResident->handle(new ResidentData(
            name: $data['name'],
            geburtsdatum: $data['geburtsdatum'],
            geschlecht: $data['geschlecht'],
            aufnahme_am: $data['aufnahme_am'],
            pflegegrad: $data['pflegegrad'],
            status: 'aktiv',
            room_id: $data['room_id'],
        ));

        $this->reset('name', 'geburtsdatum', 'pflegegrad', 'aufnahme_am', 'room_id', 'showForm');
        session()->flash('status', 'Bewohner:in angelegt.');
    }

    public function render()
    {
        return view('livewire.residents', [
            'residents' => Resident::with('room')->orderBy('name')->get(),
            'rooms' => Room::with('station')->orderBy('nummer')->get(),
        ]);
    }
}
