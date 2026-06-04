<?php

use App\Domains\Identity\Database\Seeders\DemoSeeder;
use App\Domains\Identity\Database\Seeders\RolesSeeder;
use App\Livewire\Pflegeplanung;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    $this->seed(DemoSeeder::class);
});

it('rendert das SIS-Dashboard mit echten Bewohner- und Lebensbereichs-Daten', function () {
    Livewire::test(Pflegeplanung::class)
        ->assertOk()
        ->assertSee('Maria Schneider')
        ->assertSee('Kognition')
        ->assertSee('Mobilität')
        ->assertSee('Pflegeplanung')
        ->assertSee('Lebensbereiche');
});

it('liefert die Pflegeplanungs-Route aus', function () {
    $this->get('/pflegeplanung')->assertOk()->assertSee('Bergische Diakonie', false);
});
