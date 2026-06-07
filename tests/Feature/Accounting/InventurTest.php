<?php

use App\Domains\Accounting\Actions\InventurAbschliessen;
use App\Domains\Accounting\Actions\InventurStarten;
use App\Domains\Accounting\Actions\Wareneingang;
use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Enums\InventurStatus;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Inventur;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Accounting\Support\Lagerwert;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Accounting\Inventur as InventurLivewire;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);
    $this->mehl = Artikel::create(['name' => 'Mehl', 'einheit' => 'kg', 'abteilung' => Abteilung::Kueche, 'bestand' => 0, 'einkaufspreis' => 2.00]);
});

it('startet eine Inventur und snapshottet die Soll-Mengen je aktivem Artikel', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');

    $inventur = app(InventurStarten::class)->handle('2026-06-30', null, null);

    expect($inventur->status)->toBe(InventurStatus::Offen)
        ->and($inventur->positionen)->toHaveCount(1)
        ->and((float) $inventur->positionen[0]->soll_menge)->toBe(10.0)
        ->and((float) $inventur->positionen[0]->einstandspreis_schnitt)->toBe(2.0);
});

it('bucht Schwund FIFO ab (Inventurdifferenz an Warenbestand) und gleicht den Bestand ab', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');
    app(Wareneingang::class)->handle($this->mehl->fresh(), 5, 3.00, '2026-06-09'); // soll 15
    $inventur = app(InventurStarten::class)->handle('2026-06-30', null, null);
    $inventur->positionen[0]->update(['ist_menge' => 12]); // Schwund 3 → FIFO aus der 2€-Schicht

    $report = app(InventurAbschliessen::class)->handle($inventur->fresh(), null);

    expect((float) $this->mehl->fresh()->bestand)->toBe(12.0)
        ->and(AccountingDefaults::konto(AccountingDefaults::INVENTURDIFFERENZ)->saldo())->toBe(6.0) // 3 × 2,00
        ->and($inventur->fresh()->status)->toBe(InventurStatus::Abgeschlossen)
        ->and($report['gebucht'])->toBe(1)
        ->and($report['nicht_gezaehlt'])->toBe(0);
});

it('legt bei Mehrbestand eine neue Schicht an (Warenbestand an Inventurdifferenz)', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08'); // soll 10
    $inventur = app(InventurStarten::class)->handle('2026-06-30', null, null);
    $inventur->positionen[0]->update(['ist_menge' => 13]); // +3

    app(InventurAbschliessen::class)->handle($inventur->fresh(), null);

    expect((float) $this->mehl->fresh()->bestand)->toBe(13.0)
        ->and(app(Lagerwert::class)->bestandswert($this->mehl->fresh()))->toBe(26.0) // 13 × 2,00
        ->and(AccountingDefaults::konto(AccountingDefaults::INVENTURDIFFERENZ)->saldo())->toBe(-6.0); // Ertragswirkung
});

it('zählt nicht erfasste Positionen transparent und bucht sie nicht als 0-Differenz', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');
    $inventur = app(InventurStarten::class)->handle('2026-06-30', null, null);
    // ist_menge NICHT gesetzt

    $report = app(InventurAbschliessen::class)->handle($inventur->fresh(), null);

    expect($report['gebucht'])->toBe(0)
        ->and($report['nicht_gezaehlt'])->toBe(1)
        ->and((float) $this->mehl->fresh()->bestand)->toBe(10.0); // unverändert
});

it('verhindert den Doppel-Abschluss', function () {
    $inventur = app(InventurStarten::class)->handle('2026-06-30', null, null);
    app(InventurAbschliessen::class)->handle($inventur->fresh(), null);

    expect(fn () => app(InventurAbschliessen::class)->handle($inventur->fresh(), null))
        ->toThrow(InvalidArgumentException::class);
});

it('friert den Bestandswert beim Abschluss ein', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');
    $inventur = app(InventurStarten::class)->handle('2026-06-30', null, null);
    $inventur->positionen[0]->update(['ist_menge' => 10]);

    app(InventurAbschliessen::class)->handle($inventur->fresh(), null);

    expect((float) $inventur->fresh()->bestandswert_summe)->toBe(20.0);
});

it('startet, zählt und schließt eine Inventur über die Livewire-Komponente ab', function () {
    Role::findOrCreate('buchhaltung');
    $user = User::create(['name' => 'Anke', 'email' => 'a@a.de', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id]);
    $user->assignRole('buchhaltung');
    $this->actingAs($user);
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');

    Livewire::test(InventurLivewire::class)
        ->set('neu_stichtag', '2026-06-30')
        ->call('starten')
        ->assertHasNoErrors();

    $inventur = Inventur::firstOrFail();
    $posId = $inventur->positionen[0]->id;

    Livewire::test(InventurLivewire::class)
        ->set("ist.{$posId}", 8)
        ->call('zaehlen', $posId)
        ->call('abschliessen', $inventur->id)
        ->assertHasNoErrors();

    expect($inventur->fresh()->status->value)->toBe('abgeschlossen')
        ->and((float) $this->mehl->fresh()->bestand)->toBe(8.0);
});
