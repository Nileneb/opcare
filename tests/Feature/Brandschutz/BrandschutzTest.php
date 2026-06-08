<?php

use App\Domains\Brandschutz\Enums\BrandschutzordnungTeil;
use App\Domains\Brandschutz\Enums\MangelSchwere;
use App\Domains\Brandschutz\Models\Brandschutzbegehung;
use App\Domains\Brandschutz\Models\Brandschutzmangel;
use App\Domains\Brandschutz\Models\Brandschutzordnung;
use App\Domains\Brandschutz\Models\Raeumungsuebung;
use App\Domains\Brandschutz\Services\BrandschutzMonitor;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Brandschutz Heim', 'slug' => 'brandschutz-heim']);
    app(CurrentTenant::class)->set($this->tenant);
});

// ---------------------------------------------------------------------------
// BrandschutzordnungTeil Enum
// ---------------------------------------------------------------------------

it('BrandschutzordnungTeil hat korrekte zielgruppe()-Werte', function () {
    expect(BrandschutzordnungTeil::A->zielgruppe())->toBe('Alle (Aushang)')
        ->and(BrandschutzordnungTeil::B->zielgruppe())->toBe('Beschäftigte ohne bes. Brandschutzaufgaben')
        ->and(BrandschutzordnungTeil::C->zielgruppe())->toBe('Personen mit bes. Brandschutzaufgaben');
});

it('BrandschutzordnungTeil hat korrekte label()-Werte', function () {
    expect(BrandschutzordnungTeil::A->label())->toBe('Teil A — Aushang')
        ->and(BrandschutzordnungTeil::B->label())->toBe('Teil B — Beschäftigte')
        ->and(BrandschutzordnungTeil::C->label())->toBe('Teil C — Brandschutzbeauftragte');
});

// ---------------------------------------------------------------------------
// MangelSchwere Enum
// ---------------------------------------------------------------------------

it('MangelSchwere hat korrekte ampel()-Werte', function () {
    expect(MangelSchwere::Gering->ampel())->toBe('green')
        ->and(MangelSchwere::Wesentlich->ampel())->toBe('amber')
        ->and(MangelSchwere::Kritisch->ampel())->toBe('red');
});

it('MangelSchwere hat korrekte rang()-Werte', function () {
    expect(MangelSchwere::Gering->rang())->toBe(1)
        ->and(MangelSchwere::Wesentlich->rang())->toBe(2)
        ->and(MangelSchwere::Kritisch->rang())->toBe(3);
});

// ---------------------------------------------------------------------------
// Brandschutzordnung — status() + ampel()
// ---------------------------------------------------------------------------

it('Brandschutzordnung status entwurf wenn nie freigegeben', function () {
    $ordnung = Brandschutzordnung::create([
        'titel' => 'BSO Teil A',
        'teil' => BrandschutzordnungTeil::A,
        'version' => '1.0',
        'aktiv' => true,
    ]);

    expect($ordnung->status())->toBe('entwurf')
        ->and($ordnung->ampel())->toBe('red');
});

it('Brandschutzordnung status ueberfaellig wenn freigegeben vor mehr als Intervall', function () {
    $ordnung = Brandschutzordnung::create([
        'titel' => 'BSO Teil B',
        'teil' => BrandschutzordnungTeil::B,
        'version' => '1.0',
        'aktiv' => true,
        'freigegeben_am' => today()->subMonths(25)->toDateString(),
        'revision_intervall_monate' => 24,
    ]);

    expect($ordnung->status())->toBe('ueberfaellig')
        ->and($ordnung->ampel())->toBe('red');
});

it('Brandschutzordnung status faellig wenn Revision in ≤30 Tagen', function () {
    $ordnung = Brandschutzordnung::create([
        'titel' => 'BSO Teil C',
        'teil' => BrandschutzordnungTeil::C,
        'version' => '2.0',
        'aktiv' => true,
        'freigegeben_am' => today()->subMonths(24)->addDays(20)->toDateString(),
        'revision_intervall_monate' => 24,
    ]);

    expect($ordnung->status())->toBe('faellig')
        ->and($ordnung->ampel())->toBe('amber');
});

