<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $tenant = Tenant::create(['name' => 'Testheim', 'slug' => 'testheim']);
    app(CurrentTenant::class)->set($tenant);
    $this->tenant = $tenant;
});

it('zeigt Gästen die Login-Seite', function () {
    $this->get('/login')->assertOk()->assertSee('Anmelden');
});

it('registriert einen neuen Benutzer und meldet ihn an', function () {
    Livewire::test(Register::class)
        ->set('name', 'Neue Kraft')
        ->set('email', 'neu@opcare.local')
        ->set('password', 'passwort-123')
        ->set('password_confirmation', 'passwort-123')
        ->call('register')
        ->assertRedirect(route('overview'));

    $this->assertAuthenticated();
    $user = User::where('email', 'neu@opcare.local')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('pflegefachkraft'))->toBeTrue()
        ->and($user->tenant_id)->toBe($this->tenant->id);
});

it('meldet einen bestehenden Benutzer mit korrektem Passwort an', function () {
    $user = User::create([
        'name' => 'Bestand', 'email' => 'b@opcare.local',
        'password' => 'geheim-123', 'tenant_id' => $this->tenant->id,
    ]);

    Livewire::test(Login::class)
        ->set('email', 'b@opcare.local')
        ->set('password', 'geheim-123')
        ->call('login')
        ->assertRedirect(route('overview'));

    $this->assertAuthenticatedAs($user);
});

it('weist falsche Zugangsdaten ab', function () {
    User::create(['name' => 'X', 'email' => 'x@opcare.local', 'password' => 'richtig-123', 'tenant_id' => $this->tenant->id]);

    Livewire::test(Login::class)
        ->set('email', 'x@opcare.local')
        ->set('password', 'falsch')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

it('meldet einen Benutzer ab', function () {
    $user = User::create(['name' => 'Y', 'email' => 'y@opcare.local', 'password' => 'pw-123456', 'tenant_id' => $this->tenant->id]);

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');
    $this->assertGuest();
});
