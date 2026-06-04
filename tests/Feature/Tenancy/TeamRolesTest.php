<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use Spatie\Permission\PermissionRegistrar;

beforeEach(fn () => $this->seed(\App\Domains\Identity\Database\Seeders\RolesSeeder::class));

it('isoliert Rollen je Mandant (teams)', function () {
    $a = Tenant::create(['name' => 'Haus A', 'slug' => 'a']);
    $b = Tenant::create(['name' => 'Haus B', 'slug' => 'b']);

    app(CurrentTenant::class)->set($a);
    app(PermissionRegistrar::class)->setPermissionsTeamId($a->id);
    $user = User::factory()->create(['tenant_id' => $a->id]);
    $user->assignRole('pflegefachkraft');
    expect($user->hasRole('pflegefachkraft'))->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId($b->id);
    $user->unsetRelation('roles');
    expect($user->hasRole('pflegefachkraft'))->toBeFalse();
});