it('Brandschutzordnung status aktuell wenn Revision noch weit', function () {
    $ordnung = Brandschutzordnung::create([
        'titel' => 'BSO Teil A aktuell',
        'teil' => BrandschutzordnungTeil::A,
        'version' => '3.0',
        'aktiv' => true,
        'freigegeben_am' => today()->subMonths(6)->toDateString(),
        'revision_intervall_monate' => 24,
    ]);

    expect($ordnung->status())->toBe('aktuell')
        ->and($ordnung->ampel())->toBe('green');
});

// ---------------------------------------------------------------------------
// Brandschutzbegehung — Frist-Ampel
// ---------------------------------------------------------------------------

it('Begehung frisch begangen: grün', function () {
    $begehung = Brandschutzbegehung::create([
        'bereich' => 'Wohnbereich 1',
        'begangen_am' => today()->subMonths(2)->toDateString(),
        'intervall_monate' => 12,
    ]);

    expect($begehung->faelligkeitsStatus())->toBe('gruen')
        ->and($begehung->istUeberfaellig())->toBeFalse();
});

it('Begehung älter als Intervall: rot', function () {
    $begehung = Brandschutzbegehung::create([
        'bereich' => 'Keller',
        'begangen_am' => today()->subMonths(13)->toDateString(),
        'intervall_monate' => 12,
    ]);

    expect($begehung->faelligkeitsStatus())->toBe('rot')
        ->and($begehung->istUeberfaellig())->toBeTrue();
});

it('Begehung Fälligkeit in ≤30 Tagen: gelb', function () {
    $begehung = Brandschutzbegehung::create([
        'bereich' => 'Küche',
        'begangen_am' => today()->subMonths(12)->addDays(20)->toDateString(),
        'intervall_monate' => 12,
    ]);

    expect($begehung->faelligkeitsStatus())->toBe('gelb')
        ->and($begehung->istUeberfaellig())->toBeFalse();
});

// ---------------------------------------------------------------------------
// SSOT offeneMaengel + hoechsteOffeneSchwere
// ---------------------------------------------------------------------------

it('offeneMaengel enthält nur nicht-behobene Mängel', function () {
    $begehung = Brandschutzbegehung::create([
        'bereich' => 'Treppenhaus',
        'begangen_am' => today()->subMonths(2)->toDateString(),
        'intervall_monate' => 12,
    ]);

    Brandschutzmangel::create([
        'brandschutzbegehung_id' => $begehung->id,
        'beschreibung' => 'Feuerlöscher fehlt',
        'schwere' => MangelSchwere::Kritisch,
    ]);
    Brandschutzmangel::create([
        'brandschutzbegehung_id' => $begehung->id,
        'beschreibung' => 'Schild verblasst',
        'schwere' => MangelSchwere::Gering,
        'behoben_am' => today()->toDateString(),
    ]);

    $begehung->load('maengel');

    expect($begehung->offeneMaengel())->toHaveCount(1)
        ->and($begehung->hatOffeneMaengel())->toBeTrue()
        ->and($begehung->offeneMaengel()->first()->beschreibung)->toBe('Feuerlöscher fehlt');
});

it('offeneMaengel leer nach Behebung aller Mängel', function () {
    $begehung = Brandschutzbegehung::create([
        'bereich' => 'Eingang',
        'begangen_am' => today()->subMonths(1)->toDateString(),
        'intervall_monate' => 12,
    ]);

    Brandschutzmangel::create([
        'brandschutzbegehung_id' => $begehung->id,
        'beschreibung' => 'Tür klemmt',
        'schwere' => MangelSchwere::Wesentlich,
        'behoben_am' => today()->toDateString(),
    ]);

    $begehung->load('maengel');

    expect($begehung->offeneMaengel())->toHaveCount(0)
        ->and($begehung->hatOffeneMaengel())->toBeFalse();
});

it('hoechsteOffeneSchwere liefert Kritisch wenn Kritisch und Gering offen', function () {
    $begehung = Brandschutzbegehung::create([
        'bereich' => 'Lager',
        'begangen_am' => today()->subMonths(1)->toDateString(),
        'intervall_monate' => 12,
    ]);

    Brandschutzmangel::create([
        'brandschutzbegehung_id' => $begehung->id,
        'beschreibung' => 'Kritischer Mangel',
        'schwere' => MangelSchwere::Kritisch,
    ]);
    Brandschutzmangel::create([
        'brandschutzbegehung_id' => $begehung->id,
        'beschreibung' => 'Geringer Mangel',
        'schwere' => MangelSchwere::Gering,
    ]);

    $begehung->load('maengel');

    expect($begehung->hoechsteOffeneSchwere())->toBe(MangelSchwere::Kritisch);
});

