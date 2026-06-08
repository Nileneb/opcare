<?php

use App\Domains\Arbeitsschutz\Enums\GbuStatus;
use App\Domains\Arbeitsschutz\Enums\Gefaehrdungsfaktor;
use App\Domains\Arbeitsschutz\Enums\Massnahmentyp;
use App\Domains\Arbeitsschutz\Models\Gefaehrdung;
use App\Domains\Arbeitsschutz\Models\Gefaehrdungsbeurteilung;
use App\Domains\Arbeitsschutz\Models\Schutzmassnahme;
use App\Domains\Arbeitsschutz\Services\GbuFortschreiben;
use App\Domains\Arbeitsschutz\Services\GbuMonitor;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Arbeitsschutz Heim', 'slug' => 'arbeitsschutz-heim']);
    app(CurrentTenant::class)->set($this->tenant);
});

// ---------------------------------------------------------------------------
// Enums
// ---------------------------------------------------------------------------

it('Gefaehrdungsfaktor hat korrekte nummer()-Werte', function () {
    expect(Gefaehrdungsfaktor::Arbeitsstaette->nummer())->toBe(1)
        ->and(Gefaehrdungsfaktor::Einwirkungen->nummer())->toBe(2)
        ->and(Gefaehrdungsfaktor::Arbeitsmittel->nummer())->toBe(3)
        ->and(Gefaehrdungsfaktor::Verfahren->nummer())->toBe(4)
        ->and(Gefaehrdungsfaktor::Qualifikation->nummer())->toBe(5)
        ->and(Gefaehrdungsfaktor::PsychischeBelastung->nummer())->toBe(6);
});

it('Gefaehrdungsfaktor hat korrekte paragraph()-Werte', function () {
    expect(Gefaehrdungsfaktor::Arbeitsstaette->paragraph())->toBe('§ 5 Abs. 3 Nr. 1 ArbSchG')
        ->and(Gefaehrdungsfaktor::Einwirkungen->paragraph())->toBe('§ 5 Abs. 3 Nr. 2 ArbSchG')
        ->and(Gefaehrdungsfaktor::Arbeitsmittel->paragraph())->toBe('§ 5 Abs. 3 Nr. 3 ArbSchG')
        ->and(Gefaehrdungsfaktor::Verfahren->paragraph())->toBe('§ 5 Abs. 3 Nr. 4 ArbSchG')
        ->and(Gefaehrdungsfaktor::Qualifikation->paragraph())->toBe('§ 5 Abs. 3 Nr. 5 ArbSchG')
        ->and(Gefaehrdungsfaktor::PsychischeBelastung->paragraph())->toBe('§ 5 Abs. 3 Nr. 6 ArbSchG');
});

it('Massnahmentyp hat korrekte rang()-Werte (TOP-Hierarchie)', function () {
    expect(Massnahmentyp::Technisch->rang())->toBe(1)
        ->and(Massnahmentyp::Organisatorisch->rang())->toBe(2)
        ->and(Massnahmentyp::Personenbezogen->rang())->toBe(3);
});

it('GbuStatus hat korrekte label()-Werte', function () {
    expect(GbuStatus::Entwurf->label())->toBe('Entwurf')
        ->and(GbuStatus::Freigegeben->label())->toBe('Freigegeben')
        ->and(GbuStatus::Ueberarbeitung->label())->toBe('Überarbeitung');
});

// ---------------------------------------------------------------------------
// Risiko-Matrix
// ---------------------------------------------------------------------------

it('risikowert ist Produkt aus Wahrscheinlichkeit und Schwere', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Pflege',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $gefaehrdung = Gefaehrdung::create([
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::Einwirkungen,
        'beschreibung' => 'Testgefährdung',
        'wahrscheinlichkeit' => 2,
        'schwere' => 3,
    ]);

    expect($gefaehrdung->risikowert())->toBe(6);
});

it('risikostufe: w=1,s=1 → gering (Wert 1)', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Pflege',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $gefaehrdung = Gefaehrdung::create([
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::Qualifikation,
        'beschreibung' => 'Minimal',
        'wahrscheinlichkeit' => 1,
        'schwere' => 1,
    ]);

    expect($gefaehrdung->risikowert())->toBe(1)
        ->and($gefaehrdung->risikostufe())->toBe('gering');
});

it('risikostufe: w=2,s=2 → mittel (Wert 4)', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Küche',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $gefaehrdung = Gefaehrdung::create([
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::Arbeitsmittel,
        'beschreibung' => 'Mittelgefährdung',
        'wahrscheinlichkeit' => 2,
        'schwere' => 2,
    ]);

    expect($gefaehrdung->risikowert())->toBe(4)
        ->and($gefaehrdung->risikostufe())->toBe('mittel');
});

