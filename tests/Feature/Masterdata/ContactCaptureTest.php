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

it('erfasst eine Kontaktperson mit Benachrichtigungs-Flag', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('con_name', 'Anna Schneider')
        ->set('con_beziehung', 'Tochter')
        ->set('con_telefon', '0201 123')
        ->set('con_benachrichtigen', true)
        ->call('addContact')
        ->assertHasNoErrors();

    $c = $this->resident->contacts()->first();
    expect($c->name)->toBe('Anna Schneider')->and($c->benachrichtigen)->toBeTrue();
});

it('verlangt einen Namen', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->call('addContact')
        ->assertHasErrors(['con_name']);
});
