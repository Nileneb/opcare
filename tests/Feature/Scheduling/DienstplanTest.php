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

it('verweigert das Zuweisen eines Mitarbeiters aus einem fremden Mandanten', function () {
    $leitung = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $leitung->assignRole('admin');
    $this->actingAs($leitung);

    $fremderTenant = Tenant::create(['name' => 'B', 'slug' => 'b']);
    $fremderUser = User::factory()->create(['tenant_id' => $fremderTenant->id]);

    Livewire::test(Dienstplan::class)
        ->set('userId', $fremderUser->id)
        ->set('shiftId', $this->shift->id)
        ->set('dienstAm', '2026-06-15')
        ->call('zuweisen')
        ->assertHasErrors('userId');

    expect(ShiftAssignment::query()->count())->toBe(0);
});

it('verweigert das Zuweisen einer Schicht aus einem fremden Mandanten', function () {
    $leitung = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $leitung->assignRole('admin');
    $mitarbeiter = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($leitung);

    $fremderTenant = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($fremderTenant);
    $fremdeSchicht = Shift::create(['name' => 'FremdFrüh', 'kind' => ShiftKind::Frueh, 'beginn' => '06:00', 'ende' => '14:00']);
    app(CurrentTenant::class)->set($this->tenant);

    Livewire::test(Dienstplan::class)
        ->set('userId', $mitarbeiter->id)
        ->set('shiftId', $fremdeSchicht->id)
        ->set('dienstAm', '2026-06-15')
        ->call('zuweisen')
        ->assertHasErrors('shiftId');

    expect(ShiftAssignment::query()->count())->toBe(0);
});
