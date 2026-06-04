<?php

use App\Domains\Identity\Database\Seeders\{RolesSeeder, SuperAdminRoleSeeder};
use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(SuperAdminRoleSeeder::class);
});

it('erkennt super-admin unabhängig vom aktiven Team-Kontext', function () {
    $a = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $b = Tenant::create(['name' => 'B', 'slug' => 'b']);
    $u = User::factory()->create(['tenant_id' => $a->id]);

    app(CurrentTenant::class)->set($a);   // Team-Kontext = A
    $u->assignRole('super-admin');

    // Auch wenn der aktive Team-Kontext wechselt, bleibt super-admin erkannt:
    app(CurrentTenant::class)->set($b);
    expect($u->isSuperAdmin())->toBeTrue();
});
