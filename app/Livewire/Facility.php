<?php

namespace App\Livewire;

use App\Domains\Masterdata\Actions\CreateBuilding;
use App\Domains\Masterdata\Data\BuildingData;
use App\Domains\Masterdata\Models\Building;
use App\Domains\Masterdata\Models\Floor;
use App\Domains\Masterdata\Models\HealthInsurance;
use App\Domains\Masterdata\Models\IcdCode;
use App\Domains\Masterdata\Models\Physician;
use App\Domains\Masterdata\Models\Room;
use App\Domains\Masterdata\Models\Station;
use App\Support\Concerns\ScopesTenantValidation;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Facility extends Component
{
    use ScopesTenantValidation;

    public string $b_name = '';

    public ?int $f_building = null;

    public string $f_name = '';

    public ?int $s_floor = null;

    public string $s_name = '';

    public ?int $r_station = null;

    public string $r_nummer = '';

    public int $r_betten = 1;

    public string $icd_code = '';

    public string $icd_bez = '';

    public string $ins_name = '';

    public string $ins_ik = '';

    public string $phys_name = '';

    public string $phys_fach = '';

    public string $phys_kontakt = '';

    public function addBuilding(CreateBuilding $create): void
    {
        $this->validate(['b_name' => ['required', 'string', 'max:255']]);
        $create->handle(new BuildingData(name: $this->b_name));
        $this->reset('b_name');
        session()->flash('status', 'Gebäude angelegt.');
    }

    public function addFloor(): void
    {
        $this->validate(['f_building' => ['required', $this->tenantExists('buildings')], 'f_name' => ['required', 'string']]);
        Floor::create(['building_id' => $this->f_building, 'name' => $this->f_name]);
        $this->reset('f_name');
        session()->flash('status', 'Etage angelegt.');
    }

    public function addStation(): void
    {
        $this->validate(['s_floor' => ['required', $this->tenantExists('floors')], 's_name' => ['required', 'string']]);
        Station::create(['floor_id' => $this->s_floor, 'name' => $this->s_name]);
        $this->reset('s_name');
        session()->flash('status', 'Station angelegt.');
    }

    public function addRoom(): void
    {
        $this->validate(['r_station' => ['required', $this->tenantExists('stations')], 'r_nummer' => ['required', 'string'], 'r_betten' => ['required', 'integer', 'min:1']]);
        Room::create(['station_id' => $this->r_station, 'nummer' => $this->r_nummer, 'betten' => $this->r_betten]);
        $this->reset('r_nummer');
        $this->r_betten = 1;
        session()->flash('status', 'Zimmer angelegt.');
    }

    public function addIcd(): void
    {
        $this->validate(['icd_code' => ['required', 'string', 'unique:icd_codes,code'], 'icd_bez' => ['required', 'string']]);
        IcdCode::create(['code' => $this->icd_code, 'bezeichnung' => $this->icd_bez]);
        $this->reset('icd_code', 'icd_bez');
        session()->flash('status', 'ICD-Code angelegt.');
    }

    public function addInsurance(): void
    {
        $this->validate(['ins_name' => ['required', 'string']]);
        HealthInsurance::create(['name' => $this->ins_name, 'ik_nummer' => $this->ins_ik ?: null]);
        $this->reset('ins_name', 'ins_ik');
        session()->flash('status', 'Krankenkasse angelegt.');
    }

    public function addPhysician(): void
    {
        $this->validate(['phys_name' => ['required', 'string']]);
        Physician::create(['name' => $this->phys_name, 'fachrichtung' => $this->phys_fach ?: null, 'kontakt' => $this->phys_kontakt ?: null]);
        $this->reset('phys_name', 'phys_fach', 'phys_kontakt');
        session()->flash('status', 'Arzt/Ärztin angelegt.');
    }

    public function render()
    {
        return view('livewire.facility', [
            'buildings' => Building::orderBy('name')->get(),
            'floors' => Floor::with('building')->orderBy('name')->get(),
            'stations' => Station::with('floor')->orderBy('name')->get(),
            'rooms' => Room::with('station')->orderBy('nummer')->get(),
            'icdCodes' => IcdCode::orderBy('code')->get(),
            'insurances' => HealthInsurance::orderBy('name')->get(),
            'physicians' => Physician::orderBy('name')->get(),
        ]);
    }
}