it('hoechsteOffeneSchwere null wenn keine offenen Mängel', function () {
    $begehung = Brandschutzbegehung::create([
        'bereich' => 'Garten',
        'begangen_am' => today()->subMonths(1)->toDateString(),
        'intervall_monate' => 12,
    ]);

    $begehung->load('maengel');

    expect($begehung->hoechsteOffeneSchwere())->toBeNull();
});

// ---------------------------------------------------------------------------
// Raeumungsuebung — Frist-Ampel
// ---------------------------------------------------------------------------

it('Raeumungsuebung frisch: grün', function () {
    $uebung = Raeumungsuebung::create([
        'durchgefuehrt_am' => today()->subMonths(3)->toDateString(),
        'intervall_monate' => 12,
    ]);

    expect($uebung->faelligkeitsStatus())->toBe('gruen')
        ->and($uebung->istUeberfaellig())->toBeFalse();
});

it('Raeumungsuebung älter als Intervall: rot', function () {
    $uebung = Raeumungsuebung::create([
        'durchgefuehrt_am' => today()->subMonths(13)->toDateString(),
        'intervall_monate' => 12,
    ]);

    expect($uebung->faelligkeitsStatus())->toBe('rot')
        ->and($uebung->istUeberfaellig())->toBeTrue();
});

it('Raeumungsuebung Fälligkeit in ≤30 Tagen: gelb', function () {
    $uebung = Raeumungsuebung::create([
        'durchgefuehrt_am' => today()->subMonths(12)->addDays(15)->toDateString(),
        'intervall_monate' => 12,
    ]);

    expect($uebung->faelligkeitsStatus())->toBe('gelb')
        ->and($uebung->istUeberfaellig())->toBeFalse();
});

// ---------------------------------------------------------------------------
// BrandschutzMonitor — tenant-scoped
// ---------------------------------------------------------------------------

it('BrandschutzMonitor ist tenant-scoped (fremder Tenant unsichtbar)', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremdes Heim', 'slug' => 'fremdes-heim']);

    Brandschutzbegehung::create([
        'bereich' => 'Eigener Bereich',
        'begangen_am' => today()->subMonths(1)->toDateString(),
        'intervall_monate' => 12,
    ]);

    app(CurrentTenant::class)->set($fremderTenant);
    Brandschutzbegehung::create([
        'bereich' => 'Fremder Bereich',
        'begangen_am' => today()->subMonths(1)->toDateString(),
        'intervall_monate' => 12,
    ]);
    app(CurrentTenant::class)->set($this->tenant);

    $monitor = app(BrandschutzMonitor::class);
    $result = $monitor->aktuelleBegehungen();

    expect($result)->toHaveCount(1)
        ->and($result->first()->bereich)->toBe('Eigener Bereich');
});

it('aktuelleBegehungen liefert jüngste Begehung je Bereich', function () {
    Brandschutzbegehung::create([
        'bereich' => 'Wohnbereich 1',
        'begangen_am' => today()->subMonths(6)->toDateString(),
        'intervall_monate' => 12,
    ]);
    Brandschutzbegehung::create([
        'bereich' => 'Wohnbereich 1',
        'begangen_am' => today()->subMonths(2)->toDateString(),
        'intervall_monate' => 12,
    ]);
    Brandschutzbegehung::create([
        'bereich' => 'Keller',
        'begangen_am' => today()->subMonths(3)->toDateString(),
        'intervall_monate' => 12,
    ]);

    $monitor = app(BrandschutzMonitor::class);
    $result = $monitor->aktuelleBegehungen();

    expect($result)->toHaveCount(2);

    $byBereich = $result->keyBy('bereich');
    expect($byBereich['Wohnbereich 1']->begangen_am->toDateString())
        ->toBe(today()->subMonths(2)->toDateString());
});

