<?php

use App\Domains\Identity\Models\Tenant;

it('legt einen Tenant mit slug an', function () {
    $tenant = Tenant::create(['name' => 'Haus Sonnenschein', 'slug' => 'haus-sonnenschein']);

    expect($tenant->name)->toBe('Haus Sonnenschein')
        ->and($tenant->slug)->toBe('haus-sonnenschein')
        ->and(Tenant::count())->toBe(1);
});
