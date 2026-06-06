<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Quality\Enums\BeschwerdeKategorie;
use App\Domains\Quality\Enums\BeschwerdeStatus;
use App\Domains\Quality\Models\Beschwerde;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
});

function neueBeschwerde(array $attr = []): Beschwerde
{
    return Beschwerde::create(array_merge([
        'tenant_id' => app(CurrentTenant::class)->id(),
        'titel' => 'Test', 'beschreibung' => 'x', 'kategorie' => BeschwerdeKategorie::Beschwerde,
        'bereich' => 'kueche', 'quelle' => 'bewohner', 'melder_sichtbarkeit' => 'namentlich',
        'eingang_am' => today()->toDateString(), 'status' => BeschwerdeStatus::Eingegangen,
    ], $attr));
}

it('zeigt einen Gewaltvorfall ohne Sofortmaßnahme als rot', function () {
    $b = neueBeschwerde(['kategorie' => BeschwerdeKategorie::Gewaltvorfall, 'schweregrad' => 'hoch']);
    expect($b->gewaltOhneSofortmassnahme())->toBeTrue();
    expect($b->ampel())->toBe('red');
});

it('zeigt einen Gewaltvorfall mit Sofortmaßnahme als amber bis erledigt', function () {
    $b = neueBeschwerde(['kategorie' => BeschwerdeKategorie::Gewaltvorfall, 'sofortmassnahme' => 'Trennung + Meldung']);
    expect($b->gewaltOhneSofortmassnahme())->toBeFalse();
    expect($b->ampel())->toBe('amber');
});

it('zeigt eine überfällige Frist als rot', function () {
    $b = neueBeschwerde(['frist' => today()->subDay()->toDateString()]);
    expect($b->ueberfaellig())->toBeTrue();
    expect($b->ampel())->toBe('red');
});

it('zeigt erledigte Beschwerden als grau', function () {
    $b = neueBeschwerde(['status' => BeschwerdeStatus::Erledigt, 'erledigt_am' => today()->toDateString()]);
    expect($b->offen())->toBeFalse();
    expect($b->ampel())->toBe('gray');
});

it('verbirgt die Melder-Identität bei Anonymität', function () {
    $b = neueBeschwerde(['melder_sichtbarkeit' => 'anonym', 'melder_name' => null]);
    expect($b->anonym())->toBeTrue();
    expect($b->melderAnzeige())->toBe('anonym');
});

it('zeigt den Melder-Namen bei namentlicher Meldung', function () {
    $b = neueBeschwerde(['melder_name' => 'Frau Meier']);
    expect($b->melderAnzeige())->toBe('Frau Meier');
});
