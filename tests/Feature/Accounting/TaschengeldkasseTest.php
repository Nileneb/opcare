<?php

use App\Domains\Accounting\Enums\BarbetragKategorie;
use App\Domains\Accounting\Models\Treuhandbuchung;
use App\Domains\Accounting\Models\Treuhandkonto;
use App\Domains\Accounting\Models\TreuhandMonatsabschluss;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;
use App\Livewire\Accounting\Taschengeldkasse;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('buchhaltung');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('buchhaltung');
    $this->actingAs($this->user);
    $this->resident = Resident::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'aktiv']);
});

it('verwehrt den Zugriff ohne Verwaltungsrolle', function () {
    $fremd = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($fremd);

    Livewire::test(Taschengeldkasse::class)->assertForbidden();
});

it('legt ein Treuhandkonto an und verhindert ein zweites je Bewohner', function () {
    Livewire::test(Taschengeldkasse::class)
        ->set('k_resident', $this->resident->id)->call('kontoAnlegen')->assertHasNoErrors();

    expect(Treuhandkonto::where('resident_id', $this->resident->id)->count())->toBe(1);

    Livewire::test(Taschengeldkasse::class)
        ->set('k_resident', $this->resident->id)->call('kontoAnlegen')->assertHasErrors('k_resident');

    expect(Treuhandkonto::where('resident_id', $this->resident->id)->count())->toBe(1);
});

it('bucht Ein- und Auszahlung über die UI', function () {
    $konto = Treuhandkonto::create(['tenant_id' => $this->tenant->id, 'resident_id' => $this->resident->id, 'eroeffnet_am' => '2026-06-01']);

    Livewire::test(Taschengeldkasse::class)
        ->set('selected', $konto->id)
        ->set('b_vorgang', 'einzahlung')->set('b_betrag', 80)->set('b_datum', '2026-06-02')->set('b_zweck', 'Rente')
        ->call('buchen')->assertHasNoErrors()
        ->set('b_vorgang', 'auszahlung')->set('b_betrag', 25)->set('b_datum', '2026-06-03')->set('b_kategorie', BarbetragKategorie::Friseur->value)->set('b_zweck', 'Friseur')
        ->call('buchen')->assertHasNoErrors();

    expect($konto->fresh()->saldo())->toBe(55.0)
        ->and(Treuhandbuchung::where('treuhand_konto_id', $konto->id)->count())->toBe(2);
});

it('setzt ein Sperr-Budget und blockiert die Auszahlung darüber', function () {
    $konto = Treuhandkonto::create(['tenant_id' => $this->tenant->id, 'resident_id' => $this->resident->id, 'eroeffnet_am' => '2026-06-01']);

    Livewire::test(Taschengeldkasse::class)
        ->set('selected', $konto->id)
        ->set('b_vorgang', 'einzahlung')->set('b_betrag', 200)->set('b_datum', '2026-06-01')->set('b_zweck', 'Einzahlung')->call('buchen')->assertHasNoErrors()
        ->set('bg_kategorie', BarbetragKategorie::Friseur->value)->set('bg_limit', 40)->set('bg_warn', 80)->set('bg_sperre', true)->call('budgetSetzen')->assertHasNoErrors()
        ->set('b_vorgang', 'auszahlung')->set('b_betrag', 50)->set('b_datum', '2026-06-05')->set('b_kategorie', BarbetragKategorie::Friseur->value)->set('b_zweck', 'Friseur')
        ->call('buchen')->assertHasErrors('b_betrag');

    expect($konto->fresh()->saldo())->toBe(200.0);
});

it('erstellt einen gesperrten Monatsabschluss mit korrekten Summen', function () {
    $konto = Treuhandkonto::create(['tenant_id' => $this->tenant->id, 'resident_id' => $this->resident->id, 'eroeffnet_am' => '2026-06-01']);

    Livewire::test(Taschengeldkasse::class)
        ->set('selected', $konto->id)
        ->set('b_vorgang', 'einzahlung')->set('b_betrag', 100)->set('b_datum', '2026-06-02')->set('b_zweck', 'Rente')->call('buchen')
        ->set('b_vorgang', 'auszahlung')->set('b_betrag', 30)->set('b_datum', '2026-06-10')->set('b_kategorie', BarbetragKategorie::Kleidung->value)->set('b_zweck', 'Kleidung')->call('buchen')
        ->set('ab_monat', '2026-06-01')->set('ab_erstellt_von', 'Verwaltung')->call('monatsabschluss')->assertHasNoErrors();

    $a = TreuhandMonatsabschluss::where('treuhand_konto_id', $konto->id)->firstOrFail();
    expect((float) $a->summe_einzahlungen)->toBe(100.0)
        ->and((float) $a->summe_auszahlungen)->toBe(30.0)
        ->and((float) $a->endbestand)->toBe(70.0)
        ->and($a->gesperrt_am)->not->toBeNull();
});
