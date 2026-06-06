<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\SocialCare\Enums\BetreuungsArt;
use App\Domains\SocialCare\Enums\BetreuungsTyp;
use App\Domains\SocialCare\Models\Betreuungsangebot;
use App\Domains\SocialCare\Models\BetreuungsTeilnahme;
use App\Domains\SocialCare\Services\SocialCareService;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->maria = Resident::create(['name' => 'Maria', 'geburtsdatum' => '1940-01-01', 'geschlecht' => 'w', 'aufnahme_am' => '2024-01-01', 'status' => 'aktiv']);
    $this->kurt = Resident::create(['name' => 'Kurt', 'geburtsdatum' => '1938-01-01', 'geschlecht' => 'm', 'aufnahme_am' => '2024-01-01', 'status' => 'aktiv']);
});

it('legt ein Betreuungsangebot mit Teilnahmen an', function () {
    $angebot = Betreuungsangebot::create([
        'datum' => '2026-06-08', 'beginn' => '10:00', 'dauer_minuten' => 45,
        'art' => BetreuungsArt::Gedaechtnistraining, 'typ' => BetreuungsTyp::Gruppe, 'titel' => 'Gedächtnistraining',
    ]);
    $angebot->teilnahmen()->create(['resident_id' => $this->maria->id]);

    expect($angebot->art)->toBe(BetreuungsArt::Gedaechtnistraining)
        ->and($angebot->teilnahmen)->toHaveCount(1)
        ->and(BetreuungsTeilnahme::first()->teilgenommen)->toBeTrue();
});

it('bilanziert die § 43b-Betreuung je Bewohner (Einheiten + Minuten)', function () {
    $a1 = Betreuungsangebot::create(['datum' => '2026-06-08', 'dauer_minuten' => 45, 'art' => BetreuungsArt::Musik, 'typ' => BetreuungsTyp::Gruppe, 'titel' => 'Singkreis']);
    $a2 = Betreuungsangebot::create(['datum' => '2026-06-10', 'dauer_minuten' => 30, 'art' => BetreuungsArt::Bewegung, 'typ' => BetreuungsTyp::Gruppe, 'titel' => 'Sitzgymnastik']);
    $ausserhalb = Betreuungsangebot::create(['datum' => '2026-05-01', 'dauer_minuten' => 60, 'art' => BetreuungsArt::Fest, 'typ' => BetreuungsTyp::Gruppe, 'titel' => 'Maifest']);
    $a1->teilnahmen()->create(['resident_id' => $this->maria->id]);
    $a2->teilnahmen()->create(['resident_id' => $this->maria->id]);
    $a1->teilnahmen()->create(['resident_id' => $this->kurt->id]);
    $ausserhalb->teilnahmen()->create(['resident_id' => $this->maria->id]);

    $bilanz = app(SocialCareService::class)->bilanz('2026-06-01', '2026-06-30');

    expect($bilanz[$this->maria->id]['einheiten'])->toBe(2)
        ->and($bilanz[$this->maria->id]['minuten'])->toBe(75)
        ->and($bilanz[$this->kurt->id]['einheiten'])->toBe(1);
});

it('ist mandantengetrennt', function () {
    Betreuungsangebot::create(['datum' => '2026-06-08', 'art' => BetreuungsArt::Sonstiges, 'typ' => BetreuungsTyp::Gruppe, 'titel' => 'X']);
    $fremd = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($fremd);

    expect(Betreuungsangebot::count())->toBe(0);
});
