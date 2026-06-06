<?php

use App\Domains\Facility\Enums\AssetKategorie;
use App\Domains\Facility\Enums\MeldungStatus;
use App\Domains\Facility\Models\FacilityAsset;
use App\Domains\Facility\Models\FacilityMeldung;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('berechnet die nächste Prüfung und markiert Überfälligkeit (DIN 31051)', function () {
    $ueberfaellig = FacilityAsset::create([
        'bezeichnung' => 'Pflegebett 12', 'kategorie' => AssetKategorie::Medizinprodukt,
        'pruefintervall_monate' => 12, 'letzte_pruefung' => now()->subMonths(14)->toDateString(),
    ]);
    $aktuell = FacilityAsset::create([
        'bezeichnung' => 'Aufzug Haus 1', 'kategorie' => AssetKategorie::Aufzug,
        'pruefintervall_monate' => 12, 'letzte_pruefung' => now()->subMonths(2)->toDateString(),
    ]);
    $ohnePlan = FacilityAsset::create(['bezeichnung' => 'Sitzbank', 'kategorie' => AssetKategorie::Sonstiges]);

    expect($ueberfaellig->ueberfaellig())->toBeTrue()
        ->and($ueberfaellig->faelligBald())->toBeTrue()
        ->and($aktuell->ueberfaellig())->toBeFalse()
        ->and($aktuell->naechstePruefung()->format('Y-m'))->toBe(now()->addMonths(10)->format('Y-m'))
        ->and($ohnePlan->naechstePruefung())->toBeNull();
});

it('legt eine Mängelmeldung mit Melder an und kennt den offenen Status', function () {
    $meldung = FacilityMeldung::create([
        'titel' => 'Heizung Zimmer 7 defekt', 'beschreibung' => 'wird nicht warm',
        'standort' => 'Zimmer 7', 'gemeldet_von' => $this->user->id,
    ]);

    expect($meldung->status)->toBe(MeldungStatus::Offen)
        ->and($meldung->status->offen())->toBeTrue()
        ->and($meldung->melder->id)->toBe($this->user->id);
});

it('ist mandantengetrennt', function () {
    FacilityAsset::create(['bezeichnung' => 'X', 'kategorie' => AssetKategorie::Gebaeude]);
    $fremd = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($fremd);

    expect(FacilityAsset::count())->toBe(0);
});
