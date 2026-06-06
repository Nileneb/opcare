<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Auth\ChallengeTwoFactor;
use App\Livewire\Auth\EnrollTwoFactor;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->tenant = Tenant::create(['name' => 'Testheim', 'slug' => 'testheim']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('schließt das Enrollment mit gültigem TOTP-Code ab und erzeugt Recovery-Codes', function () {
    $user = User::factory()->withoutTwoFactor()->create(['tenant_id' => $this->tenant->id]);

    $component = Livewire::actingAs($user)->test(EnrollTwoFactor::class);
    $secret = $component->get('secret');
    expect($secret)->not->toBeEmpty();

    $otp = (new Google2FA)->getCurrentOtp($secret);
    $component->set('code', $otp)->call('confirm')->assertSet('confirmed', true);

    $user->refresh();
    expect($user->two_factor_confirmed_at)->not->toBeNull()
        ->and($user->two_factor_secret)->toBe($secret)
        ->and($user->two_factor_recovery_codes)->toHaveCount(8);
});

it('weist einen ungültigen Enrollment-Code ab', function () {
    $user = User::factory()->withoutTwoFactor()->create(['tenant_id' => $this->tenant->id]);

    Livewire::actingAs($user)->test(EnrollTwoFactor::class)
        ->set('code', '000000')
        ->call('confirm')
        ->assertHasErrors('code');

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

it('leitet eingeloggte Benutzer ohne 2FA zwingend zum Enrollment', function () {
    $user = User::factory()->withoutTwoFactor()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($user)->get(route('bewohner'))->assertRedirect(route('two-factor.enroll'));
});

it('lässt eingerichtete Benutzer ungehindert in die App', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]); // Factory-Default: 2FA aktiv

    $this->actingAs($user)->get(route('two-factor.enroll'))->assertRedirect(route('overview'));
});

it('authentifiziert über die TOTP-Challenge', function () {
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

    session(['mfa.pending_id' => $user->id]);
    $otp = (new Google2FA)->getCurrentOtp($secret);

    Livewire::test(ChallengeTwoFactor::class)
        ->set('code', $otp)
        ->call('verify')
        ->assertRedirect(route('overview'));

    $this->assertAuthenticatedAs($user);
});

it('akzeptiert einen Recovery-Code einmalig und verbraucht ihn', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->forceFill([
        'two_factor_secret' => (new Google2FA)->generateSecretKey(),
        'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
        'two_factor_confirmed_at' => now(),
    ])->save();

    session(['mfa.pending_id' => $user->id]);

    Livewire::test(ChallengeTwoFactor::class)
        ->set('code', 'AAAAA-BBBBB')
        ->call('verify')
        ->assertRedirect(route('overview'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->two_factor_recovery_codes)->toBe(['CCCCC-DDDDD']);
});
