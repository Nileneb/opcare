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

it('erfasst eine Allergie mit Kategorie und Kritikalität', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('alg_substanz', 'Penicillin')
        ->set('alg_kategorie', 'medikament')
        ->set('alg_kritikalitaet', 'hoch')
        ->set('alg_reaktion', 'Hautausschlag')
        ->call('addAllergy')
        ->assertHasNoErrors();

    $allergy = $this->resident->allergies()->first();
    expect($allergy->substanz)->toBe('Penicillin')
        ->and($allergy->kritikalitaet)->toBe('hoch')
        ->and($allergy->erfasst_am)->not->toBeNull();
});

it('verlangt eine Substanz', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->call('addAllergy')
        ->assertHasErrors(['alg_substanz']);
});

it('entfernt einen Eintrag', function () {
    $a = $this->resident->allergies()->create(['substanz' => 'Erdnuss', 'typ' => 'allergie', 'kategorie' => 'nahrung']);

    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->call('removeAllergy', $a->id)
        ->assertHasNoErrors();

    expect($this->resident->allergies()->count())->toBe(0);
});
