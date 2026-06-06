<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Scheduling\Compliance\Betreuungsschluessel;
use App\Domains\Scheduling\Compliance\PersonalbemessungDefaults;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

it('berechnet Soll-VZÄ aus dem Pflegegrad-Mix (§ 113c PAW)', function () {
    Resident::factory()->count(10)->create(['pflegegrad' => 2, 'status' => 'aktiv']);
    Resident::factory()->count(5)->create(['pflegegrad' => 4, 'status' => 'aktiv']);

    $a = app(Betreuungsschluessel::class)->analysiere($this->tenant->id, 0, 0);

    // PG2: 0,1202+0,0675+0,1037 = 0,2914 ; PG4: 0,1627+0,1413+0,2463 = 0,5503
    expect($a->sollVzaeGesamt)->toBe(round(10 * 0.2914 + 5 * 0.5503, 4))
        ->and($a->sollVzaeFachkraft)->toBe(round(10 * 0.1037 + 5 * 0.2463, 4))
        ->and($a->sollWochenstundenGesamt)->toBe(round($a->sollVzaeGesamt * 38.5, 1))
        ->and($a->ampelGesamt())->toBe('rot') // ist 0
        ->and($a->deckungGesamt())->toBe(0);
});

it('skaliert mit dem PAW-Multiplikator und zeigt grün bei ausreichender Ist-Besetzung', function () {
    Resident::factory()->count(4)->create(['pflegegrad' => 3, 'status' => 'aktiv']);
    $config = PersonalbemessungDefaults::ensureConfig($this->tenant->id);
    $config->update(['paw_multiplikator' => 2.0]);

    $base = round(4 * (0.1449 + 0.1074 + 0.1551), 4);
    $a = app(Betreuungsschluessel::class)->analysiere($this->tenant->id, 9999, 9999);

    expect($a->sollVzaeGesamt)->toBe(round($base * 2.0, 4))
        ->and($a->ampelGesamt())->toBe('gruen');
});
