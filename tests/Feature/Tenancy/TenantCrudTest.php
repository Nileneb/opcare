<?php

use App\Domains\Identity\Actions\CreateTenant;
use App\Domains\Identity\Data\TenantData;

it('erstellt eine Einrichtung über die Action', function () {
    $tenant = app(CreateTenant::class)->handle(new TenantData(
        name: 'Haus Sonnenhof', slug: 'sonnenhof', traeger: 'Diakonie', ik_nummer: '260999999',
    ));
    expect($tenant->name)->toBe('Haus Sonnenhof')->and($tenant->aktiv)->toBeTrue();
});
