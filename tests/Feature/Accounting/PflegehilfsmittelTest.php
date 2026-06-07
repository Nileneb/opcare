<?php

use App\Domains\Accounting\Actions\Wareneingang;
use App\Domains\Accounting\Actions\Warenverbrauch;
use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Schichtabgang;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Accounting\Support\PflegehilfsmittelMonitor;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Livewire\Accounting\Pflegehilfsmittel;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'PHM-Test', 'slug' => 'phm']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);

    $this->artikel = Artikel::create([
        'name' => 'Einmalhandschuhe PG54',
        'einheit' => 'Stück',
        'abteilung' => Abteilung::Pflege,
        'bestand' => 0,
        'pflegehilfsmittel' => true,
        'pg_nummer' => '54.40.01',
    ]);

    $this->artikelAndere = Artikel::create([
        'name' => 'Büropapier',
        'einheit' => 'Blatt',
        'abteilung' => Abteilung::Verwaltung,
        'bestand' => 0,
        'pflegehilfsmittel' => false,
    ]);

    // Stock the shelves
    app(Wareneingang::class)->handle($this->artikel, 100, 0.30, '2026-06-01');
    app(Wareneingang::class)->handle($this->artikel->fresh(), 100, 0.30, '2026-06-01');
    app(Wareneingang::class)->handle($this->artikelAndere, 500, 0.05, '2026-06-01');
});

// ─── Warenverbrauch mit resident_id ──────────────────────────────────────────

it('trägt resident_id in alle Schichtabgänge ein wenn angegeben', function () {
    $resident = Resident::factory()->create(['tenant_id' => $this->tenant->id]);

    app(Warenverbrauch::class)->handle(
        $this->artikel->fresh(), 10, '2026-06-05', null, $resident->id,
    );

    $abgaenge = Schichtabgang::where('tenant_id', $this->tenant->id)->get();
    expect($abgaenge)->not->toBeEmpty();
    foreach ($abgaenge as $abgang) {
        expect($abgang->resident_id)->toBe($resident->id);
    }
});

it('lässt resident_id null wenn kein Bewohner angegeben', function () {
    app(Warenverbrauch::class)->handle($this->artikel->fresh(), 5, '2026-06-05');

    $abgaenge = Schichtabgang::where('tenant_id', $this->tenant->id)->get();
    expect($abgaenge)->not->toBeEmpty();
    foreach ($abgaenge as $abgang) {
        expect($abgang->resident_id)->toBeNull();
    }
});

// ─── PflegehilfsmittelMonitor ─────────────────────────────────────────────────

it('summiert Pflegehilfsmittel-Verbrauch korrekt je Bewohner im Monat', function () {
    $r1 = Resident::factory()->create(['tenant_id' => $this->tenant->id]);
    $r2 = Resident::factory()->create(['tenant_id' => $this->tenant->id]);

    // r1: 30 × 0,30 = 9,00 €
    app(Warenverbrauch::class)->handle($this->artikel->fresh(), 30, '2026-06-10', null, $r1->id);
    // r2: 20 × 0,30 = 6,00 €
    app(Warenverbrauch::class)->handle($this->artikel->fresh(), 20, '2026-06-15', null, $r2->id);

    $monitor = app(PflegehilfsmittelMonitor::class);
    $result = $monitor->verbrauchProBewohner($this->tenant->id, '2026-06');

    expect($result)->toHaveCount(2);

    $byId = collect($result)->keyBy(fn ($row) => $row['resident']->id);

    expect((float) $byId[$r1->id]['summe'])->toBe(9.0)
        ->and($byId[$r1->id]['ampel'])->toBe('gruen');

    expect((float) $byId[$r2->id]['summe'])->toBe(6.0)
        ->and($byId[$r2->id]['ampel'])->toBe('gruen');
});

it('zählt Nicht-Pflegehilfsmittel-Verbrauch NICHT mit', function () {
    $r1 = Resident::factory()->create(['tenant_id' => $this->tenant->id]);

    app(Warenverbrauch::class)->handle($this->artikelAndere->fresh(), 10, '2026-06-10', null, $r1->id);

    $result = app(PflegehilfsmittelMonitor::class)->verbrauchProBewohner($this->tenant->id, '2026-06');

    expect($result)->toBeEmpty();
});

it('ignoriert Verbrauch aus dem Vormonat (Monatsgrenze, date-Cast-Falle)', function () {
    $r1 = Resident::factory()->create(['tenant_id' => $this->tenant->id]);

    // Letzter Tag Mai — soll NICHT in 2026-06 auftauchen
    app(Warenverbrauch::class)->handle($this->artikel->fresh(), 10, '2026-05-31', null, $r1->id);
    // Erster Tag Juni — soll auftauchen
    app(Warenverbrauch::class)->handle($this->artikel->fresh(), 5, '2026-06-01', null, $r1->id);

    $result = app(PflegehilfsmittelMonitor::class)->verbrauchProBewohner($this->tenant->id, '2026-06');

    expect($result)->toHaveCount(1);
    expect((float) $result[0]['summe'])->toBe(round(5 * 0.30, 2));
});

it('setzt Ampel amber wenn 80–99 % der Pauschale erreicht', function () {
    $r1 = Resident::factory()->create(['tenant_id' => $this->tenant->id]);

    // Ziel: 80 % von 42 € = 33,60 € → 112 Stück à 0,30 = 33,60 €
    app(Warenverbrauch::class)->handle($this->artikel->fresh(), 112, '2026-06-10', null, $r1->id);

    $result = app(PflegehilfsmittelMonitor::class)->verbrauchProBewohner($this->tenant->id, '2026-06');

    expect($result[0]['ampel'])->toBe('amber');
});

it('setzt Ampel rot wenn 100 % oder mehr der Pauschale erreicht', function () {
    $r1 = Resident::factory()->create(['tenant_id' => $this->tenant->id]);

    // 42 € / 0,30 = 140 Stück → genau 100 %
    app(Warenverbrauch::class)->handle($this->artikel->fresh(), 140, '2026-06-10', null, $r1->id);

    $result = app(PflegehilfsmittelMonitor::class)->verbrauchProBewohner($this->tenant->id, '2026-06');

    expect($result[0]['ampel'])->toBe('rot');
});

it('berechnet den prozent-Wert korrekt', function () {
    $r1 = Resident::factory()->create(['tenant_id' => $this->tenant->id]);

    // 21 € = 50 % von 42 €  → 70 Stück × 0,30 = 21,00 €
    app(Warenverbrauch::class)->handle($this->artikel->fresh(), 70, '2026-06-10', null, $r1->id);

    $result = app(PflegehilfsmittelMonitor::class)->verbrauchProBewohner($this->tenant->id, '2026-06');

    expect($result[0]['prozent'])->toBe(50);
});

// ─── Livewire Smoke ────────────────────────────────────────────────────────────

it('rendert die Pflegehilfsmittel-Seite für Buchhaltungs-Rolle und zeigt §-40-Hinweis', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $role = Role::findOrCreate('buchhaltung');
    $user->assignRole($role);

    Livewire::actingAs($user)
        ->test(Pflegehilfsmittel::class)
        ->assertOk()
        ->assertSee('§ 40');
});