it('risikostufe: w=2,s=3 → hoch (Wert 6)', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Haustechnik',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $gefaehrdung = Gefaehrdung::create([
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::Verfahren,
        'beschreibung' => 'Hochgefährdung',
        'wahrscheinlichkeit' => 2,
        'schwere' => 3,
    ]);

    expect($gefaehrdung->risikowert())->toBe(6)
        ->and($gefaehrdung->risikostufe())->toBe('hoch');
});

it('risikostufe: w=3,s=3 → hoch (Wert 9)', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Außendienst',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $gefaehrdung = Gefaehrdung::create([
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::PsychischeBelastung,
        'beschreibung' => 'Maximalgefährdung',
        'wahrscheinlichkeit' => 3,
        'schwere' => 3,
    ]);

    expect($gefaehrdung->risikowert())->toBe(9)
        ->and($gefaehrdung->risikostufe())->toBe('hoch');
});

// ---------------------------------------------------------------------------
// Frist-Ampel
// ---------------------------------------------------------------------------

it('Entwurf-GBU ist immer gruen (keine Fortschreibungs-Frist)', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Pflege WB 1',
        'erstellt_am' => today()->subYears(2)->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    expect($gbu->faelligkeitsStatus())->toBe('gruen')
        ->and($gbu->istUeberfaellig())->toBeFalse();
});

it('freigegebene GBU mit letzte vor mehr als Intervall: rot', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Pflege WB 1',
        'erstellt_am' => today()->subYears(2)->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'letzte_ueberpruefung_am' => today()->subMonths(13)->toDateString(),
        'status' => GbuStatus::Freigegeben,
    ]);

    expect($gbu->istUeberfaellig())->toBeTrue()
        ->and($gbu->faelligkeitsStatus())->toBe('rot');
});

it('freigegebene GBU fällig in 30 Tagen: gelb', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Pflege WB 2',
        'erstellt_am' => today()->subYears(2)->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'letzte_ueberpruefung_am' => today()->subMonths(12)->addDays(30)->subDay()->toDateString(),
        'status' => GbuStatus::Freigegeben,
    ]);

    expect($gbu->faelligkeitsStatus())->toBe('gelb');
});

it('freigegebene GBU frisch überprüft: gruen', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Küche',
        'erstellt_am' => today()->subMonths(6)->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'letzte_ueberpruefung_am' => today()->toDateString(),
        'status' => GbuStatus::Freigegeben,
    ]);

    expect($gbu->faelligkeitsStatus())->toBe('gruen')
        ->and($gbu->istUeberfaellig())->toBeFalse();
});

it('letzte_ueberpruefung_am null: fällt auf erstellt_am zurück', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Haustechnik',
        'erstellt_am' => today()->subMonths(13)->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'letzte_ueberpruefung_am' => null,
        'status' => GbuStatus::Freigegeben,
    ]);

    // naechsteUeberpruefung = erstellt_am + 12 Monate = vor 1 Monat → rot
    expect($gbu->naechsteUeberpruefung()->lt(today()))->toBeTrue()
        ->and($gbu->faelligkeitsStatus())->toBe('rot');
});

it('Ueberarbeitung-Status ist immer gruen (wie Entwurf)', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Verwaltung',
        'erstellt_am' => today()->subYears(2)->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Ueberarbeitung,
    ]);

    expect($gbu->faelligkeitsStatus())->toBe('gruen');
});

// ---------------------------------------------------------------------------
// SSOT offeneMassnahmen
// ---------------------------------------------------------------------------

it('offeneMassnahmen liefert offene Massnahmen und hatOffeneMassnahmen ist true', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Pflege WB 1',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $gefaehrdung = Gefaehrdung::create([
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::Einwirkungen,
        'beschreibung' => 'Biologische Belastung',
        'wahrscheinlichkeit' => 2,
        'schwere' => 2,
    ]);

    Schutzmassnahme::create([
        'gefaehrdung_id' => $gefaehrdung->id,
        'typ' => Massnahmentyp::Technisch,
        'beschreibung' => 'Schutzausrüstung',
        'umgesetzt_am' => null,
    ]);

    expect($gbu->offeneMassnahmen())->toHaveCount(1)
        ->and($gbu->hatOffeneMassnahmen())->toBeTrue();
});

