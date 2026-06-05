<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Livewire\ResidentShow;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['admin', 'pflegefachkraft', 'pflegehilfskraft', 'leserecht'] as $r) {
        Role::findOrCreate($r);
    }
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
    $this->user = User::factory()->create(['tenant_id' => $t->id]);
    $this->user->assignRole('admin');
    $this->resident = Resident::factory()->create();
});

it('erfasst ein Medizinprodukt', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('dev_bezeichnung', 'Rollator')
        ->set('dev_kategorie', 'hilfsmittel')
        ->call('addDevice')
        ->assertHasNoErrors();

    expect($this->resident->devices()->first()->bezeichnung)->toBe('Rollator');
});

it('verlangt eine Bezeichnung', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->call('addDevice')
        ->assertHasErrors(['dev_bezeichnung']);
});
