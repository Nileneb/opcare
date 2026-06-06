<?php

use App\Domains\Identity\Database\Seeders\DemoSeeder;
use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Pflegeplanung;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(DemoSeeder::class);
    $this->admin = User::query()->where('email', 'admin@opcare.local')->first();
    // WHY(Track B, MFA): Seiten-Zugriff setzt abgeschlossenes 2FA-Enrollment voraus (Enrollment-Middleware).
    $this->admin->forceFill(['two_factor_confirmed_at' => now()])->save();
    app(CurrentTenant::class)->set($this->admin->tenant);
});

it('rendert das SIS-Dashboard mit echten Bewohner- und Lebensbereichs-Daten', function () {
    Livewire::actingAs($this->admin)
        ->test(Pflegeplanung::class)
        ->assertOk()
        ->assertSee('Maria Schneider')
        ->assertSee('Kognition')
        ->assertSee('Mobilität')
        ->assertSee('Lebensbereiche');
});

it('liefert die Pflegeplanungs-Route für eingeloggte Nutzer aus', function () {
    $this->actingAs($this->admin)->get('/pflegeplanung')
        ->assertOk()
        ->assertSee('Bergische Diakonie', false);
});

it('schützt die App-Routen vor Gästen', function () {
    $this->get('/pflegeplanung')->assertRedirect('/login');
    $this->get('/')->assertRedirect('/login');
});
