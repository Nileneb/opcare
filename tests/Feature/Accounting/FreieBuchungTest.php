<?php

use App\Domains\Accounting\Enums\KontoTyp;
use App\Domains\Accounting\Models\Buchung;
use App\Domains\Accounting\Models\Konto;
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
    Role::findOrCreate('kueche');
    $this->kasse = Konto::create(['nummer' => '1000', 'name' => 'Kasse', 'typ' => KontoTyp::Aktiv]);
    $this->ertrag = Konto::create(['nummer' => '8000', 'name' => 'Spenden', 'typ' => KontoTyp::Ertrag]);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('buchhaltung');
});

it('erfasst eine freie Buchung im Hauptbuch über die Buchen-Action', function () {
    $this->actingAs($this->user);
    Livewire::test(Buchhaltung::class)
        ->set('b_soll', $this->kasse->id)->set('b_haben', $this->ertrag->id)
        ->set('b_betrag', 250.0)->set('b_text', 'Bareinzahlung Spende')->set('b_beleg', 'Q-12')
        ->call('freieBuchung')->assertHasNoErrors();

    $b = Buchung::first();
    expect($b)->not->toBeNull()
        ->and((float) $b->betrag)->toBe(250.0)
        ->and($b->beleg)->toBe('Q-12')
        ->and($this->kasse->saldo())->toBe(250.0);
});

it('lehnt eine Buchung mit identischem Soll- und Haben-Konto ab', function () {
    $this->actingAs($this->user);
    Livewire::test(Buchhaltung::class)
        ->set('b_soll', $this->kasse->id)->set('b_haben', $this->kasse->id)
        ->set('b_betrag', 10.0)->set('b_text', 'x')
        ->call('freieBuchung')->assertHasErrors('b_haben');

    expect(Buchung::count())->toBe(0);
});

it('verhindert das Buchen auf ein fremdes Konto (IDOR)', function () {
    $fremd = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($fremd);
    $fremdKonto = Konto::create(['nummer' => '1000', 'name' => 'Fremd-Kasse', 'typ' => KontoTyp::Aktiv]);
    app(CurrentTenant::class)->set($this->tenant);

    $this->actingAs($this->user);
    Livewire::test(Buchhaltung::class)
        ->set('b_soll', $fremdKonto->id)->set('b_haben', $this->ertrag->id)
        ->set('b_betrag', 10.0)->set('b_text', 'x')
        ->call('freieBuchung')->assertHasErrors('b_soll');
});

it('verwehrt den Zugriff ohne Finanzrolle', function () {
    $koch = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $koch->assignRole('kueche');
    $this->actingAs($koch);
    Livewire::test(Buchhaltung::class)->assertForbidden();
});
