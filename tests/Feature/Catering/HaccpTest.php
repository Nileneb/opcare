<?php

use App\Domains\Catering\Enums\HaccpArt;
use App\Domains\Catering\Models\HaccpMesspunkt;
use App\Domains\Catering\Models\Temperaturmessung;
use App\Domains\Catering\Services\HaccpMonitor;
use App\Domains\Catering\Services\MessungErfassen;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'HACCP Haus', 'slug' => 'haccp-haus']);
    app(CurrentTenant::class)->set($this->tenant);
});

// ---------------------------------------------------------------------------
// HaccpArt::istAbweichung via HaccpMesspunkt
// ---------------------------------------------------------------------------

it('erkennt Kühlung-Abweichung: zu warm (8 °C > 7 °C)', function () {
    $mp = HaccpMesspunkt::create([
        'bezeichnung' => 'Kühlhaus Gemüse',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    expect($mp->istAbweichung(8.0))->toBeTrue()
        ->and($mp->istAbweichung(6.0))->toBeFalse();
});

it('Kühlung-Grenzfall exakt 7,0 °C ist KEINE Abweichung', function () {
    $mp = HaccpMesspunkt::create([
        'bezeichnung' => 'Kühlhaus Test',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    expect($mp->istAbweichung(7.0))->toBeFalse();
});

it('erkennt Tiefkühlung-Abweichung: zu warm (-15 °C > -18 °C)', function () {
    $mp = HaccpMesspunkt::create([
        'bezeichnung' => 'Tiefkühlkammer',
        'art' => HaccpArt::Tiefkuehlung,
        'grenzwert' => -18.0,
        'aktiv' => true,
    ]);

    expect($mp->istAbweichung(-15.0))->toBeTrue()
        ->and($mp->istAbweichung(-20.0))->toBeFalse();
});

it('erkennt Heißhaltung-Abweichung: zu kalt (60 °C < 65 °C)', function () {
    $mp = HaccpMesspunkt::create([
        'bezeichnung' => 'Bain-Marie Suppe',
        'art' => HaccpArt::Heisshaltung,
        'grenzwert' => 65.0,
        'aktiv' => true,
    ]);

    expect($mp->istAbweichung(60.0))->toBeTrue()
        ->and($mp->istAbweichung(70.0))->toBeFalse();
});

it('Heißhaltung-Grenzfall exakt 65,0 °C ist KEINE Abweichung', function () {
    $mp = HaccpMesspunkt::create([
        'bezeichnung' => 'Ausgabe Test',
        'art' => HaccpArt::Heisshaltung,
        'grenzwert' => 65.0,
        'aktiv' => true,
    ]);

    expect($mp->istAbweichung(65.0))->toBeFalse();
});

// ---------------------------------------------------------------------------
// MessungErfassen
// ---------------------------------------------------------------------------

it('MessungErfassen setzt abweichung=true und offen()=true bei Überschreitung', function () {
    $mp = HaccpMesspunkt::create([
        'bezeichnung' => 'Kühlhaus A',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    $service = app(MessungErfassen::class);
    $messung = $service->handle($mp, 9.0, now()->subMinutes(5)->toDateTimeString());

    expect($messung->abweichung)->toBeTrue()
        ->and($messung->offen())->toBeTrue();
});

it('MessungErfassen mit Korrektur setzt offen()=false', function () {
    $mp = HaccpMesspunkt::create([
        'bezeichnung' => 'Kühlhaus B',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    $service = app(MessungErfassen::class);
    $messung = $service->handle($mp, 9.0, now()->subMinutes(5)->toDateTimeString(), null, 'Kühlgerät geprüft, Tür geschlossen');

    expect($messung->abweichung)->toBeTrue()
        ->and($messung->offen())->toBeFalse();
});

it('MessungErfassen ohne Abweichung setzt abweichung=false', function () {
    $mp = HaccpMesspunkt::create([
        'bezeichnung' => 'Kühlhaus C',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    $service = app(MessungErfassen::class);
    $messung = $service->handle($mp, 5.0, now()->subMinutes(5)->toDateTimeString());

    expect($messung->abweichung)->toBeFalse()
        ->and($messung->offen())->toBeFalse();
});

it('MessungErfassen speichert tenant_id korrekt', function () {
    $mp = HaccpMesspunkt::create([
        'bezeichnung' => 'Kühlhaus D',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    $service = app(MessungErfassen::class);
    $messung = $service->handle($mp, 5.0, now()->subMinutes(5)->toDateTimeString());

    expect($messung->tenant_id)->toBe($this->tenant->id);
});

// ---------------------------------------------------------------------------
// offeneAbweichung — Single Source of Truth
// ---------------------------------------------------------------------------

it('offeneAbweichung() ist true wenn offene Messung ohne Korrektur existiert', function () {
    $mp = HaccpMesspunkt::create([
        'bezeichnung' => 'CCP Ausgabe',
        'art' => HaccpArt::Ausgabe,
        'grenzwert' => 65.0,
        'aktiv' => true,
    ]);

    Temperaturmessung::create([
        'tenant_id' => $this->tenant->id,
        'haccp_messpunkt_id' => $mp->id,
        'gemessen_am' => now()->subMinutes(10),
        'wert' => 60.0,
        'abweichung' => true,
        'korrekturmassnahme' => null,
        'erfasst_von' => null,
    ]);

    expect($mp->offeneAbweichung())->toBeTrue();
});

it('offeneAbweichung() ist false nach Setzen der Korrekturmaßnahme', function () {
    $mp = HaccpMesspunkt::create([
        'bezeichnung' => 'CCP Ausgabe 2',
        'art' => HaccpArt::Ausgabe,
        'grenzwert' => 65.0,
        'aktiv' => true,
    ]);

    $messung = Temperaturmessung::create([
        'tenant_id' => $this->tenant->id,
        'haccp_messpunkt_id' => $mp->id,
        'gemessen_am' => now()->subMinutes(10),
        'wert' => 60.0,
        'abweichung' => true,
        'korrekturmassnahme' => null,
        'erfasst_von' => null,
    ]);

    $messung->update(['korrekturmassnahme' => 'Gerät nachgeregelt, Messung wiederholt']);

    expect($mp->offeneAbweichung())->toBeFalse();
});

// ---------------------------------------------------------------------------
// HaccpMonitor::tagesblatt
// ---------------------------------------------------------------------------

it('tagesblatt liefert je Messpunkt heutige Messungen und offene_abweichung', function () {
    $mp1 = HaccpMesspunkt::create([
        'bezeichnung' => 'Kühlhaus 1',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);
    $mp2 = HaccpMesspunkt::create([
        'bezeichnung' => 'Heißhalter 1',
        'art' => HaccpArt::Heisshaltung,
        'grenzwert' => 65.0,
        'aktiv' => true,
    ]);

    // Abweichende Messung heute
    Temperaturmessung::create([
        'tenant_id' => $this->tenant->id,
        'haccp_messpunkt_id' => $mp1->id,
        'gemessen_am' => now()->subHour(),
        'wert' => 9.0,
        'abweichung' => true,
        'korrekturmassnahme' => null,
        'erfasst_von' => null,
    ]);
    // Normale Messung heute
    Temperaturmessung::create([
        'tenant_id' => $this->tenant->id,
        'haccp_messpunkt_id' => $mp2->id,
        'gemessen_am' => now()->subHour(),
        'wert' => 70.0,
        'abweichung' => false,
        'korrekturmassnahme' => null,
        'erfasst_von' => null,
    ]);

    $monitor = app(HaccpMonitor::class);
    $blatt = $monitor->tagesblatt();

    expect($blatt)->toHaveCount(2);

    $byId = collect($blatt)->keyBy(fn (array $e) => $e['messpunkt']->id);

    expect($byId[$mp1->id]['messungen_heute'])->toHaveCount(1)
        ->and($byId[$mp1->id]['offene_abweichung'])->toBeTrue();

    expect($byId[$mp2->id]['messungen_heute'])->toHaveCount(1)
        ->and($byId[$mp2->id]['offene_abweichung'])->toBeFalse();
});

it('tagesblatt ist tenant-scoped (anderer Tenant unsichtbar)', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremdes Haus', 'slug' => 'fremdes-haus']);

    // Messpunkt im eigenen Tenant
    HaccpMesspunkt::create([
        'bezeichnung' => 'Eigener CCP',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    // Messpunkt im fremden Tenant — ohne CurrentTenant-Kontext direkt anlegen
    app(CurrentTenant::class)->set($fremderTenant);
    HaccpMesspunkt::create([
        'bezeichnung' => 'Fremder CCP',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    // Zurück zum eigenen Tenant
    app(CurrentTenant::class)->set($this->tenant);

    $monitor = app(HaccpMonitor::class);
    $blatt = $monitor->tagesblatt();

    expect($blatt)->toHaveCount(1)
        ->and($blatt[0]['messpunkt']->bezeichnung)->toBe('Eigener CCP');
});

it('tagesblatt schließt inaktive Messpunkte aus', function () {
    HaccpMesspunkt::create([
        'bezeichnung' => 'Aktiver CCP',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);
    HaccpMesspunkt::create([
        'bezeichnung' => 'Stillgelegter CCP',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => false,
    ]);

    $monitor = app(HaccpMonitor::class);
    $blatt = $monitor->tagesblatt();

    expect($blatt)->toHaveCount(1)
        ->and($blatt[0]['messpunkt']->bezeichnung)->toBe('Aktiver CCP');
});

it('tagesblatt liefert nur Messungen des angefragten Tages', function () {
    $mp = HaccpMesspunkt::create([
        'bezeichnung' => 'Tagestest CCP',
        'art' => HaccpArt::Kuehlung,
        'grenzwert' => 7.0,
        'aktiv' => true,
    ]);

    // Messung heute
    Temperaturmessung::create([
        'tenant_id' => $this->tenant->id,
        'haccp_messpunkt_id' => $mp->id,
        'gemessen_am' => now(),
        'wert' => 5.0,
        'abweichung' => false,
        'korrekturmassnahme' => null,
        'erfasst_von' => null,
    ]);
    // Messung gestern
    Temperaturmessung::create([
        'tenant_id' => $this->tenant->id,
        'haccp_messpunkt_id' => $mp->id,
        'gemessen_am' => now()->subDay(),
        'wert' => 6.0,
        'abweichung' => false,
        'korrekturmassnahme' => null,
        'erfasst_von' => null,
    ]);

    $monitor = app(HaccpMonitor::class);
    $blatt = $monitor->tagesblatt(today()->toDateString());

    expect($blatt[0]['messungen_heute'])->toHaveCount(1);
});
