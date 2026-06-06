<?php

use App\Domains\Catering\Enums\EssenswunschArt;
use App\Domains\Catering\Enums\LmivAllergen;
use App\Domains\Catering\Enums\Mahlzeit;
use App\Domains\Catering\Models\Essenswunsch;
use App\Domains\Catering\Models\Gericht;
use App\Domains\Catering\Models\Menuewahl;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->maria = Resident::create(['name' => 'Maria', 'geburtsdatum' => '1940-01-01', 'geschlecht' => 'w', 'aufnahme_am' => '2024-01-01', 'status' => 'aktiv']);
});

it('hält einen allgemeinen Essenswunsch je Bewohner', function () {
    $w = Essenswunsch::create(['resident_id' => $this->maria->id, 'art' => EssenswunschArt::Abneigung, 'text' => 'kein Fisch']);

    expect($w->art)->toBe(EssenswunschArt::Abneigung)
        ->and($w->art->label())->toBe('mag nicht')
        ->and($w->resident->id)->toBe($this->maria->id);
});

it('hält die Menüwahl eines Bewohners für ein Gericht', function () {
    $g = Gericht::create(['datum' => '2026-06-08', 'mahlzeit' => Mahlzeit::Mittag, 'bezeichnung' => 'Gemüseeintopf', 'allergene' => [LmivAllergen::Sellerie->value]]);
    Menuewahl::create(['resident_id' => $this->maria->id, 'gericht_id' => $g->id]);

    expect($g->menuewahlen)->toHaveCount(1)
        ->and(Menuewahl::first()->resident->id)->toBe($this->maria->id);
});

it('ist mandantengetrennt', function () {
    Essenswunsch::create(['resident_id' => $this->maria->id, 'art' => EssenswunschArt::Vorliebe, 'text' => 'gern süß']);
    $fremd = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($fremd);

    expect(Essenswunsch::count())->toBe(0);
});
