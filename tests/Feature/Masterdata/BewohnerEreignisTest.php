<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Enums\Aufgabenkreis;
use App\Domains\Masterdata\Enums\EreignisKategorie;
use App\Domains\Masterdata\Enums\VertretungTyp;
use App\Domains\Masterdata\Models\BewohnerEreignis;
use App\Domains\Masterdata\Models\Custodian;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->resident = Resident::create(['name' => 'Test B', 'geburtsdatum' => '1940-01-01',
        'geschlecht' => 'm', 'aufnahme_am' => '2020-01-01']);
    Custodian::create(['resident_id' => $this->resident->id, 'typ' => VertretungTyp::GesetzlicherBetreuer->value,
        'name' => 'Gesundheits-Betreuer', 'aufgabenkreise' => [Aufgabenkreis::Gesundheitssorge->value]]);
    Custodian::create(['resident_id' => $this->resident->id, 'typ' => VertretungTyp::Bevollmaechtigter->value,
        'name' => 'Vermögens-Bevollmächtigter', 'aufgabenkreise' => [Aufgabenkreis::Vermoegenssorge->value]]);
});

function macheEreignis(array $attr = []): BewohnerEreignis
{
    return BewohnerEreignis::create(array_merge([
        'resident_id' => test()->resident->id,
        'kategorie' => EreignisKategorie::MdBegutachtung->value,
        'titel' => 'Ereignis',
        'datum' => now()->toDateString(),
        'status' => 'offen',
    ], $attr));
}

it('liefert nur die nach Aufgabenkreis berechtigten Empfänger', function () {
    $md = macheEreignis(['kategorie' => EreignisKategorie::MdBegutachtung->value]);
    $md->load('resident.custodians');
    expect($md->empfaenger())->toHaveCount(1)
        ->and($md->empfaenger()->first()->name)->toBe('Gesundheits-Betreuer');

    $tod = macheEreignis(['kategorie' => EreignisKategorie::Sterbefall->value]);
    $tod->load('resident.custodians');
    expect($tod->empfaenger())->toHaveCount(2);
});

it('ampelt nach Status und Fälligkeit', function () {
    expect(macheEreignis(['status' => 'offen', 'datum' => now()->subDay()->toDateString()])->ampel())->toBe('red');
    expect(macheEreignis(['status' => 'offen', 'datum' => now()->addDays(5)->toDateString()])->ampel())->toBe('amber');
    expect(macheEreignis(['status' => 'erledigt', 'datum' => now()->subDay()->toDateString()])->ampel())->toBe('green');
});
