<?php

use App\Domains\Catering\Enums\ReinigungsIntervall;
use App\Domains\Catering\Models\Reinigungsaufgabe;
use App\Domains\Catering\Models\Reinigungsnachweis;
use App\Domains\Catering\Services\ReinigungErledigen;
use App\Domains\Catering\Services\ReinigungsplanMonitor;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Reinigungs Haus', 'slug' => 'reinigungs-haus']);
    app(CurrentTenant::class)->set($this->tenant);
});

// ---------------------------------------------------------------------------
// ReinigungsIntervall Enum
// ---------------------------------------------------------------------------

it('ReinigungsIntervall hat korrekte tage()-Werte', function () {
    expect(ReinigungsIntervall::Taeglich->tage())->toBe(1)
        ->and(ReinigungsIntervall::Woechentlich->tage())->toBe(7)
        ->and(ReinigungsIntervall::ZweiWoechentlich->tage())->toBe(14)
        ->and(ReinigungsIntervall::Monatlich->tage())->toBe(30)
        ->and(ReinigungsIntervall::Quartalsweise->tage())->toBe(90);
});

it('ReinigungsIntervall hat korrekte label()-Werte', function () {
    expect(ReinigungsIntervall::Taeglich->label())->toBe('Täglich')
        ->and(ReinigungsIntervall::Woechentlich->label())->toBe('Wöchentlich')
        ->and(ReinigungsIntervall::ZweiWoechentlich->label())->toBe('Zweiwöchentlich')
        ->and(ReinigungsIntervall::Monatlich->label())->toBe('Monatlich')
        ->and(ReinigungsIntervall::Quartalsweise->label())->toBe('Vierteljährlich');
});

// ---------------------------------------------------------------------------
// naechsteFaelligkeit / istUeberfaellig / faelligkeitsStatus
// ---------------------------------------------------------------------------

it('nie erledigt: naechsteFaelligkeit null, istUeberfaellig true, Status rot', function () {
    $aufgabe = Reinigungsaufgabe::create([
        'bezeichnung' => 'Arbeitsflächen',
        'intervall' => ReinigungsIntervall::Taeglich,
        'aktiv' => true,
    ]);

    expect($aufgabe->naechsteFaelligkeit())->toBeNull()
        ->and($aufgabe->istUeberfaellig())->toBeTrue()
        ->and($aufgabe->faelligkeitsStatus())->toBe('rot');
});

it('täglich + letzte gestern: fällig heute, nicht überfällig, Status gelb', function () {
    $aufgabe = Reinigungsaufgabe::create([
        'bezeichnung' => 'Böden',
        'intervall' => ReinigungsIntervall::Taeglich,
        'aktiv' => true,
        'letzte_erledigung_am' => today()->subDay()->toDateString(),
    ]);

    // naechste = heute → lt(today()) false → nicht überfällig
    // Gelb-Schwelle = 3 Tage → heute lte today()+3 → gelb
    expect($aufgabe->naechsteFaelligkeit()->isToday())->toBeTrue()
        ->and($aufgabe->istUeberfaellig())->toBeFalse()
        ->and($aufgabe->faelligkeitsStatus())->toBe('gelb');
});

it('wöchentlich + letzte vor 8 Tagen: überfällig → rot', function () {
    $aufgabe = Reinigungsaufgabe::create([
        'bezeichnung' => 'Kühlhaus',
        'intervall' => ReinigungsIntervall::Woechentlich,
        'aktiv' => true,
        'letzte_erledigung_am' => today()->subDays(8)->toDateString(),
    ]);

    expect($aufgabe->istUeberfaellig())->toBeTrue()
        ->and($aufgabe->faelligkeitsStatus())->toBe('rot');
});

it('wöchentlich + letzte vor 2 Tagen: fällig in 5 Tagen → grün', function () {
    $aufgabe = Reinigungsaufgabe::create([
        'bezeichnung' => 'Dunstabzug',
        'intervall' => ReinigungsIntervall::Woechentlich,
        'aktiv' => true,
        'letzte_erledigung_am' => today()->subDays(2)->toDateString(),
    ]);

    // naechste in 5 Tagen → 5 > 3 (Gelb-Schwelle) → grün
    expect($aufgabe->istUeberfaellig())->toBeFalse()
        ->and($aufgabe->faelligkeitsStatus())->toBe('gruen');
});

it('wöchentlich + Fälligkeit in 3 Tagen → gelb', function () {
    $aufgabe = Reinigungsaufgabe::create([
        'bezeichnung' => 'Lager',
        'intervall' => ReinigungsIntervall::Woechentlich,
        'aktiv' => true,
        'letzte_erledigung_am' => today()->subDays(4)->toDateString(),
    ]);

    // letzte vor 4 Tagen → nächste in 3 Tagen → lte(today()+3) → gelb
    expect($aufgabe->naechsteFaelligkeit()->toDateString())->toBe(today()->addDays(3)->toDateString())
        ->and($aufgabe->faelligkeitsStatus())->toBe('gelb');
});

it('inaktive Aufgabe ist nie überfällig und immer grün', function () {
    $aufgabe = Reinigungsaufgabe::create([
        'bezeichnung' => 'Stillgelegte Aufgabe',
        'intervall' => ReinigungsIntervall::Taeglich,
        'aktiv' => false,
    ]);

    expect($aufgabe->istUeberfaellig())->toBeFalse()
        ->and($aufgabe->faelligkeitsStatus())->toBe('gruen');
});

