<?php

use App\Domains\Facility\Models\Legionellenbefund;
use App\Domains\Facility\Models\Probenahmestelle;
use App\Domains\Facility\Models\Trinkwasseranlage;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Facility\Trinkwasser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'TW-UI', 'slug' => 'tw-ui']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['admin', 'haustechnik', 'kueche'] as $r) {
        Role::findOrCreate($r);
    }
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('haustechnik');
    $this->actingAs($this->user);
});

it('rendert die Seite ohne Fehler', function () {
    Livewire::test(Trinkwasser::class)->assertOk();
});

it('legt eine Trinkwasseranlage an', function () {
    Livewire::test(Trinkwasser::class)
        ->set('bezeichnung', 'Warmwasseranlage Test')
        ->set('gebaeude', 'Haus 2')
        ->set('intervall', 12)
        ->call('anlageSpeichern')
        ->assertHasNoErrors();

    expect(
        Trinkwasseranlage::where('tenant_id', $this->tenant->id)
            ->where('bezeichnung', 'Warmwasseranlage Test')
            ->exists()
    )->toBeTrue();
});

it('legt eine Probenahmestelle an', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Anlage A',
        'ist_grossanlage' => true,
        'untersuchungsintervall_monate' => 12,
    ]);

    Livewire::test(Trinkwasser::class)
        ->set('stelle_bezeichnung', 'Austritt Erwärmer')
        ->set('stelle_ort', 'Technikraum')
        ->call('stelleSpeichern', $anlage->id)
        ->assertHasNoErrors();

    expect(
        Probenahmestelle::where('tenant_id', $this->tenant->id)
            ->where('trinkwasseranlage_id', $anlage->id)
            ->where('bezeichnung', 'Austritt Erwärmer')
            ->exists()
    )->toBeTrue();
});

it('erfasst einen Befund mit kbe=120, setzt ueberschreitung=true und zeigt den § 51-Pflicht-Kasten', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Anlage B',
        'ist_grossanlage' => true,
        'untersuchungsintervall_monate' => 12,
    ]);

    Livewire::test(Trinkwasser::class)
        ->set('untersucht_am', '2026-06-01')
        ->set('kbe', 120)
        ->call('befundErfassen', $anlage->id)
        ->assertHasNoErrors();

    $befund = Legionellenbefund::where('trinkwasseranlage_id', $anlage->id)->firstOrFail();
    expect($befund->ueberschreitung)->toBeTrue();
    expect($befund->kbe_pro_100ml)->toBe(120);

    // View zeigt den § 51-Pflicht-Kasten
    Livewire::test(Trinkwasser::class)
        ->assertSee('51 TrinkwV');
});

it('schliesst den Ueberschreitungs-Workflow ab via meldungSetzen', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Anlage C',
        'ist_grossanlage' => true,
        'untersuchungsintervall_monate' => 12,
    ]);
    $befund = Legionellenbefund::create([
        'tenant_id' => $this->tenant->id,
        'trinkwasseranlage_id' => $anlage->id,
        'untersucht_am' => '2026-06-01',
        'kbe_pro_100ml' => 150,
        'ueberschreitung' => true,
    ]);

    expect($anlage->offeneUeberschreitung())->toBeTrue();

    Livewire::test(Trinkwasser::class)
        ->set('meldung_massnahme', 'Thermische Desinfektion durchgeführt.')
        ->call('meldungSetzen', $befund->id)
        ->assertHasNoErrors();

    expect($anlage->fresh()->offeneUeberschreitung())->toBeFalse();
    $fresh = $befund->fresh();
    expect($fresh->gesundheitsamt_gemeldet_am)->not->toBeNull();
    expect($fresh->massnahme)->toBe('Thermische Desinfektion durchgeführt.');
});

it('verwehrt den Zugriff für Rolle kueche mit 403', function () {
    $kueche = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $kueche->assignRole('kueche');
    $this->actingAs($kueche);

    Livewire::test(Trinkwasser::class)->assertForbidden();
});

it('wirft findOrFail wenn Fremd-Tenant-Anlage beim Befund erfassen übergeben wird', function () {
    $fremdTenant = Tenant::create(['name' => 'Fremd', 'slug' => 'fremd']);
    $fremdAnlage = Trinkwasseranlage::create([
        'tenant_id' => $fremdTenant->id,
        'bezeichnung' => 'Fremde Anlage',
        'ist_grossanlage' => true,
        'untersuchungsintervall_monate' => 12,
    ]);

    $this->expectException(ModelNotFoundException::class);

    $component = new Trinkwasser;
    $component->untersucht_am = '2026-06-01';
    $component->kbe = 10;

    app(CurrentTenant::class)->set($this->tenant);

    $livewire = Livewire::test(Trinkwasser::class)
        ->set('untersucht_am', '2026-06-01')
        ->set('kbe', 10);

    // befundErfassen mit fremder anlageId → findOrFail wirft ModelNotFoundException
    $livewire->call('befundErfassen', $fremdAnlage->id);
})->throws(ModelNotFoundException::class);
