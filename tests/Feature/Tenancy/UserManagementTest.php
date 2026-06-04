<?php

use App\Domains\Identity\Actions\{CreateUser, AssignRole};
use App\Domains\Identity\Data\AdminUserData;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(fn () => $this->seed(\App\Domains\Identity\Database\Seeders\RolesSeeder::class));

it('legt einen Mitarbeitenden im aktiven Mandanten an und vergibt eine Rolle', function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);

    $user = app(CreateUser::class)->handle(new AdminUserData(
        name: 'Pia Pflege', email: 'pia@opcare.local', password: 'geheim-123', role: 'pflegefachkraft',
    ));

    expect($user->tenant_id)->toBe($t->id)
        ->and($user->hasRole('pflegefachkraft'))->toBeTrue();
});
