<?php

use App\Domains\Catering\Enums\GefahrenanalyseStatus;
use App\Domains\Catering\Enums\Gefahrenart;
use App\Domains\Catering\Enums\HaccpArt;
use App\Domains\Catering\Enums\Lenkungsart;
use App\Domains\Catering\Models\Gefahrenanalyse;
use App\Domains\Catering\Models\HaccpMesspunkt;
use App\Domains\Catering\Models\LebensmittelGefahr;
use App\Domains\Catering\Models\Lenkungsmassnahme;
use App\Domains\Catering\Services\GefahrenanalyseMonitor;
use App\Domains\Catering\Services\GefahrenanalyseVerifizieren;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'HACCP Heim', 'slug' => 'haccp-heim']);
    app(CurrentTenant::class)->set($this->tenant);
});

function analyse(array $overrides = []): Gefahrenanalyse
{
    return Gefahrenanalyse::create(array_merge([
        'prozessschritt' => 'Wareneingang Kühlware',
        'erstellt_am' => today()->toDateString(),
        'verifizierungsintervall_monate' => 12,
        'status' => GefahrenanalyseStatus::Entwurf,
    ], $overrides));
}

function gefahr(Gefahrenanalyse $a, array $overrides = []): LebensmittelGefahr
{
    return LebensmittelGefahr::create(array_merge([
        'gefahrenanalyse_id' => $a->id,
        'gefahrenart' => Gefahrenart::Biologisch,
        'beschreibung' => 'Salmonellen bei Kühlkettenbruch',
        'wahrscheinlichkeit' => 2,
        'schwere' => 2,
    ], $overrides));
}

// ---------------------------------------------------------------------------
// Enums
// ---------------------------------------------------------------------------

it('Gefahrenart hat Kürzel B/C/P/A', function () {
    expect(Gefahrenart::Biologisch->kuerzel())->toBe('B')
        ->and(Gefahrenart::Chemisch->kuerzel())->toBe('C')
        ->and(Gefahrenart::Physikalisch->kuerzel())->toBe('P')
        ->and(Gefahrenart::Allergen->kuerzel())->toBe('A');
});

it('Lenkungsart hat rang() CCP<Prozess<Basishygiene', function () {
    expect(Lenkungsart::Ccp->rang())->toBe(1)
        ->and(Lenkungsart::Prozesslenkung->rang())->toBe(2)
        ->and(Lenkungsart::Basishygiene->rang())->toBe(3);
});

it('GefahrenanalyseStatus hat label-Werte', function () {
    expect(GefahrenanalyseStatus::Entwurf->label())->toBe('Entwurf')
        ->and(GefahrenanalyseStatus::Freigegeben->label())->toBe('Freigegeben')
        ->and(GefahrenanalyseStatus::Ueberarbeitung->label())->toBe('Überarbeitung');
});

// ---------------------------------------------------------------------------
// Risiko-Matrix
// ---------------------------------------------------------------------------

it('risikowert ist Produkt aus W und S', function () {
    $g = gefahr(analyse(), ['wahrscheinlichkeit' => 2, 'schwere' => 3]);

    expect($g->risikowert())->toBe(6);
});

it('risikostufe gering (≤2), mittel (3–4), hoch (≥6) + signifikant', function () {
    $a = analyse();
    $gering = gefahr($a, ['wahrscheinlichkeit' => 1, 'schwere' => 1]);
    $mittel = gefahr($a, ['wahrscheinlichkeit' => 2, 'schwere' => 2]);
    $hoch = gefahr($a, ['wahrscheinlichkeit' => 3, 'schwere' => 3]);

    expect($gering->risikostufe())->toBe('gering')
        ->and($gering->signifikant())->toBeFalse()
        ->and($mittel->risikostufe())->toBe('mittel')
        ->and($mittel->signifikant())->toBeTrue()
        ->and($hoch->risikostufe())->toBe('hoch')
        ->and($hoch->signifikant())->toBeTrue();
});

it('hoechsteRisikostufe liefert max über alle Gefahren, null wenn leer', function () {
    $a = analyse();
    expect($a->fresh()->load('gefahren')->hoechsteRisikostufe())->toBeNull();

    gefahr($a, ['wahrscheinlichkeit' => 1, 'schwere' => 1]);
    gefahr($a, ['wahrscheinlichkeit' => 3, 'schwere' => 3]);

    expect($a->fresh()->load('gefahren')->hoechsteRisikostufe())->toBe('hoch');
});

