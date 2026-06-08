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

it('erfasst einen Krankenhausaufenthalt mit Enddatum', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('hos_ende', '2026-04-15')
        ->set('hos_grund', 'Pneumonie')
        ->call('addHospitalStay')
        ->assertHasNoErrors();

    $stay = $this->resident->hospitalStays()->first();
    expect($stay->ende->toDateString())->toBe('2026-04-15')
        ->and($stay->grund)->toBe('Pneumonie');
});

it('verlangt ein Enddatum für den Krankenhausaufenthalt', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->call('addHospitalStay')
        ->assertHasErrors(['hos_ende']);
});

it('erfasst eine Empfehlung an die aufnehmende Einrichtung', function () {
    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->set('rec_text', 'Sturzprophylaxe fortführen')
        ->call('addRecommendation')
        ->assertHasNoErrors();

    expect($this->resident->recommendations()->first()->empfehlung)->toBe('Sturzprophylaxe fortführen');
});

it('entfernt einen Krankenhausaufenthalt', function () {
    $stay = $this->resident->hospitalStays()->create(['ende' => '2026-04-15']);

    Livewire::actingAs($this->user)->test(ResidentShow::class, ['resident' => $this->resident])
        ->call('removeHospitalStay', $stay->id)
        ->assertHasNoErrors();

    expect($this->resident->hospitalStays()->count())->toBe(0);
});