it('MAX-SEMANTIK: nachgetragene ältere Begehung im selben Bereich ändert jüngste nicht', function () {
    $juengste = Brandschutzbegehung::create([
        'bereich' => 'Technik',
        'begangen_am' => today()->subMonths(2)->toDateString(),
        'intervall_monate' => 12,
    ]);

    // Nachtrag: älter als die bereits vorhandene Begehung
    Brandschutzbegehung::create([
        'bereich' => 'Technik',
        'begangen_am' => today()->subMonths(8)->toDateString(),
        'intervall_monate' => 12,
    ]);

    $monitor = app(BrandschutzMonitor::class);
    $result = $monitor->aktuelleBegehungen();

    expect($result)->toHaveCount(1)
        ->and($result->first()->id)->toBe($juengste->id);
});

it('offeneMaengelAnzahl zählt nur tenant-eigene offene Mängel', function () {
    $begehung = Brandschutzbegehung::create([
        'bereich' => 'Flur',
        'begangen_am' => today()->subMonths(1)->toDateString(),
        'intervall_monate' => 12,
    ]);

    Brandschutzmangel::create([
        'brandschutzbegehung_id' => $begehung->id,
        'beschreibung' => 'Offen',
        'schwere' => MangelSchwere::Wesentlich,
    ]);
    Brandschutzmangel::create([
        'brandschutzbegehung_id' => $begehung->id,
        'beschreibung' => 'Behoben',
        'schwere' => MangelSchwere::Gering,
        'behoben_am' => today()->toDateString(),
    ]);

    // Fremder Tenant — darf nicht gezählt werden
    $fremderTenant = Tenant::create(['name' => 'Fremdes Heim 2', 'slug' => 'fremdes-heim-2']);
    app(CurrentTenant::class)->set($fremderTenant);
    $fremdeBegehung = Brandschutzbegehung::create([
        'bereich' => 'Fremder Flur',
        'begangen_am' => today()->subMonths(1)->toDateString(),
        'intervall_monate' => 12,
    ]);
    Brandschutzmangel::create([
        'brandschutzbegehung_id' => $fremdeBegehung->id,
        'beschreibung' => 'Fremder Mangel',
        'schwere' => MangelSchwere::Kritisch,
    ]);
    app(CurrentTenant::class)->set($this->tenant);

    $monitor = app(BrandschutzMonitor::class);
    expect($monitor->offeneMaengelAnzahl())->toBe(1);
});

it('ueberfaelligeAnzahl addiert überfällige Ordnungen + Begehungen + Übung', function () {
    // Überfällige Ordnung
    Brandschutzordnung::create([
        'titel' => 'Überfällig',
        'teil' => BrandschutzordnungTeil::A,
        'version' => '1.0',
        'aktiv' => true,
        'freigegeben_am' => today()->subMonths(25)->toDateString(),
        'revision_intervall_monate' => 24,
    ]);
    // Entwurf — zählt nicht als ueberfaellig
    Brandschutzordnung::create([
        'titel' => 'Entwurf',
        'teil' => BrandschutzordnungTeil::B,
        'version' => '1.0',
        'aktiv' => true,
    ]);

    // Überfällige Begehung (älteste im Bereich — wird durch jüngere überschrieben)
    Brandschutzbegehung::create([
        'bereich' => 'Wohnbereich 2',
        'begangen_am' => today()->subMonths(14)->toDateString(),
        'intervall_monate' => 12,
    ]);
    // Jüngere, noch aktuelle Begehung für denselben Bereich
    Brandschutzbegehung::create([
        'bereich' => 'Wohnbereich 2',
        'begangen_am' => today()->subMonths(2)->toDateString(),
        'intervall_monate' => 12,
    ]);
    // Überfällige Begehung anderer Bereich
    Brandschutzbegehung::create([
        'bereich' => 'Technikraum',
        'begangen_am' => today()->subMonths(13)->toDateString(),
        'intervall_monate' => 12,
    ]);

    // Aktuelle Übung
    Raeumungsuebung::create([
        'durchgefuehrt_am' => today()->subMonths(14)->toDateString(),
        'intervall_monate' => 12,
    ]);

    $monitor = app(BrandschutzMonitor::class);
    // 1 Ordnung + 1 Begehung (Technikraum) + 1 Übung = 3
    expect($monitor->ueberfaelligeAnzahl())->toBe(3);
});