it('offeneMassnahmen aggregiert korrekt über mehrere Gefährdungen', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Pflege WB 2',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $gefaehrdung1 = Gefaehrdung::create([
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::Einwirkungen,
        'beschreibung' => 'Biologische Belastung',
        'wahrscheinlichkeit' => 2,
        'schwere' => 2,
    ]);

    $gefaehrdung2 = Gefaehrdung::create([
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::PsychischeBelastung,
        'beschreibung' => 'Schichtarbeit',
        'wahrscheinlichkeit' => 3,
        'schwere' => 2,
    ]);

    Schutzmassnahme::create([
        'gefaehrdung_id' => $gefaehrdung1->id,
        'typ' => Massnahmentyp::Technisch,
        'beschreibung' => 'Schutzausrüstung',
        'umgesetzt_am' => null,
    ]);

    Schutzmassnahme::create([
        'gefaehrdung_id' => $gefaehrdung2->id,
        'typ' => Massnahmentyp::Organisatorisch,
        'beschreibung' => 'Dienstplanoptimierung',
        'umgesetzt_am' => null,
    ]);

    // Eine davon schon umgesetzt
    Schutzmassnahme::create([
        'gefaehrdung_id' => $gefaehrdung2->id,
        'typ' => Massnahmentyp::Personenbezogen,
        'beschreibung' => 'Coaching',
        'umgesetzt_am' => today()->toDateString(),
    ]);

    expect($gbu->offeneMassnahmen())->toHaveCount(2)
        ->and($gbu->hatOffeneMassnahmen())->toBeTrue();
});

it('nach umgesetzt_am gesetzt: offeneMassnahmen leer, hatOffeneMassnahmen false', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Küche',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $gefaehrdung = Gefaehrdung::create([
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::Arbeitsmittel,
        'beschreibung' => 'Messer',
        'wahrscheinlichkeit' => 1,
        'schwere' => 2,
    ]);

    Schutzmassnahme::create([
        'gefaehrdung_id' => $gefaehrdung->id,
        'typ' => Massnahmentyp::Technisch,
        'beschreibung' => 'Schutzhandschuhe',
        'umgesetzt_am' => today()->toDateString(),
    ]);

    expect($gbu->offeneMassnahmen())->toHaveCount(0)
        ->and($gbu->hatOffeneMassnahmen())->toBeFalse();
});

// ---------------------------------------------------------------------------
// hoechsteRisikostufe
// ---------------------------------------------------------------------------

it('hoechsteRisikostufe liefert max über alle Gefährdungen', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Pflege',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    Gefaehrdung::create([
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::Qualifikation,
        'beschreibung' => 'Gering',
        'wahrscheinlichkeit' => 1,
        'schwere' => 1,
    ]);

    Gefaehrdung::create([
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::Einwirkungen,
        'beschreibung' => 'Hoch',
        'wahrscheinlichkeit' => 3,
        'schwere' => 3,
    ]);

    expect($gbu->hoechsteRisikostufe())->toBe('hoch');
});

it('hoechsteRisikostufe ist null wenn keine Gefährdungen', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Leer',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    expect($gbu->hoechsteRisikostufe())->toBeNull();
});

// ---------------------------------------------------------------------------
// Schutzmassnahme
// ---------------------------------------------------------------------------

it('Schutzmassnahme istOffen und istWirksamGeprueft', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Test',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $gefaehrdung = Gefaehrdung::create([
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::Arbeitsstaette,
        'beschreibung' => 'Rutschgefahr',
        'wahrscheinlichkeit' => 1,
        'schwere' => 2,
    ]);

    $massnahme = Schutzmassnahme::create([
        'gefaehrdung_id' => $gefaehrdung->id,
        'typ' => Massnahmentyp::Technisch,
        'beschreibung' => 'Rutschmatten',
        'umgesetzt_am' => null,
        'wirksam_geprueft_am' => null,
    ]);

    expect($massnahme->istOffen())->toBeTrue()
        ->and($massnahme->istWirksamGeprueft())->toBeFalse();

    $massnahme->update([
        'umgesetzt_am' => today()->toDateString(),
        'wirksam_geprueft_am' => today()->toDateString(),
    ]);
    $massnahme->refresh();

    expect($massnahme->istOffen())->toBeFalse()
        ->and($massnahme->istWirksamGeprueft())->toBeTrue();
});

// ---------------------------------------------------------------------------
// GbuFortschreiben
// ---------------------------------------------------------------------------

