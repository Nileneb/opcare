<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;

it('hält Einrichtungs-Stammdaten und Nutzer', function () {
    $t = Tenant::create([
        'name' => 'Haus Aprath', 'slug' => 'aprath',
        'traeger' => 'Bergische Diakonie', 'ik_nummer' => '260123456',
        'settings' => ['stichtag_quartal' => 1], 'aktiv' => true,
    ]);
    User::factory()->create(['tenant_id' => $t->id]);

    expect($t->aktiv)->toBeTrue()
        ->and($t->settings['stichtag_quartal'])->toBe(1)
        ->and($t->users)->toHaveCount(1);
});
