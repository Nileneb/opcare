<?php

use App\Models\Application;
use App\Models\Invitation;

it('creates an invitation and evaluates expiry and acceptance state', function () {
    $expiredInvitation = Invitation::factory()->create([
        'expires_at' => now()->subDay(),
    ]);

    expect($expiredInvitation->isExpired())->toBeTrue()
        ->and($expiredInvitation->isAccepted())->toBeFalse();

    $acceptedInvitation = Invitation::factory()->accepted()->create();

    expect($acceptedInvitation->isExpired())->toBeFalse()
        ->and($acceptedInvitation->isAccepted())->toBeTrue();
});

it('creates an application and returns the full_name accessor', function () {
    $application = Application::factory()->create([
        'first_name' => 'Max',
        'last_name' => 'Mustermann',
    ]);

    expect($application->full_name)->toBe('Max Mustermann');
});
