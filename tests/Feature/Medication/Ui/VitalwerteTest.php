<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Models\VitalReading;
use App\Livewire\Medication\Vitalwerte;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('pflegehilfskraft');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('pflegehilfskraft');
    $this->actingAs($this->user);
    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('erfasst einen Vitalwert (Puls) am Bett', function () {
    Livewire::test(Vitalwerte::class, ['resident' => $this->resident])
        ->set('typ', 'puls')
        ->set('wert', 72)
        ->call('erfassen')
        ->assertHasNoErrors();

    expect(VitalReading::where('resident_id', $this->resident->id)->where('typ', 'puls')->count())->toBe(1);
});
