<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Building;
use App\Domains\Masterdata\Models\Floor;
use App\Domains\Masterdata\Models\Physician;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Models\Room;
use App\Domains\Masterdata\Models\Station;
use App\Livewire\Facility;
use App\Livewire\Residents;
use App\Livewire\ResidentShow;
use App\Livewire\Speech;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Regression gegen die IDOR-Klasse: ungescopte `exists:`-Validierung umging den TenantScope.
 * Der Trait ScopesTenantValidation::tenantExists() bindet jede FK-Validierung an den aktuellen Mandanten.
 * Hier wird je Komponente geprüft, dass eine FREMD-mandantige ID abgelehnt wird.
 */
beforeEach(function () {
    foreach (['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht'] as $r) {
        Role::findOrCreate($r);
    }

    // Mandant B trägt die FREMDEN Datensätze.
    $this->foreign = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($this->foreign);
    $this->foreignBuilding = Building::create(['name' => 'Fremdbau']);
    $foreignFloor = Floor::create(['building_id' => $this->foreignBuilding->id, 'name' => 'F-EG']);
    $foreignStation = Station::create(['floor_id' => $foreignFloor->id, 'name' => 'F-Stat']);
    $this->foreignRoom = Room::create(['station_id' => $foreignStation->id, 'nummer' => 'F-1', 'betten' => 1]);
    $this->foreignPhysician = Physician::create(['name' => 'Dr. Fremd']);
    $this->foreignResident = Resident::factory()->create();

    // Mandant A ist der aktive Kontext des angemeldeten Nutzers.
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);
});

it('Fac::addFloor lehnt ein fremd-mandantiges Gebäude ab', function () {
    Livewire::test(Facility::class)
        ->set('f_building', $this->foreignBuilding->id)
        ->set('f_name', 'EG')
        ->call('addFloor')
        ->assertHasErrors('f_building');
});

it('Residents::save lehnt ein fremd-mandantiges Zimmer ab', function () {
    Livewire::test(Residents::class)
        ->set('name', 'Neu Bewohner')
        ->set('geburtsdatum', '1940-01-01')
        ->set('geschlecht', 'w')
        ->set('aufnahme_am', '2026-06-04')
        ->set('room_id', $this->foreignRoom->id)
        ->call('save')
        ->assertHasErrors('room_id');
});

it('ResidentShow::attachPhysician lehnt eine:n fremd-mandantige:n Arzt/Ärztin ab', function () {
    $resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);

    Livewire::test(ResidentShow::class, ['resident' => $resident])
        ->set('phys_id', $this->foreignPhysician->id)
        ->call('attachPhysician')
        ->assertHasErrors('phys_id');

    expect($resident->physicians()->count())->toBe(0);
});

it('Speech::startDemo lehnt eine:n fremd-mandantige:n Bewohner:in ab', function () {
    Livewire::test(Speech::class)
        ->set('resident_id', $this->foreignResident->id)
        ->set('kontext', 'mobilitaet')
        ->call('startDemo')
        ->assertHasErrors('resident_id');
});

it('akzeptiert eine EIGENE Mandanten-ID (Gegenprobe)', function () {
    $eigenesGebaeude = Building::create(['name' => 'Eigenbau']);

    Livewire::test(Facility::class)
        ->set('f_building', $eigenesGebaeude->id)
        ->set('f_name', 'EG')
        ->call('addFloor')
        ->assertHasNoErrors();
});
