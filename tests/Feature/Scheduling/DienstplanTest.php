<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Enums\ShiftKind;
use App\Domains\Scheduling\Models\Shift;
use App\Domains\Scheduling\Models\ShiftAssignment;
use App\Livewire\Scheduling\Dienstplan;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('admin');
    Role::findOrCreate('leserecht');
    $this->shift = Shift::create(['name' => 'Früh', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00']);
});

it('verweigert Pflegekraft mit nur Leserecht die Dienstplan-Pflege', function () {
    $pfk = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $pfk->assignRole('leserecht');
    $this->actingAs($pfk);

    Livewire::test(Dienstplan::class)->assertForbidden();
});

it('lässt die Leitung eine Schicht zuweisen', function () {
    $leitung = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $leitung->assignRole('admin');
    $mitarbeiter = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($leitung);

    Livewire::test(Dienstplan::class)
        ->set('userId', $mitarbeiter->id)
        ->set('shiftId', $this->shift->id)
        ->set('dienstAm', '2026-06-15')
        ->call('zuweisen')
        ->assertHasNoErrors();

    expect(ShiftAssignment::where('user_id', $mitarbeiter->id)->count())->toBe(1);
});
