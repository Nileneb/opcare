<?php

use App\Domains\Facility\Models\Probenahmestelle;
use App\Domains\Facility\Models\Trinkwasseranlage;
use App\Domains\Facility\Services\BefundErfassen;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'TW-Test', 'slug' => 'tw-test']);
    app(CurrentTenant::class)->set($this->tenant);
});

// --- Frist-Ampel ---

it('ist überfällig und rot wenn nie untersucht wurde', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Warmwasseranlage Nord',
        'ist_grossanlage' => true,
        'untersuchungsintervall_monate' => 12,
    ]);

    expect($anlage->naechsteFaelligkeit())->toBeNull();
    expect($anlage->istUeberfaellig())->toBeTrue();
    expect($anlage->faelligkeitsStatus())->toBe('rot');
});

it('ist überfällig und rot wenn letzte Untersuchung vor 13 Monaten liegt (Intervall 12)', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Warmwasseranlage Süd',
        'ist_grossanlage' => true,
        'untersuchungsintervall_monate' => 12,
        'letzte_untersuchung_am' => now()->subMonths(13)->toDateString(),
    ]);

    expect($anlage->naechsteFaelligkeit()->isPast())->toBeTrue();
    expect($anlage->istUeberfaellig())->toBeTrue();
    expect($anlage->faelligkeitsStatus())->toBe('rot');
});

it('ist gruen wenn letzte Untersuchung vor 1 Monat liegt (Intervall 12)', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Warmwasseranlage West',
        'ist_grossanlage' => true,
        'untersuchungsintervall_monate' => 12,
        'letzte_untersuchung_am' => now()->subMonth()->toDateString(),
    ]);

    expect($anlage->istUeberfaellig())->toBeFalse();
    expect($anlage->faelligkeitsStatus())->toBe('gruen');
});

it('ist gelb wenn Fälligkeit in 20 Tagen liegt', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Warmwasseranlage Ost',
        'ist_grossanlage' => true,
        'untersuchungsintervall_monate' => 12,
        'letzte_untersuchung_am' => now()->subMonths(12)->addDays(20)->toDateString(),
    ]);

    expect($anlage->istUeberfaellig())->toBeFalse();
    expect($anlage->faelligkeitsStatus())->toBe('gelb');
});

// --- BefundErfassen ---

it('setzt ueberschreitung=false bei kbe=99', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Anlage A',
        'ist_grossanlage' => true,
    ]);

    $befund = app(BefundErfassen::class)->handle($anlage, null, '2026-06-01', 99);

    expect($befund->ueberschreitung)->toBeFalse();
    expect($befund->kbe_pro_100ml)->toBe(99);
});

it('setzt ueberschreitung=true bei kbe=100 (Maßnahmenwert)', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Anlage B',
        'ist_grossanlage' => true,
    ]);

    $befund = app(BefundErfassen::class)->handle($anlage, null, '2026-06-01', 100);

    expect($befund->ueberschreitung)->toBeTrue();
});

it('setzt ueberschreitung=true bei kbe=101', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Anlage C',
        'ist_grossanlage' => true,
    ]);

    $befund = app(BefundErfassen::class)->handle($anlage, null, '2026-06-01', 101);

    expect($befund->ueberschreitung)->toBeTrue();
});

it('aktualisiert letzte_untersuchung_am beim Befund erfassen', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Anlage D',
        'ist_grossanlage' => true,
    ]);

    app(BefundErfassen::class)->handle($anlage, null, '2026-06-01', 50);

    expect($anlage->fresh()->letzte_untersuchung_am->toDateString())->toBe('2026-06-01');
});

it('behält das neuere Datum wenn ein späterer Befund folgt', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Anlage E',
        'ist_grossanlage' => true,
        'letzte_untersuchung_am' => '2026-05-01',
    ]);

    app(BefundErfassen::class)->handle($anlage, null, '2026-06-10', 30);

    expect($anlage->fresh()->letzte_untersuchung_am->toDateString())->toBe('2026-06-10');
});

it('überschreibt letzte_untersuchung_am NICHT durch älteren Befund', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Anlage F',
        'ist_grossanlage' => true,
        'letzte_untersuchung_am' => '2026-06-10',
    ]);

    app(BefundErfassen::class)->handle($anlage, null, '2026-05-01', 30);

    expect($anlage->fresh()->letzte_untersuchung_am->toDateString())->toBe('2026-06-10');
});

it('verknüpft Befund mit Probenahmestelle', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Anlage G',
        'ist_grossanlage' => true,
    ]);
    $stelle = Probenahmestelle::create([
        'tenant_id' => $this->tenant->id,
        'trinkwasseranlage_id' => $anlage->id,
        'bezeichnung' => 'Austritt Erwärmer',
    ]);

    $befund = app(BefundErfassen::class)->handle($anlage, $stelle->id, '2026-06-01', 55, 'Testlabor GmbH');

    expect($befund->probenahmestelle_id)->toBe($stelle->id);
    expect($befund->labor)->toBe('Testlabor GmbH');
});

// --- offeneUeberschreitung ---

it('meldet offeneUeberschreitung wenn Überschreitungs-Befund ohne Meldung/Maßnahme vorliegt', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Anlage H',
        'ist_grossanlage' => true,
    ]);

    app(BefundErfassen::class)->handle($anlage, null, '2026-06-01', 150);

    expect($anlage->offeneUeberschreitung())->toBeTrue();
});

it('offeneUeberschreitung ist false nachdem Meldung und Maßnahme gesetzt wurden', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Anlage I',
        'ist_grossanlage' => true,
    ]);

    $befund = app(BefundErfassen::class)->handle($anlage, null, '2026-06-01', 200);

    $befund->update([
        'gesundheitsamt_gemeldet_am' => '2026-06-02',
        'massnahme' => 'Thermische Desinfektion durchgeführt.',
    ]);

    expect($anlage->offeneUeberschreitung())->toBeFalse();
});

it('bleibt offen wenn nur Meldung aber keine Maßnahme gesetzt', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Anlage J',
        'ist_grossanlage' => true,
    ]);

    $befund = app(BefundErfassen::class)->handle($anlage, null, '2026-06-01', 300);

    $befund->update(['gesundheitsamt_gemeldet_am' => '2026-06-02']);

    expect($anlage->offeneUeberschreitung())->toBeTrue();
});

it('bleibt offen wenn nur Maßnahme aber keine Meldung gesetzt', function () {
    $anlage = Trinkwasseranlage::create([
        'tenant_id' => $this->tenant->id,
        'bezeichnung' => 'Anlage K',
        'ist_grossanlage' => true,
    ]);

    $befund = app(BefundErfassen::class)->handle($anlage, null, '2026-06-01', 300);

    $befund->update(['massnahme' => 'Spülung und Chlorung.']);

    expect($anlage->offeneUeberschreitung())->toBeTrue();
});
