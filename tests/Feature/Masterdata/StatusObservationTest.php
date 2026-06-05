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

it('erfasst eine codierte Status-Observation (Harnkontinenz)', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('so_typ', 'harnkontinenz')
        ->set('so_wert_code', '450841000')
        ->call('addStatusObservation')
        ->assertHasNoErrors();

    $o = $this->resident->statusObservations()->first();
    expect($o->typ)->toBe('harnkontinenz')->and($o->wert_code)->toBe('450841000')
        ->and($o->anzeige())->toContain('intermittierend inkontinent');
});

it('weist einen ungültigen codierten Wert ab', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('so_typ', 'harnkontinenz')
        ->set('so_wert_code', '999')
        ->call('addStatusObservation')
        ->assertHasErrors(['so_wert_code']);
});

it('erfasst eine Freitext-Status-Observation (Atmung)', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('so_typ', 'atmung')
        ->set('so_wert_text', 'unauffällig')
        ->call('addStatusObservation')
        ->assertHasNoErrors();

    expect($this->resident->statusObservations()->first()->wert_text)->toBe('unauffällig');
});