// ---------------------------------------------------------------------------
// CCP-Verknüpfung + Lücken (SSOT)
// ---------------------------------------------------------------------------

it('CCP ohne verknüpften Messpunkt ist eine Lücke', function () {
    $g = gefahr(analyse(), ['ist_ccp' => true, 'haccp_messpunkt_id' => null]);

    expect($g->istCcpOhneUeberwachung())->toBeTrue();
});

it('CCP mit verknüpftem Messpunkt ist keine Lücke', function () {
    $mp = HaccpMesspunkt::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Kühlhaus 1',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);
    $g = gefahr(analyse(), ['ist_ccp' => true, 'haccp_messpunkt_id' => $mp->id]);

    expect($g->istCcpOhneUeberwachung())->toBeFalse()
        ->and($g->messpunkt->bezeichnung)->toBe('Kühlhaus 1');
});

it('signifikanteGefahrenOhneLenkung + ccpOhneUeberwachung als SSOT-Lücken', function () {
    $a = analyse();
    // signifikant (mittel) ohne Lenkung → Lücke
    gefahr($a, ['wahrscheinlichkeit' => 2, 'schwere' => 2]);
    // CCP ohne Messpunkt → Lücke
    gefahr($a, ['ist_ccp' => true, 'wahrscheinlichkeit' => 1, 'schwere' => 1]);

    $a = $a->fresh()->load('gefahren.lenkungsmassnahmen');

    expect($a->signifikanteGefahrenOhneLenkung())->toHaveCount(1)
        ->and($a->ccpOhneUeberwachung())->toHaveCount(1)
        ->and($a->hatLuecke())->toBeTrue();
});

it('signifikante Gefahr mit Lenkung ist keine Lücke mehr', function () {
    $a = analyse();
    $g = gefahr($a, ['wahrscheinlichkeit' => 2, 'schwere' => 2]);
    Lenkungsmassnahme::create([
        'lebensmittel_gefahr_id' => $g->id,
        'art' => Lenkungsart::Basishygiene,
        'beschreibung' => 'Kühlkette dokumentiert',
    ]);

    $a = $a->fresh()->load('gefahren.lenkungsmassnahmen');

    expect($a->signifikanteGefahrenOhneLenkung())->toHaveCount(0)
        ->and($a->hatLuecke())->toBeFalse();
});

// ---------------------------------------------------------------------------
// offene Lenkungsmaßnahmen (SSOT)
// ---------------------------------------------------------------------------

it('offeneLenkungsmassnahmen aggregiert über Gefahren und ignoriert umgesetzte', function () {
    $a = analyse();
    $g1 = gefahr($a);
    $g2 = gefahr($a, ['gefahrenart' => Gefahrenart::Physikalisch]);

    Lenkungsmassnahme::create(['lebensmittel_gefahr_id' => $g1->id, 'art' => Lenkungsart::Ccp, 'beschreibung' => 'A', 'umgesetzt_am' => null]);
    Lenkungsmassnahme::create(['lebensmittel_gefahr_id' => $g2->id, 'art' => Lenkungsart::Basishygiene, 'beschreibung' => 'B', 'umgesetzt_am' => null]);
    Lenkungsmassnahme::create(['lebensmittel_gefahr_id' => $g2->id, 'art' => Lenkungsart::Prozesslenkung, 'beschreibung' => 'C', 'umgesetzt_am' => today()->toDateString()]);

    $a = $a->fresh()->load('gefahren.lenkungsmassnahmen');

    expect($a->offeneLenkungsmassnahmen())->toHaveCount(2)
        ->and($a->hatOffeneLenkungsmassnahmen())->toBeTrue();
});

it('Lenkungsmassnahme istOffen und istVerifiziert', function () {
    $g = gefahr(analyse());
    $l = Lenkungsmassnahme::create(['lebensmittel_gefahr_id' => $g->id, 'art' => Lenkungsart::Ccp, 'beschreibung' => 'X']);

    expect($l->istOffen())->toBeTrue()->and($l->istVerifiziert())->toBeFalse();

    $l->update(['umgesetzt_am' => today()->toDateString(), 'verifiziert_am' => today()->toDateString()]);
    $l->refresh();

    expect($l->istOffen())->toBeFalse()->and($l->istVerifiziert())->toBeTrue();
});

