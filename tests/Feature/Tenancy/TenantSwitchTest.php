<?php

use App\Domains\Identity\Database\Seeders\{RolesSeeder, SuperAdminRoleSeeder};
use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Admin\TenantSwitcher;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(SuperAdminRoleSeeder::class);
    $this->a = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $this->b = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($this->a);
    $this->admin = User::factory()->create(['tenant_id' => $this->a->id]);
    $this->admin->assignRole('super-admin');
});

it('schaltet den aktiven Mandanten in die Session', function () {
    Livewire::actingAs($this->admin)->test(TenantSwitcher::class)
        ->call('switchTo', $this->b->id);
    expect(session('active_tenant_id'))->toBe($this->b->id);
});

it('kann nach einem Wechsel erneut wechseln', function () {
    Livewire::actingAs($this->admin)->test(TenantSwitcher::class)
        ->call('switchTo', $this->b->id)
        ->call('switchTo', $this->a->id);
    expect(session('active_tenant_id'))->toBe($this->a->id);
});
