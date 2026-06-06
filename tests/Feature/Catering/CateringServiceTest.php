<?php

use App\Domains\Catering\Enums\LmivAllergen;
use App\Domains\Catering\Enums\Mahlzeit;
use App\Domains\Catering\Models\Gericht;
use App\Domains\Catering\Services\CateringService;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->service = app(CateringService::class);
});

function bewohner(string $name): Resident
{
    return Resident::create([
        'name' => $name, 'geburtsdatum' => '1940-01-01', 'geschlecht' => 'w',
        'aufnahme_am' => '2024-01-01', 'status' => 'aktiv',
    ]);
}

it('sammelt nur Bewohner mit küchenrelevanter Diät (Allergie oder Kostform)', function () {
    $mitAllergie = bewohner('Erika Nuss');
    $mitAllergie->allergies()->create(['substanz' => 'Haselnüsse', 'typ' => 'allergie', 'kategorie' => 'nahrung', 'erfasst_am' => '2025-01-01']);
    $mitKostform = bewohner('Karl Diabetes');
    $mitKostform->statusObservations()->create(['typ' => 'kostform', 'wert_code' => '160670007', 'erfasst_am' => '2025-01-01']);
    $ohne = bewohner('Ohne Diät');
    $ohne->allergies()->create(['substanz' => 'Penicillin', 'typ' => 'allergie', 'kategorie' => 'medikament', 'erfasst_am' => '2025-01-01']);

    $diaet = $this->service->diaetBewohner();

    expect($diaet->pluck('name'))->toContain('Erika Nuss', 'Karl Diabetes')
        ->not->toContain('Ohne Diät');
});

it('matcht ein Gericht-Allergen unscharf gegen den Allergie-Freitext', function () {
    $erika = bewohner('Erika Nuss');
    $erika->allergies()->create(['substanz' => 'Haselnüsse + Walnuss', 'typ' => 'allergie', 'kategorie' => 'nahrung', 'erfasst_am' => '2025-01-01']);
    $gericht = Gericht::create([
        'datum' => '2026-06-08', 'mahlzeit' => Mahlzeit::Mittag, 'bezeichnung' => 'Nussecken',
        'allergene' => [LmivAllergen::Schalenfruechte->value, LmivAllergen::Gluten->value],
    ]);

    $betroffene = $this->service->betroffene($gericht, $this->service->diaetBewohner());

    expect($betroffene)->toHaveCount(1)
        ->and($betroffene[0]['resident']->name)->toBe('Erika Nuss')
        ->and($betroffene[0]['allergen'])->toBe('Schalenfrüchte (Nüsse)');
});

it('liefert keine Betroffenen, wenn das Gericht keine passenden Allergene hat', function () {
    $erika = bewohner('Erika Nuss');
    $erika->allergies()->create(['substanz' => 'Haselnüsse', 'typ' => 'allergie', 'kategorie' => 'nahrung', 'erfasst_am' => '2025-01-01']);
    $gericht = Gericht::create(['datum' => '2026-06-08', 'mahlzeit' => Mahlzeit::Mittag, 'bezeichnung' => 'Reis', 'allergene' => [LmivAllergen::Fisch->value]]);

    expect($this->service->betroffene($gericht, $this->service->diaetBewohner()))->toBe([]);
});