// ---------------------------------------------------------------------------
// Frist-Ampel
// ---------------------------------------------------------------------------

it('Entwurf ist immer gruen (keine Verifizierungs-Frist)', function () {
    $a = analyse(['erstellt_am' => today()->subYears(2)->toDateString(), 'status' => GefahrenanalyseStatus::Entwurf]);

    expect($a->faelligkeitsStatus())->toBe('gruen')->and($a->istUeberfaellig())->toBeFalse();
});

it('freigegeben + letzte vor mehr als Intervall: rot', function () {
    $a = analyse([
        'status' => GefahrenanalyseStatus::Freigegeben,
        'letzte_verifizierung_am' => today()->subMonths(13)->toDateString(),
    ]);

    expect($a->istUeberfaellig())->toBeTrue()->and($a->faelligkeitsStatus())->toBe('rot');
});

it('freigegeben fällig in 30 Tagen: gelb', function () {
    $a = analyse([
        'status' => GefahrenanalyseStatus::Freigegeben,
        'letzte_verifizierung_am' => today()->subMonths(12)->addDays(30)->subDay()->toDateString(),
    ]);

    expect($a->faelligkeitsStatus())->toBe('gelb');
});

// ---------------------------------------------------------------------------
// Verifizieren (Max-Semantik)
// ---------------------------------------------------------------------------

it('GefahrenanalyseVerifizieren setzt Datum + Freigegeben', function () {
    $a = analyse(['erstellt_am' => today()->subYear()->toDateString()]);

    app(GefahrenanalyseVerifizieren::class)->handle($a, today()->toDateString());
    $a->refresh();

    expect($a->letzte_verifizierung_am->toDateString())->toBe(today()->toDateString())
        ->and($a->status)->toBe(GefahrenanalyseStatus::Freigegeben);
});

it('GefahrenanalyseVerifizieren Max-Semantik: älteres Datum setzt nicht zurück', function () {
    $a = analyse([
        'status' => GefahrenanalyseStatus::Freigegeben,
        'letzte_verifizierung_am' => today()->toDateString(),
    ]);

    app(GefahrenanalyseVerifizieren::class)->handle($a, today()->subDays(5)->toDateString());
    $a->refresh();

    expect($a->letzte_verifizierung_am->toDateString())->toBe(today()->toDateString());
});

// ---------------------------------------------------------------------------
// Monitor (tenant-scoped)
// ---------------------------------------------------------------------------

it('GefahrenanalyseMonitor::status ist tenant-scoped', function () {
    analyse(['prozessschritt' => 'Eigener Schritt']);

    $fremd = Tenant::create(['name' => 'Fremd', 'slug' => 'fremd-haccp']);
    app(CurrentTenant::class)->set($fremd);
    analyse(['prozessschritt' => 'Fremder Schritt']);
    app(CurrentTenant::class)->set($this->tenant);

    $result = app(GefahrenanalyseMonitor::class)->status();

    expect($result)->toHaveCount(1)
        ->and($result->first()->prozessschritt)->toBe('Eigener Schritt');
});

it('GefahrenanalyseMonitor zählt überfällige und Lücken', function () {
    // überfällig + Lücke (signifikant ohne Lenkung)
    $a = analyse([
        'status' => GefahrenanalyseStatus::Freigegeben,
        'letzte_verifizierung_am' => today()->subMonths(13)->toDateString(),
    ]);
    gefahr($a, ['wahrscheinlichkeit' => 3, 'schwere' => 3]);

    // frisch + ohne Lücke
    analyse([
        'prozessschritt' => 'Sauber',
        'status' => GefahrenanalyseStatus::Freigegeben,
        'letzte_verifizierung_am' => today()->toDateString(),
    ]);

    $monitor = app(GefahrenanalyseMonitor::class);

    expect($monitor->ueberfaelligeAnzahl())->toBe(1)
        ->and($monitor->mitLueckenAnzahl())->toBe(1);
});
