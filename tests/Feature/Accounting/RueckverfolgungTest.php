<?php

use App\Domains\Accounting\Actions\Wareneingang;
use App\Domains\Accounting\Actions\Warenverbrauch;
use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lagerschicht;
use App\Domains\Accounting\Models\Lieferant;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Accounting\Support\Chargenverfolgung;
use App\Domains\Accounting\Support\MhdMonitor;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Livewire\Accounting\Rueckverfolgung;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);
    $this->mehl = Artikel::create([
        'name' => 'Mehl', 'einheit' => 'kg',
        'abteilung' => Abteilung::Kueche, 'bestand' => 0, 'einkaufspreis' => 2.00,
    ]);
});

it('speichert charge_nr, mhd und lieferant_id auf der Lagerschicht', function () {
    $lieferant = Lieferant::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Frische-Depot GmbH',
        'lieferantennr' => 'LF-001',
    ]);

    app(Wareneingang::class)->handle(
        $this->mehl, 10, 2.00, '2026-06-07',
        'Testnotiz', 'L-123', '2026-08-01', $lieferant->id,
    );

    $schicht = Lagerschicht::where('artikel_id', $this->mehl->id)->firstOrFail();
    expect($schicht->charge_nr)->toBe('L-123')
        ->and($schicht->mhd->toDateString())->toBe('2026-08-01')
        ->and($schicht->lieferant_id)->toBe($lieferant->id);
});

it('Chargenverfolgung: enthält Lieferant (eine Stufe zurück) und Resident in Abgängen (interner Rückruf)', function () {
    $lieferant = Lieferant::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Frische-Depot GmbH',
    ]);
    $resident = Resident::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Max Mustermann',
        'geburtsdatum' => '1940-01-01',
        'geschlecht' => 'm',
        'aufnahme_am' => '2026-01-01',
        'pflegegrad' => 2,
    ]);

    app(Wareneingang::class)->handle(
        $this->mehl, 10, 2.00, '2026-06-07',
        null, 'L-123', null, $lieferant->id,
    );
    app(Warenverbrauch::class)->handle($this->mehl->fresh(), 3, '2026-06-08', 'Frühstück', $resident->id);

    $treffer = app(Chargenverfolgung::class)->verfolge('L-123', $this->tenant->id);

    expect($treffer)->toHaveCount(1)
        ->and($treffer[0]['lieferant'])->toBe('Frische-Depot GmbH')
        ->and($treffer[0]['abgaenge'])->toHaveCount(1)
        ->and($treffer[0]['abgaenge'][0]['resident'])->toBe('Max Mustermann')
        ->and($treffer[0]['abgaenge'][0]['abteilung'])->toBe(Abteilung::Kueche->label());
});

it('MhdMonitor: Schicht mit mhd=morgen ist enthalten', function () {
    app(Wareneingang::class)->handle(
        $this->mehl, 5, 2.00, '2026-06-07',
        null, 'L-001', today()->addDay()->toDateString(),
    );

    $liste = app(MhdMonitor::class)->ablaufend($this->tenant->id);

    expect($liste)->toHaveCount(1)
        ->and($liste[0]['artikel'])->toBe('Mehl')
        ->and($liste[0]['abgelaufen'])->toBeFalse();
});

it('MhdMonitor: Schicht mit mhd in 60 Tagen nicht im Default-Vorlauf von 14 Tagen', function () {
    app(Wareneingang::class)->handle(
        $this->mehl, 5, 2.00, '2026-06-07',
        null, 'L-002', today()->addDays(60)->toDateString(),
    );

    $liste = app(MhdMonitor::class)->ablaufend($this->tenant->id);

    expect($liste)->toHaveCount(0);
});

it('MhdMonitor: Schicht mit mhd=gestern enthält abgelaufen=true', function () {
    app(Wareneingang::class)->handle(
        $this->mehl, 5, 2.00, '2026-06-06',
        null, 'L-003', today()->subDay()->toDateString(),
    );

    $liste = app(MhdMonitor::class)->ablaufend($this->tenant->id);

    expect($liste)->toHaveCount(1)
        ->and($liste[0]['abgelaufen'])->toBeTrue();
});

it('MhdMonitor: vollständig verbrauchte Schicht erscheint nicht', function () {
    app(Wareneingang::class)->handle(
        $this->mehl, 5, 2.00, '2026-06-06',
        null, 'L-004', today()->addDay()->toDateString(),
    );
    app(Warenverbrauch::class)->handle($this->mehl->fresh(), 5, '2026-06-07');

    $liste = app(MhdMonitor::class)->ablaufend($this->tenant->id);

    expect($liste)->toHaveCount(0);
});

it('Livewire Rueckverfolgung rendert für Rolle buchhaltung', function () {
    Role::findOrCreate('buchhaltung');
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole('buchhaltung');
    $this->actingAs($user);

    Livewire::test(Rueckverfolgung::class)->assertOk();
});

it('Livewire Rueckverfolgung zeigt Lieferantenname bei Chargensuche', function () {
    Role::findOrCreate('buchhaltung');
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole('buchhaltung');
    $this->actingAs($user);

    $lieferant = Lieferant::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Frische-Depot GmbH',
    ]);
    app(Wareneingang::class)->handle(
        $this->mehl, 10, 2.00, '2026-06-07',
        null, 'L-999', null, $lieferant->id,
    );

    Livewire::test(Rueckverfolgung::class)
        ->set('charge', 'L-999')
        ->assertSee('Frische-Depot GmbH');
});
