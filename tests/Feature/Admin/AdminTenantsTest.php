<?php

use App\Domains\Identity\Database\Seeders\{RolesSeeder, SuperAdminRoleSeeder};
use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Admin\Tenants;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(SuperAdminRoleSeeder::class);
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->admin->assignRole('super-admin');
});

it('listet und erstellt Einrichtungen', function () {
    Livewire::actingAs($this->admin)->test(Tenants::class)
        ->set('name', 'Haus Neu')->set('slug', 'neu')
        ->call('save')->assertHasNoErrors();
    expect(Tenant::where('slug', 'neu')->exists())->toBeTrue();
});

it('verwehrt normalen Nutzern den Zugriff', function () {
    $nurse = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $nurse->assignRole('pflegefachkraft');
    $this->actingAs($nurse)->get('/admin/einrichtungen')->assertForbidden();
});
