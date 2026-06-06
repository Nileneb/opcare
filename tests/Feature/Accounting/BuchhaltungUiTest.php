<?php

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Accounting\Buchhaltung;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    Role::findOrCreate('buchhaltung');
    $this->buchhalter = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->buchhalter->assignRole('buchhaltung');
    $this->actingAs($this->buchhalter);
});

it('bucht einen Wareneingang über die UI und zeigt den Saldo', function () {
    AccountingDefaults::ensureFor($this->tenant->id);
    $mehl = Artikel::create(['name' => 'Mehl', 'einheit' => 'kg', 'abteilung' => Abteilung::Kueche, 'bestand' => 0, 'einkaufspreis' => 2.0]);

    Livewire::test(Buchhaltung::class)
        ->set('beweg_artikel', $mehl->id)->set('beweg_menge', 10)->set('beweg_preis', 2.0)
        ->call('wareneingang')->assertHasNoErrors();

    expect((float) $mehl->fresh()->bestand)->toBe(10.0)
        ->and(AccountingDefaults::konto(AccountingDefaults::WARENBESTAND)->saldo())->toBe(20.0);
});

it('legt einen Artikel an und verbucht den Verbrauch aufs Abteilungs-Aufwandskonto', function () {
    Livewire::test(Buchhaltung::class)
        ->set('a_name', 'Handschuhe')->set('a_einheit', 'Box')->set('a_abteilung', Abteilung::Pflege->value)
        ->call('artikelAnlegen')->assertHasNoErrors();

    $artikel = Artikel::where('name', 'Handschuhe')->firstOrFail();
    expect($artikel->abteilung)->toBe(Abteilung::Pflege);

    Livewire::test(Buchhaltung::class)
        ->set('beweg_artikel', $artikel->id)->set('beweg_menge', 5)->set('beweg_preis', 4.0)
        ->call('wareneingang')->assertHasNoErrors()
        ->set('beweg_artikel', $artikel->id)->set('beweg_menge', 2)
        ->call('verbrauch')->assertHasNoErrors();

    expect((float) $artikel->fresh()->bestand)->toBe(3.0)
        ->and(AccountingDefaults::konto(Abteilung::Pflege->aufwandKonto())->saldo())->toBe(8.0);
});

it('verwehrt den Zugriff ohne Buchhaltungs-Rolle', function () {
    $fremd = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($fremd);

    Livewire::test(Buchhaltung::class)->assertForbidden();
});