it('GbuFortschreiben setzt letzte_ueberpruefung_am und Status Freigegeben', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Pflege WB 1',
        'erstellt_am' => today()->subYear()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $service = app(GbuFortschreiben::class);
    $service->handle($gbu, today()->toDateString());

    $gbu->refresh();

    expect($gbu->letzte_ueberpruefung_am->toDateString())->toBe(today()->toDateString())
        ->and($gbu->status)->toBe(GbuStatus::Freigegeben);
});

it('GbuFortschreiben Max-Semantik: älteres Datum setzt letzte_ueberpruefung_am nicht zurück', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Küche',
        'erstellt_am' => today()->subYear()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'letzte_ueberpruefung_am' => today()->toDateString(),
        'status' => GbuStatus::Freigegeben,
    ]);

    $service = app(GbuFortschreiben::class);
    $service->handle($gbu, today()->subDays(5)->toDateString());

    $gbu->refresh();

    // Max-Semantik: älteres Datum darf die Frist nicht zurücksetzen
    expect($gbu->letzte_ueberpruefung_am->toDateString())->toBe(today()->toDateString());
});

it('GbuFortschreiben aktualisiert wenn neues Datum neuer ist', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Haustechnik',
        'erstellt_am' => today()->subYears(2)->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'letzte_ueberpruefung_am' => today()->subDays(10)->toDateString(),
        'status' => GbuStatus::Freigegeben,
    ]);

    $service = app(GbuFortschreiben::class);
    $service->handle($gbu, today()->toDateString());

    $gbu->refresh();

    expect($gbu->letzte_ueberpruefung_am->toDateString())->toBe(today()->toDateString());
});

// ---------------------------------------------------------------------------
// GbuMonitor
// ---------------------------------------------------------------------------

it('GbuMonitor::status ist tenant-scoped (fremder Tenant unsichtbar)', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremdes Heim', 'slug' => 'fremdes-heim']);

    Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Eigener Bereich',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    app(CurrentTenant::class)->set($fremderTenant);
    Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Fremder Bereich',
        'erstellt_am' => today()->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);
    app(CurrentTenant::class)->set($this->tenant);

    $monitor = app(GbuMonitor::class);
    $result = $monitor->status();

    expect($result)->toHaveCount(1)
        ->and($result->first()->arbeitsbereich)->toBe('Eigener Bereich');
});

it('GbuMonitor::ueberfaelligeAnzahl zählt nur überfällige freigegebene GBUs', function () {
    // Überfällig
    Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'WB 1',
        'erstellt_am' => today()->subYears(2)->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'letzte_ueberpruefung_am' => today()->subMonths(13)->toDateString(),
        'status' => GbuStatus::Freigegeben,
    ]);

    // Nicht überfällig (frisch)
    Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'WB 2',
        'erstellt_am' => today()->subMonths(6)->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'letzte_ueberpruefung_am' => today()->toDateString(),
        'status' => GbuStatus::Freigegeben,
    ]);

    // Entwurf — niemals überfällig
    Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'WB 3',
        'erstellt_am' => today()->subYears(3)->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'status' => GbuStatus::Entwurf,
    ]);

    $monitor = app(GbuMonitor::class);

    expect($monitor->ueberfaelligeAnzahl())->toBe(1);
});

it('GbuMonitor::status enthält faelligkeitsStatus und hatOffeneMassnahmen', function () {
    $gbu = Gefaehrdungsbeurteilung::create([
        'arbeitsbereich' => 'Pflege',
        'erstellt_am' => today()->subYears(2)->toDateString(),
        'ueberpruefungsintervall_monate' => 12,
        'letzte_ueberpruefung_am' => today()->subMonths(13)->toDateString(),
        'status' => GbuStatus::Freigegeben,
    ]);

    $gefaehrdung = Gefaehrdung::create([
        'gefaehrdungsbeurteilung_id' => $gbu->id,
        'faktor' => Gefaehrdungsfaktor::PsychischeBelastung,
        'beschreibung' => 'Schichtarbeit',
        'wahrscheinlichkeit' => 2,
        'schwere' => 2,
    ]);

    Schutzmassnahme::create([
        'gefaehrdung_id' => $gefaehrdung->id,
        'typ' => Massnahmentyp::Organisatorisch,
        'beschreibung' => 'Dienstplanoptimierung',
        'umgesetzt_am' => null,
    ]);

    $monitor = app(GbuMonitor::class);
    $result = $monitor->status();

    expect($result)->toHaveCount(1);

    $gbuResult = $result->first();
    expect($gbuResult->faelligkeitsStatus())->toBe('rot')
        ->and($gbuResult->hatOffeneMassnahmen())->toBeTrue();
});
