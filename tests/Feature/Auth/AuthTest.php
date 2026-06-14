<?php

use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $tenant = Tenant::create(['name' => 'Testheim', 'slug' => 'testheim']);
    app(CurrentTenant::class)->set($tenant);
    $this->tenant = $tenant;
});

it('zeigt Gästen die Login-Seite', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Anmelden')
        ->assertDontSee('Noch kein Konto');
});

it('blockiert die registrierung und gibt 403 zurück', function () {
    $this->get('/register')->assertStatus(403);
    $this->post('/register', [])->assertStatus(403);
});

it('registriert einen neuen Benutzer und meldet ihn an', function () {
    Livewire::test(Register::class)
        ->set('name', 'Neue Kraft')
        ->set('email', 'neu@opcare.local')
        ->set('password', 'passwort-123')
        ->set('password_confirmation', 'passwort-123')
        ->call('register')
        // WHY(Track B, MFA-Pflicht): neue Konten gehen direkt ins Enrollment
        ->assertRedirect(route('two-factor.enroll'));

    $this->assertAuthenticated();
    $user = User::where('email', 'neu@opcare.local')->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('pflegefachkraft'))->toBeTrue()
        ->and($user->tenant_id)->toBe($this->tenant->id);
});

it('loggt einen Benutzer ohne 2FA ein und erzwingt das Enrollment', function () {
    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'b@opcare.local', 'password' => 'geheim-123', 'tenant_id' => $this->tenant->id,
    ]);

    Livewire::test(Login::class)
        ->set('email', 'b@opcare.local')
        ->set('password', 'geheim-123')
        ->call('login')
        ->assertRedirect(route('two-factor.enroll'));

    $this->assertAuthenticatedAs($user);
});

it('verlangt bei aktivem 2FA die Challenge und authentifiziert noch nicht', function () {
    User::factory()->create([
        'email' => 'mfa@opcare.local', 'password' => 'geheim-123', 'tenant_id' => $this->tenant->id,
    ]);

    Livewire::test(Login::class)
        ->set('email', 'mfa@opcare.local')
        ->set('password', 'geheim-123')
        ->call('login')
        ->assertRedirect(route('two-factor.challenge'));

    $this->assertGuest();
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

it('zeigt das öffentliche bewerbungsformular', function () {
    $this->get('/bewerben')
        ->assertOk()
        ->assertSee('Bewerbung einreichen');
});

it('zeigt die hr einladungsseite für berechtigte nutzer', function () {
    $user = User::factory()->create(['email' => 'hr@opcare.local', 'password' => 'pw-123456', 'tenant_id' => $this->tenant->id]);
    $user->assignRole('admin');

    $this->actingAs($user)->get('/hr/einladungen')
        ->assertOk()
        ->assertSee('Einladungen versenden');
});

it('zeigt die einladungsseite für gültige tokens', function () {
    $invitation = Invitation::factory()->create();

    $this->get('/invite/'.$invitation->token)
        ->assertOk()
        ->assertSee('Einladung annehmen')
        ->assertSee($invitation->email);
});

it('zeigt eine fehlerseite für abgelaufene oder bereits genutzte einladungen', function () {
    $invitation = Invitation::factory()->create(['expires_at' => now()->subHour()]);

    $this->get('/invite/'.$invitation->token)
        ->assertOk()
        ->assertSee('Einladung ungültig')
        ->assertSee('Personalabteilung');
});

it('queues invitation mail when the mailable is sent', function () {
    Mail::fake();
    $invitation = Invitation::factory()->create();

    Mail::to($invitation->email)->queue(new InvitationMail($invitation));

    Mail::assertQueued(InvitationMail::class, function (InvitationMail $mail) use ($invitation) {
        return $mail->hasTo($invitation->email)
            && $mail->invitation->is($invitation);
    });
});
