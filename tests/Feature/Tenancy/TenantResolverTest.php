<?php

use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\TenantResolver;

beforeEach(fn () => $this->seed(\App\Domains\Identity\Database\Seeders\RolesSeeder::class));

it('löst für normale Nutzer den eigenen Tenant auf', function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $u = User::factory()->create(['tenant_id' => $t->id]);

    expect(app(TenantResolver::class)->resolveFor($u, sessionTenantId: null)->id)->toBe($t->id);
});

it('lässt Super-Admins per Session zwischen Tenants wechseln', function () {
    $a = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $b = Tenant::create(['name' => 'B', 'slug' => 'b']);
    $u = User::factory()->create(['tenant_id' => $a->id]);
    $this->seed(\App\Domains\Identity\Database\Seeders\SuperAdminRoleSeeder::class);
    app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($a->id);
    $u->assignRole('super-admin');

    expect(app(TenantResolver::class)->resolveFor($u, sessionTenantId: $b->id)->id)->toBe($b->id);
});