// ---------------------------------------------------------------------------
// ReinigungErledigen
// ---------------------------------------------------------------------------

it('ReinigungErledigen legt Nachweis an und setzt letzte_erledigung_am', function () {
    $aufgabe = Reinigungsaufgabe::create([
        'bezeichnung' => 'Arbeitsflächen',
        'intervall' => ReinigungsIntervall::Taeglich,
        'aktiv' => true,
    ]);

    $service = app(ReinigungErledigen::class);
    $nachweis = $service->handle($aufgabe, today()->toDateString());

    expect($nachweis)->toBeInstanceOf(Reinigungsnachweis::class)
        ->and($nachweis->reinigungsaufgabe_id)->toBe($aufgabe->id)
        ->and($nachweis->erledigt_am->toDateString())->toBe(today()->toDateString())
        ->and($nachweis->tenant_id)->toBe($this->tenant->id);

    $aufgabe->refresh();
    expect($aufgabe->letzte_erledigung_am->toDateString())->toBe(today()->toDateString());
});

it('ReinigungErledigen: nachgetragener älterer Nachweis setzt letzte_erledigung_am nicht zurück (Max)', function () {
    $aufgabe = Reinigungsaufgabe::create([
        'bezeichnung' => 'Böden',
        'intervall' => ReinigungsIntervall::Woechentlich,
        'aktiv' => true,
        'letzte_erledigung_am' => today()->toDateString(),
    ]);

    $service = app(ReinigungErledigen::class);
    $service->handle($aufgabe, today()->subDays(3)->toDateString(), null, 'Nachtrag');

    $aufgabe->refresh();
    expect($aufgabe->letzte_erledigung_am->toDateString())->toBe(today()->toDateString());
});

it('ReinigungErledigen speichert userId und Bemerkung korrekt', function () {
    $aufgabe = Reinigungsaufgabe::create([
        'bezeichnung' => 'Kühlhaus',
        'intervall' => ReinigungsIntervall::Woechentlich,
        'aktiv' => true,
    ]);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $service = app(ReinigungErledigen::class);
    $nachweis = $service->handle($aufgabe, today()->toDateString(), $user->id, 'Mit Desinfektionsmittel A gereinigt');

    expect($nachweis->erledigt_von)->toBe($user->id)
        ->and($nachweis->bemerkung)->toBe('Mit Desinfektionsmittel A gereinigt');
});

// ---------------------------------------------------------------------------
// ReinigungsplanMonitor
// ---------------------------------------------------------------------------

it('ReinigungsplanMonitor::status liefert aktive Aufgaben mit korrektem Status', function () {
    Reinigungsaufgabe::create([
        'bezeichnung' => 'Aufgabe A',
        'intervall' => ReinigungsIntervall::Taeglich,
        'aktiv' => true,
    ]);
    Reinigungsaufgabe::create([
        'bezeichnung' => 'Aufgabe B',
        'intervall' => ReinigungsIntervall::Woechentlich,
        'aktiv' => true,
        'letzte_erledigung_am' => today()->subDays(2)->toDateString(),
    ]);
    // Inaktive Aufgabe darf nicht erscheinen
    Reinigungsaufgabe::create([
        'bezeichnung' => 'Aufgabe C inaktiv',
        'intervall' => ReinigungsIntervall::Monatlich,
        'aktiv' => false,
    ]);

    $monitor = app(ReinigungsplanMonitor::class);
    $result = $monitor->status();

    expect($result)->toHaveCount(2);

    $byName = collect($result)->keyBy(fn (array $e) => $e['aufgabe']->bezeichnung);

    expect($byName['Aufgabe A']['status'])->toBe('rot')
        ->and($byName['Aufgabe B']['status'])->toBe('gruen')
        ->and($byName->has('Aufgabe C inaktiv'))->toBeFalse();
});

it('ReinigungsplanMonitor::status ist tenant-scoped (fremder Tenant unsichtbar)', function () {
    $fremderTenant = Tenant::create(['name' => 'Fremdes Reinigungs Haus', 'slug' => 'fremdes-reinigung']);

    Reinigungsaufgabe::create([
        'bezeichnung' => 'Eigene Reinigung',
        'intervall' => ReinigungsIntervall::Taeglich,
        'aktiv' => true,
    ]);

    app(CurrentTenant::class)->set($fremderTenant);
    Reinigungsaufgabe::create([
        'bezeichnung' => 'Fremde Reinigung',
        'intervall' => ReinigungsIntervall::Taeglich,
        'aktiv' => true,
    ]);
    app(CurrentTenant::class)->set($this->tenant);

    $monitor = app(ReinigungsplanMonitor::class);
    $result = $monitor->status();

    expect($result)->toHaveCount(1)
        ->and($result[0]['aufgabe']->bezeichnung)->toBe('Eigene Reinigung');
});

it('ReinigungsplanMonitor::status enthält korrekte naechste Fälligkeit', function () {
    Reinigungsaufgabe::create([
        'bezeichnung' => 'Ausgabe',
        'intervall' => ReinigungsIntervall::Woechentlich,
        'aktiv' => true,
        'letzte_erledigung_am' => today()->toDateString(),
    ]);

    $monitor = app(ReinigungsplanMonitor::class);
    $result = $monitor->status();

    expect($result[0]['naechste'])->not->toBeNull()
        ->and($result[0]['naechste']->toDateString())->toBe(today()->addDays(7)->toDateString());
});
