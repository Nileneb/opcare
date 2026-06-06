<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Enums\Aufgabenkreis;
use App\Domains\Masterdata\Enums\EreignisKategorie;
use App\Domains\Masterdata\Enums\VertretungTyp;
use App\Domains\Masterdata\Models\Custodian;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->resident = Resident::create(['name' => 'Test B', 'geburtsdatum' => '1940-01-01',
        'geschlecht' => 'm', 'aufnahme_am' => '2020-01-01']);
});

function macheVertretung(array $attr = []): Custodian
{
    return Custodian::create(array_merge([
        'resident_id' => test()->resident->id,
        'typ' => VertretungTyp::GesetzlicherBetreuer->value,
        'name' => 'RA Vormund',
        'aufgabenkreise' => [Aufgabenkreis::Gesundheitssorge->value],
    ], $attr));
}

it('prüft Aufgabenkreise und liefert Enum-Liste', function () {
    $v = macheVertretung(['aufgabenkreise' => [Aufgabenkreis::Gesundheitssorge->value, Aufgabenkreis::Vermoegenssorge->value]]);
    expect($v->hatAufgabenkreis(Aufgabenkreis::Gesundheitssorge))->toBeTrue();
    expect($v->hatAufgabenkreis(Aufgabenkreis::Postangelegenheiten))->toBeFalse();
    expect($v->aufgabenkreiseEnums())->toHaveCount(2)
        ->and($v->aufgabenkreiseEnums()[0])->toBe(Aufgabenkreis::Gesundheitssorge);
});

it('bewertet die Aktivität anhand gueltig_bis', function () {
    expect(macheVertretung(['gueltig_bis' => null])->aktiv())->toBeTrue();
    expect(macheVertretung(['gueltig_bis' => now()->addYear()->toDateString()])->aktiv())->toBeTrue();
    expect(macheVertretung(['gueltig_bis' => now()->subDay()->toDateString()])->aktiv())->toBeFalse();
});

it('ampelt die Berichtspflicht (§ 1863)', function () {
    expect(macheVertretung(['bericht_intervall_monate' => null])->berichtAmpel())->toBe('gray');
    expect(macheVertretung(['bericht_intervall_monate' => 12, 'letzter_bericht_am' => now()->subMonths(13)->toDateString()])->berichtAmpel())->toBe('red');
    expect(macheVertretung(['bericht_intervall_monate' => 12, 'letzter_bericht_am' => now()->subMonths(1)->toDateString()])->berichtAmpel())->toBe('green');
    expect(macheVertretung(['bericht_intervall_monate' => 12, 'letzter_bericht_am' => now()->subMonths(12)->addDays(15)->toDateString()])->berichtAmpel())->toBe('amber');
});

it('gatet Ereignis-Beteiligung nach Aufgabenkreis', function () {
    $gesundheit = macheVertretung(['aufgabenkreise' => [Aufgabenkreis::Gesundheitssorge->value]]);
    $vermoegen = macheVertretung(['aufgabenkreise' => [Aufgabenkreis::Vermoegenssorge->value]]);

    expect($gesundheit->darfEreignis(EreignisKategorie::MdBegutachtung))->toBeTrue();
    expect($vermoegen->darfEreignis(EreignisKategorie::MdBegutachtung))->toBeFalse();
    // Sterbefall: keine Pflicht-Kreise → jede aktive Vertretung ist zu informieren
    expect($vermoegen->darfEreignis(EreignisKategorie::Sterbefall))->toBeTrue();
});
