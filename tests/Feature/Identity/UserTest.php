<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;

it('verknüpft einen User mit einem Tenant', function () {
    $tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    expect($user->tenant->is($tenant))->toBeTrue();
});
